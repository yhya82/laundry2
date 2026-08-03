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
            $data['photo_path'] = $request->file('photo')->store('damage', 'public');
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
        $damageRecord->load(['order.customer', 'damageType', 'resolution']);

        return view('damage.show', compact('damageRecord'));
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
