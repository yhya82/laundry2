<?php

namespace App\Http\Controllers;

use App\Models\LoginEvent;
use App\Models\Setting;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\SecurityPolicy;
use App\Support\SecurityScore;
use App\Support\UserAgent;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $settings = Setting::all()->keyBy('key');

        // The Security tab is Admin-only -- its data isn't even built for
        // anyone else with settings.manage.
        $security = $request->user()->hasRole('Admin') ? $this->securityData($request) : null;

        return view('settings.index', compact('settings', 'security'));
    }

    /**
     * Everything the Security tab shows: overview counts, the policy,
     * signed-in devices, the login log (failed attempts by default) and the
     * top offending IPs/emails of the last 24 hours. Filters arrive as
     * query parameters so they survive pagination and links.
     */
    protected function securityData(Request $request): array
    {
        $expiryDays = SecurityPolicy::passwordExpiryDays();
        $lifetime = SecurityPolicy::idleTimeoutMinutes();

        $sessions = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes($lifetime)->getTimestamp())
            ->when($request->filled('sess_user'), fn ($q) => $q->where('user_id', $request->integer('sess_user')))
            ->when($request->boolean('sess_recent'), fn ($q) => $q->where('last_activity', '>=', now()->subMinutes(15)->getTimestamp()))
            ->orderByDesc('last_activity')
            ->limit(200)
            ->get(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity']);

        $sessionUsers = User::whereIn('id', $sessions->pluck('user_id')->unique())->get()->keyBy('id');

        $devices = $sessions
            ->map(fn ($s) => (object) [
                'id' => $s->id,
                'user' => $sessionUsers->get($s->user_id),
                'ip' => $s->ip_address,
                'user_agent' => $s->user_agent,
                'agent' => UserAgent::describe($s->user_agent),
                'last_activity' => Carbon::createFromTimestamp($s->last_activity),
                'is_current' => $s->id === $request->session()->getId(),
                'idle_soon' => $s->last_activity < now()->subMinutes(max(1, $lifetime - 10))->getTimestamp(),
            ])
            ->filter(fn ($d) => $d->user !== null)
            ->when($request->filled('sess_device'), fn ($c) => $c->filter(fn ($d) => $d->agent['device'] === $request->input('sess_device')))
            ->values();

        $eventFilter = $request->input('ev_event', 'failed');

        $events = LoginEvent::with(['user', 'actor'])
            ->when($eventFilter === 'failed', fn ($q) => $q->whereIn('event', ['failed', 'lockout']))
            ->when($eventFilter !== 'failed' && $eventFilter !== 'all', fn ($q) => $q->where('event', $eventFilter))
            ->when($request->filled('ev_email'), fn ($q) => $q->where('email', 'like', '%'.$request->input('ev_email').'%'))
            ->when($request->filled('ev_ip'), fn ($q) => $q->where('ip_address', 'like', $request->input('ev_ip').'%'))
            ->when($request->filled('ev_reason'), fn ($q) => $q->where('reason', $request->input('ev_reason')))
            ->when($request->filled('ev_from'), fn ($q) => $q->where('created_at', '>=', Carbon::parse($request->input('ev_from'))->startOfDay()))
            ->when($request->filled('ev_to'), fn ($q) => $q->where('created_at', '<=', Carbon::parse($request->input('ev_to'))->endOfDay()))
            ->latest('id')
            ->paginate(25, ['*'], 'events_page')
            ->withQueryString();

        $recentFailures = LoginEvent::whereIn('event', ['failed', 'lockout'])->where('created_at', '>=', now()->subDay());

        $topIps = (clone $recentFailures)->selectRaw('ip_address, COUNT(*) as attempts, MAX(created_at) as last_at')
            ->groupBy('ip_address')->orderByDesc('attempts')->limit(5)->get();
        $topEmails = (clone $recentFailures)->whereNotNull('email')->selectRaw('email, COUNT(*) as attempts, MAX(created_at) as last_at')
            ->groupBy('email')->orderByDesc('attempts')->limit(5)->get();

        $overview = [
            'expiring_soon' => $expiryDays > 0
                ? User::where('is_active', true)->whereBetween('password_changed_at', [now()->subDays($expiryDays), now()->subDays($expiryDays)->addDays(7)])->count()
                : 0,
            'expired' => $expiryDays > 0
                ? User::where('is_active', true)->where('password_changed_at', '<=', now()->subDays($expiryDays))->count()
                : 0,
            'temporary' => User::where('is_active', true)->where('must_change_password', true)->count(),
            // A lock lives in the rate limiter, not the DB -- this counts
            // distinct email+IP pairs locked within the current lock window.
            'locked' => LoginEvent::where('event', 'lockout')
                ->where('created_at', '>=', now()->subMinutes(SecurityPolicy::lockoutMinutes()))
                ->selectRaw('COUNT(DISTINCT CONCAT(COALESCE(email, \'\'), \'|\', COALESCE(ip_address, \'\'))) as total')
                ->value('total'),
            'failed_today' => LoginEvent::where('event', 'failed')->where('created_at', '>=', now()->startOfDay())->count(),
            'devices' => $devices->count(),
        ];

        $people = $this->peopleStatus($request, $lifetime);

        $failedLast24h = LoginEvent::where('event', 'failed')->where('created_at', '>=', now()->subDay())->count();
        $lockoutsLast24h = LoginEvent::where('event', 'lockout')->where('created_at', '>=', now()->subDay())->count();

        return [
            'score' => SecurityScore::calculate($failedLast24h, $lockoutsLast24h),
            // Bell dot: red if anything is locked now, amber if there were
            // failures in the last 24h, green when it's quiet.
            'alertLevel' => $overview['locked'] > 0 ? 'critical' : ($failedLast24h > 0 ? 'warning' : 'ok'),
            'failedLast24h' => $failedLast24h,
            'overview' => $overview,
            'people' => $people['rows'],
            'peopleCounts' => $people['counts'],
            'devices' => $devices,
            'deviceUsers' => User::orderBy('name')->get(['id', 'name']),
            'events' => $events,
            'eventFilter' => $eventFilter,
            'topIps' => $topIps,
            'topEmails' => $topEmails,
        ];
    }

    /**
     * Who's signed in right now and who's signed out, per staff account
     * (the Security tab's People view). Built from two sources:
     *  - the `sessions` table: a live session (within the idle timeout)
     *    means signed in -- "online" if active in the last 5 minutes,
     *    otherwise "idle";
     *  - the login log: the latest sign-in, log-out and admin sign-out say
     *    HOW someone without a live session left -- logged out themselves,
     *    signed out by an Admin, or the session simply expired.
     *
     * @return array{rows: \Illuminate\Support\Collection, counts: array<string, int>}
     */
    protected function peopleStatus(Request $request, int $lifetime): array
    {
        $latest = fn (array $events) => LoginEvent::select('created_at')
            ->whereColumn('user_id', 'users.id')
            ->whereIn('event', $events)
            ->latest('id')
            ->limit(1);

        $users = User::with('roles')
            ->select('users.*')
            ->addSelect([
                'last_login_at' => $latest(['login']),
                'last_logout_at' => $latest(['logout']),
                'last_revoked_at' => $latest(['sessions_revoked', 'password_reset_by_admin']),
            ])
            ->orderBy('name')
            ->get();

        $sessions = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes($lifetime)->getTimestamp())
            ->selectRaw('user_id, COUNT(*) as devices, MAX(last_activity) as last_activity')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $onlineCutoff = now()->subMinutes(5)->getTimestamp();

        $rows = $users->map(function (User $user) use ($sessions, $onlineCutoff) {
            $session = $sessions->get($user->id);
            $login = $user->last_login_at ? Carbon::parse($user->last_login_at) : null;
            $logout = $user->last_logout_at ? Carbon::parse($user->last_logout_at) : null;
            $revoked = $user->last_revoked_at ? Carbon::parse($user->last_revoked_at) : null;

            if ($session) {
                $status = $session->last_activity >= $onlineCutoff ? 'online' : 'idle';
                $how = null;
                $signedOutAt = null;
            } else {
                $status = 'signed_out';
                // The most recent way out that came after the last sign-in.
                [$how, $signedOutAt] = match (true) {
                    ! $login => ['never', null],
                    $revoked && $revoked->gte($login) && (! $logout || $revoked->gte($logout)) => ['admin', $revoked],
                    $logout && $logout->gte($login) => ['logout', $logout],
                    default => ['expired', null],
                };
            }

            return (object) [
                'user' => $user,
                'status' => $status,
                'how' => $how,
                'devices' => (int) ($session->devices ?? 0),
                'last_active' => $session ? Carbon::createFromTimestamp($session->last_activity) : null,
                'signed_in_at' => $login,
                'signed_out_at' => $signedOutAt,
            ];
        });

        $counts = [
            'online' => $rows->where('status', 'online')->count(),
            'idle' => $rows->where('status', 'idle')->count(),
            'signed_out' => $rows->where('status', 'signed_out')->count(),
        ];

        // Signed-in people first (online, then idle), most recent first.
        $filter = $request->input('people_status');
        $search = trim((string) $request->input('people_q'));

        $rows = $rows
            ->when($filter === 'signed_in', fn ($c) => $c->whereIn('status', ['online', 'idle']))
            ->when($filter === 'signed_out', fn ($c) => $c->where('status', 'signed_out'))
            ->when($search !== '', fn ($c) => $c->filter(fn ($r) => str_contains(strtolower($r->user->name.' '.$r->user->email), strtolower($search))))
            ->sortBy([
                fn ($a, $b) => ['online' => 0, 'idle' => 1, 'signed_out' => 2][$a->status] <=> ['online' => 0, 'idle' => 1, 'signed_out' => 2][$b->status],
                fn ($a, $b) => ($b->last_active ?? $b->signed_out_at ?? $b->signed_in_at)?->getTimestamp() <=> ($a->last_active ?? $a->signed_out_at ?? $a->signed_in_at)?->getTimestamp(),
            ])
            ->values();

        return ['rows' => $rows, 'counts' => $counts];
    }

    public function update(Request $request): RedirectResponse
    {
        $group = $request->input('group');

        if ($group === 'security') {
            abort_unless($request->user()->hasRole('Admin'), 403, 'Only an Admin can change security settings.');
        }

        match ($group) {
            'security' => $this->saveSecurity($request),
            'general' => $this->saveGeneral($request),
            'laundry' => $this->saveLaundry($request),
            'subscription' => $this->saveSubscription($request),
            'payment' => $this->savePayment($request),
            'notification' => $this->saveNotification($request),
            'order' => $this->saveOrder($request),
            'receipt' => $this->saveReceipt($request),
            'backup' => $this->saveBackup($request),
            default => abort(422, 'Unknown settings group.'),
        };

        if ($group === 'security') {
            return back()->with('status', $request->input('policy_mode') === 'create' ? 'Password policy created.' : 'Password policy updated.');
        }

        return back()->with('status', ucfirst($group).' settings saved.');
    }

    /**
     * Ranges come from SecurityPolicy::RANGES, so the form, the validation
     * and the policy's own clamping always agree. Stricter rules apply the
     * next time each person sets a password -- existing passwords keep
     * working until then (or until they expire).
     */
    protected function saveSecurity(Request $request): void
    {
        $range = fn (string $key) => 'between:'.implode(',', SecurityPolicy::RANGES["security.{$key}"]);

        $validated = $request->validate([
            'password_min_length' => ['required', 'integer', $range('password_min_length')],
            'password_expiry_days' => ['required', 'integer', $range('password_expiry_days')],
            'password_history' => ['required', 'integer', $range('password_history')],
            'lockout_attempts' => ['required', 'integer', $range('lockout_attempts')],
            'lockout_minutes' => ['required', 'integer', $range('lockout_minutes')],
            'idle_timeout_minutes' => ['required', 'integer', $range('idle_timeout_minutes')],
        ], [], [
            'password_min_length' => 'minimum password length',
            'password_expiry_days' => 'password expiry',
            'password_history' => 'password history',
            'lockout_attempts' => 'failed attempts before lockout',
            'lockout_minutes' => 'lockout length',
            'idle_timeout_minutes' => 'idle timeout',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set("security.{$key}", (string) $value, 'security', 'integer');
        }

        foreach (['password_require_mixed_case', 'password_require_numbers', 'password_require_symbols', 'password_block_breached'] as $flag) {
            Setting::set("security.{$flag}", $request->boolean($flag) ? 'true' : 'false', 'security', 'boolean');
        }
    }

    protected function saveGeneral(Request $request): void
    {
        $request->merge(['phone' => PhoneNumber::normalize($request->input('phone'))]);

        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::VALID_PATTERN],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'phone.regex' => PhoneNumber::INVALID_MESSAGE,
        ]);

        // Uploaded first, before any of the other fields are saved -- store()
        // doesn't throw when the S3 disk is unreachable (unconfigured bucket/
        // credentials, an outage), it just returns false, which used to get
        // saved as the literal path and silently discard the file while
        // still reporting success. Failing fast here means nothing (not even
        // the other fields) gets saved on a broken upload, and the person
        // uploading actually finds out it didn't work.
        $logoPath = null;

        if ($request->hasFile('logo')) {
            try {
                $logoPath = $request->file('logo')->store('branding', 's3');
            } catch (\Throwable $e) {
                report($e);
                $logoPath = false;
            }

            if (! $logoPath) {
                throw ValidationException::withMessages([
                    'logo' => 'Could not upload the logo -- storage is unavailable right now. Nothing was changed; try again once it\'s back.',
                ]);
            }
        }

        Setting::set('branding.business_name', $validated['business_name'], 'general');
        Setting::set('branding.phone', $validated['phone'], 'general');
        Setting::set('branding.email', $validated['email'] ?? null, 'general');
        Setting::set('branding.address', $validated['address'] ?? null, 'general');

        if ($logoPath) {
            Setting::set('branding.logo_path', $logoPath, 'general');
        }
    }

    protected function saveLaundry(Request $request): void
    {
        $validated = $request->validate([
            'default_turnaround_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ]);

        Setting::set('laundry.default_turnaround_hours', $validated['default_turnaround_hours'] ?? null, 'laundry', 'integer');
    }

    protected function saveSubscription(Request $request): void
    {
        Setting::set('subscription.allow_new_signups', $request->boolean('allow_new_signups') ? 'true' : 'false', 'subscription', 'boolean');
        Setting::set('subscription.charge_for_cycle_overage', $request->boolean('charge_for_cycle_overage') ? 'true' : 'false', 'subscription', 'boolean');
        Setting::set('subscription.walkin_extra_charge_enabled', $request->boolean('walkin_extra_charge_enabled') ? 'true' : 'false', 'subscription', 'boolean');
    }

    protected function savePayment(Request $request): void
    {
        Setting::set('payment.store_credit_enabled', $request->boolean('store_credit_enabled') ? 'true' : 'false', 'payment', 'boolean');
    }

    protected function saveNotification(Request $request): void
    {
        foreach (['sms_enabled', 'whatsapp_enabled', 'email_enabled'] as $field) {
            Setting::set("notification.{$field}", $request->boolean($field) ? 'true' : 'false', 'notification', 'boolean');
        }
    }

    protected function saveOrder(Request $request): void
    {
        $validated = $request->validate([
            'max_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        Setting::set('order.max_discount_percent', (string) $validated['max_discount_percent'], 'order', 'integer');
        Setting::set('order.discount_enabled', $request->boolean('discount_enabled') ? 'true' : 'false', 'order', 'boolean');
        Setting::set('order.assignment_enabled', $request->boolean('assignment_enabled') ? 'true' : 'false', 'order', 'boolean');
    }

    protected function saveReceipt(Request $request): void
    {
        $validated = $request->validate([
            'footer_message' => ['nullable', 'string', 'max:255'],
        ]);

        Setting::set('receipt.show_logo', $request->boolean('show_logo') ? 'true' : 'false', 'receipt', 'boolean');
        Setting::set('receipt.show_phone', $request->boolean('show_phone') ? 'true' : 'false', 'receipt', 'boolean');
        Setting::set('receipt.footer_message', $validated['footer_message'] ?? null, 'receipt');
    }

    protected function saveBackup(Request $request): void
    {
        $validated = $request->validate([
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'alert_email' => ['nullable', 'email', 'max:255'],
        ]);

        Setting::set('backup.retention_days', $validated['retention_days'] ?? null, 'backup', 'integer');
        Setting::set('backup.alert_email', $validated['alert_email'] ?? null, 'backup');
    }
}
