<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\LoginEvent;
use App\Models\User;
use App\Support\AuditPresenter;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Audit Log: one timeline of data changes (activity_log, written by DB
 * triggers) and -- for Admins only -- security events (login_events, the
 * Security Center's log, read live, never copied). Both are merged with a
 * UNION of (source, id, created_at), paginated, then hydrated and turned
 * into readable entries by AuditPresenter.
 *
 * Non-Admins with audit.view see data changes only: security entries carry
 * the emails people typed and their IP addresses.
 */
class AuditController extends Controller
{
    public const EXPORT_LIMIT = 10000;

    public function index(Request $request): View
    {
        $isAdmin = $request->user()->hasRole('Admin');
        $tab = $this->tab($request, $isAdmin);

        $page = $this->timeline($request, $tab, $isAdmin)->paginate(30)->withQueryString();
        $page->setCollection($this->present($page->getCollection()));

        $modules = array_keys(AuditPresenter::MODULES);
        $actions = collect(AuditPresenter::ACTIONS)
            ->when(! $isAdmin || $tab === 'data', fn ($c) => $c->except(AuditPresenter::SECURITY_ACTIONS))
            ->when($tab === 'security', fn ($c) => $c->only(AuditPresenter::SECURITY_ACTIONS));
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('audit.index', [
            'entries' => $page,
            'tab' => $tab,
            'isAdmin' => $isAdmin,
            'modules' => $modules,
            'actions' => $actions,
            'users' => $users,
        ]);
    }

    /**
     * CSV of whatever the page is currently filtered to (security rows only
     * for Admins), newest first, capped at EXPORT_LIMIT rows.
     */
    public function export(Request $request): StreamedResponse
    {
        $isAdmin = $request->user()->hasRole('Admin');
        $tab = $this->tab($request, $isAdmin);
        $rows = $this->timeline($request, $tab, $isAdmin)->limit(self::EXPORT_LIMIT)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Time', 'Source', 'Module', 'Action', 'What happened', 'By', 'Changes', 'Details']);

            foreach ($rows->chunk(500) as $chunk) {
                foreach ($this->present($chunk) as $entry) {
                    fputcsv($out, [
                        $entry['when'],
                        $entry['source'] === 'security' ? 'Security' : 'Data change',
                        $entry['module'],
                        $entry['action'],
                        $entry['title'],
                        $entry['who'],
                        collect($entry['changes'])->map(fn ($c) => $c['field'].': '.($c['before'] ?? '—').' → '.($c['after'] ?? '—'))->implode('; '),
                        collect($entry['meta'])->map(fn ($v, $k) => "{$k}: {$v}")->implode('; '),
                    ]);
                }
            }

            fclose($out);
        }, 'audit-log-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    protected function tab(Request $request, bool $isAdmin): string
    {
        $tab = $request->input('tab', $isAdmin ? 'all' : 'data');

        return $isAdmin && in_array($tab, ['all', 'data', 'security'], true) ? $tab : 'data';
    }

    /**
     * (source, id, created_at) rows across both logs, filtered and ordered.
     */
    protected function timeline(Request $request, string $tab, bool $isAdmin): Builder
    {
        $module = $request->input('module');
        $action = $request->input('action');
        $isSecurityAction = in_array($action, AuditPresenter::SECURITY_ACTIONS, true);
        $isDataAction = $action && ! $isSecurityAction;

        $wantData = $tab !== 'security' && ! $isSecurityAction && $module !== 'Security';
        $wantSecurity = $isAdmin && $tab !== 'data' && ! $isDataAction && (! $module || $module === 'Security');

        $parts = [];

        if ($wantData) {
            $parts[] = $this->dataQuery($request, $module, $action);
        }

        if ($wantSecurity) {
            $parts[] = $this->securityQuery($request, $action);
        }

        if ($parts === []) {
            // Contradictory filters (e.g. a security action on Data changes) -> nothing.
            return DB::table('activity_log')->selectRaw("'data' as source, id, created_at")->whereRaw('1 = 0');
        }

        $union = array_shift($parts);
        foreach ($parts as $part) {
            $union->unionAll($part);
        }

        return DB::query()->fromSub($union, 't')->orderByDesc('created_at')->orderByDesc('id');
    }

    protected function dataQuery(Request $request, ?string $module, ?string $action): Builder
    {
        $types = $module ? (AuditPresenter::MODULES[$module][0] ?? []) : null;
        $accessDescriptions = ['role granted', 'role removed', 'permission granted', 'permission removed'];

        return DB::table('activity_log')
            ->selectRaw("'data' as source, id, created_at")
            ->when($types !== null, fn ($q) => $q->whereIn('subject_type', $types))
            ->when(in_array($action, ['created', 'updated', 'deleted'], true), fn ($q) => $q->where('description', 'like', $action.' %')->whereNotIn('description', $accessDescriptions))
            ->when($action === 'access', fn ($q) => $q->whereIn('description', $accessDescriptions))
            ->when($request->filled('user'), fn ($q) => $q->where('causer_id', $request->integer('user')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->input('from').' 00:00:00'))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->input('to').' 23:59:59'))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim((string) $request->input('q'));
                $q->where(function ($w) use ($term) {
                    $w->where('properties', 'like', '%'.$term.'%')->orWhere('description', 'like', '%'.$term.'%');
                    if (ctype_digit(ltrim($term, '#'))) {
                        $w->orWhere('subject_id', (int) ltrim($term, '#'));
                    }
                });
            });
    }

    protected function securityQuery(Request $request, ?string $action): Builder
    {
        $events = match ($action) {
            'signed_in' => ['login'],
            'signed_out' => ['logout'],
            'failed' => ['failed'],
            'lockout' => ['lockout'],
            'admin' => ['password_reset_by_admin', 'sessions_revoked'],
            default => null,
        };

        return DB::table('login_events')
            ->selectRaw("'security' as source, id, created_at")
            ->when($events, fn ($q) => $q->whereIn('event', $events))
            ->when($request->filled('user'), fn ($q) => $q->where(fn ($w) => $w->where('user_id', $request->integer('user'))->orWhere('actor_id', $request->integer('user'))))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->input('from').' 00:00:00'))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->input('to').' 23:59:59'))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim((string) $request->input('q'));
                $q->where(fn ($w) => $w->where('email', 'like', '%'.$term.'%')->orWhere('ip_address', 'like', $term.'%'));
            });
    }

    /**
     * (source, id, created_at) rows -> presented entries, keeping their order.
     */
    protected function present(Collection $rows): Collection
    {
        $dataIds = $rows->where('source', 'data')->pluck('id');
        $securityIds = $rows->where('source', 'security')->pluck('id');

        $logs = ActivityLog::with('causer')->whereIn('id', $dataIds)->get()->keyBy('id');
        $events = LoginEvent::with(['user', 'actor'])->whereIn('id', $securityIds)->get()->keyBy('id');

        $userNames = User::whereIn('id', $logs->where('subject_type', 'App\Models\User')->pluck('subject_id')->unique())->pluck('name', 'id');

        return $rows->map(function ($row) use ($logs, $events, $userNames) {
            if ($row->source === 'security') {
                return ($event = $events->get($row->id)) ? AuditPresenter::security($event) : null;
            }

            return ($log = $logs->get($row->id)) ? AuditPresenter::activity($log, $userNames) : null;
        })->filter()->values();
    }
}
