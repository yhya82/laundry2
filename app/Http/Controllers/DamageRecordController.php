<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDamageRecordRequest;
use App\Http\Requests\StoreDamageResolutionRequest;
use App\Models\DamageRecord;
use App\Models\DamageType;
use App\Models\Order;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DamageRecordController extends Controller
{
    public function __construct(protected NotificationDispatcher $notifications)
    {
    }

    public function index(Request $request): View
    {
        $damageRecords = DamageRecord::with(['order.customer', 'damageType'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('damage.index', compact('damageRecords'));
    }

    public function create(Order $order): View
    {
        $damageTypes = DamageType::orderBy('name')->get();

        return view('damage.create', compact('order', 'damageTypes'));
    }

    public function store(StoreDamageRecordRequest $request, Order $order): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            // 'local' (private), not 'public' -- damage photos can be of the
            // customer's own belongings and shouldn't be reachable by anyone
            // who merely obtains the URL. Served back out through photo()
            // below, behind the same damage.view permission as the report
            // itself.
            $data['photo_path'] = $request->file('photo')->store('damage', 'local');
        }

        $data['reported_by'] = auth()->id();
        $data['stage_at_report'] = $order->status;

        $damageRecord = $order->damageRecords()->create($data);

        User::role('Admin')->get()->each(
            fn (User $admin) => $this->notifications->toStaff(
                $admin,
                'Damage report submitted',
                "{$damageRecord->damageType->name} reported on order {$order->order_number}."
            )
        );

        return redirect()->route('damage.show', $damageRecord)->with('status', 'Damage report submitted.');
    }

    public function show(DamageRecord $damageRecord): View
    {
        $damageRecord->load(['order.customer', 'damageType', 'reportedBy', 'resolution.resolvedBy', 'statusHistory.changedBy']);

        return view('damage.show', compact('damageRecord'));
    }

    /**
     * Streams the photo from the private disk -- gated by the same
     * damage.view permission as the report page itself (see route), so it
     * can't be reached by URL alone the way a public-disk file could.
     */
    public function photo(DamageRecord $damageRecord): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless($damageRecord->photo_path && Storage::disk('local')->exists($damageRecord->photo_path), 404);

        return Storage::disk('local')->response($damageRecord->photo_path);
    }

    /**
     * A single endpoint for every review-chain move (Under Investigation /
     * Approve / Reject / Close) -- 'resolved' is never an accepted target
     * here, so this can't become the second door into it. The UI only ever
     * renders buttons for canTransitionTo() targets; this re-checks the same
     * rule server-side.
     */
    public function transition(Request $request, DamageRecord $damageRecord): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:under_investigation,approved,rejected,closed'],
        ]);

        if (! $damageRecord->canTransitionTo($validated['status'])) {
            return back()->withErrors(['status' => 'That transition is not allowed from the current status.']);
        }

        $damageRecord->status = $validated['status'];
        $damageRecord->save();

        return back()->with('status', 'Damage report updated.');
    }

    /**
     * The only path to 'resolved' -- trg_damage_records_resolve_guard rejects
     * any direct attempt to set status='resolved' outside of this insert.
     */
    public function resolve(StoreDamageResolutionRequest $request, DamageRecord $damageRecord): RedirectResponse
    {
        if ($damageRecord->status !== 'approved') {
            return back()->withErrors(['status' => 'Only an approved damage report can be resolved.']);
        }

        $validated = $request->validated();

        $resolution = $damageRecord->resolution()->create([
            'resolution_type' => $validated['resolution_type'],
            'amount' => $validated['amount'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'resolved_by' => auth()->id(),
        ]);

        if ($resolution->resolution_type === 'store_credit' && $resolution->amount > 0) {
            $damageRecord->order->customer->creditTransactions()->create([
                'type' => 'credit',
                'amount' => $resolution->amount,
                'reference_type' => 'damage_resolution',
                'reference_id' => $resolution->id,
                'created_by' => auth()->id(),
            ]);
        }

        return redirect()->route('damage.show', $damageRecord)->with('status', 'Resolution recorded.');
    }
}
