<?php

use App\Models\ClothingItem;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\LaundryPackage;
use App\Models\Order;
use App\Support\CollectionScheduler;
use App\Support\Numbering;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $customerId = null;

    public string $customerSearch = '';

    public bool $showNewCustomerForm = false;

    public string $newCustomerName = '';

    public string $newCustomerPhone = '';

    /**
     * @var list<array{laundry_package_id: int, name: string, price: float, quantity: int, clothes: list<array{clothing_item_id: int, name: string, quantity: int}>}>
     */
    public array $cart = [];

    public string $selectedPackageId = '';

    public float $discount = 0;

    public string $discountReason = '';

    public float $extraCharge = 0;

    public float $creditToApply = 0;

    public string $paymentMethod = 'cash';

    /**
     * When set, the Terminal is in subscription mode: customer is locked to
     * the collection's subscription, packages/clothes are priced at 0
     * (covered by the subscription fee), and only over-allowance clothes
     * carry a manual extra charge. See Master Document §2.2.
     */
    public ?int $collectionId = null;

    public function mount(?int $collectionId = null): void
    {
        if ($collectionId === null) {
            return;
        }

        $collection = Collection::with('subscription')->find($collectionId);

        if (! $collection || $collection->status !== 'scheduled') {
            return;
        }

        $this->collectionId = $collectionId;
        $this->customerId = $collection->subscription->customer_id;
    }

    #[Computed]
    public function isSubscriptionMode(): bool
    {
        return $this->collectionId !== null;
    }

    #[Computed]
    public function collection()
    {
        return $this->collectionId
            ? Collection::with('subscription.customer', 'subscription.subscriptionPackage')->find($this->collectionId)
            : null;
    }

    #[Computed]
    public function allowance(): int
    {
        return $this->collection?->subscription?->subscriptionPackage?->clothes_allowance ?? 0;
    }

    #[Computed]
    public function clothesCount(): int
    {
        return collect($this->cart)->sum(fn ($line) => collect($line['clothes'])->sum('quantity'));
    }

    #[Computed]
    public function overAllowanceCount(): int
    {
        return max(0, $this->clothesCount - $this->allowance);
    }

    #[Computed]
    public function customerResults()
    {
        if ($this->customerId !== null || trim($this->customerSearch) === '') {
            return collect();
        }

        $term = '%'.$this->customerSearch.'%';

        return Customer::where('full_name', 'like', $term)
            ->orWhere('phone', 'like', $term)
            ->orderBy('full_name')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function selectedCustomer()
    {
        return $this->customerId ? Customer::find($this->customerId) : null;
    }

    #[Computed]
    public function packages()
    {
        return LaundryPackage::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function clothingItemsByCategory()
    {
        return ClothingItem::with('category')->get()->groupBy(fn ($item) => $item->category->name ?? 'Uncategorized');
    }

    #[Computed]
    public function subtotal(): float
    {
        if ($this->isSubscriptionMode) {
            return 0.0;
        }

        return collect($this->cart)->sum(fn ($line) => $line['price'] * $line['quantity']);
    }

    #[Computed]
    public function total(): float
    {
        if ($this->isSubscriptionMode) {
            return max(0, $this->extraCharge);
        }

        return max(0, $this->subtotal - $this->discount);
    }

    #[Computed]
    public function availableCredit(): float
    {
        return (float) ($this->selectedCustomer->store_credit_balance ?? 0);
    }

    #[Computed]
    public function maxApplicableCredit(): float
    {
        return round(min($this->availableCredit, $this->total), 2);
    }

    #[Computed]
    public function remainingDue(): float
    {
        return max(0, round($this->total - $this->creditToApply, 2));
    }

    public function selectCustomer(int $id): void
    {
        if ($this->isSubscriptionMode) {
            return;
        }

        $this->customerId = $id;
        $this->customerSearch = '';
    }

    public function clearCustomer(): void
    {
        if ($this->isSubscriptionMode) {
            return;
        }

        $this->customerId = null;
    }

    public function createCustomer(): void
    {
        if ($this->isSubscriptionMode) {
            return;
        }

        $this->validate([
            'newCustomerName' => ['required', 'string', 'max:255'],
            'newCustomerPhone' => ['required', 'string', 'regex:/^[+0-9][0-9 ()\-]{6,19}$/', 'unique:customers,phone'],
        ], [], ['newCustomerName' => 'name', 'newCustomerPhone' => 'phone']);

        $customer = Customer::create([
            'full_name' => $this->newCustomerName,
            'phone' => $this->newCustomerPhone,
            'customer_type' => 'walk_in',
        ]);

        $this->customerId = $customer->id;
        $this->newCustomerName = '';
        $this->newCustomerPhone = '';
        $this->showNewCustomerForm = false;
    }

    public function addPackage(): void
    {
        if ($this->selectedPackageId === '') {
            return;
        }

        $package = LaundryPackage::find($this->selectedPackageId);

        if (! $package) {
            return;
        }

        foreach ($this->cart as $index => $line) {
            if ($line['laundry_package_id'] === $package->id) {
                $this->cart[$index]['quantity']++;
                $this->selectedPackageId = '';

                return;
            }
        }

        $this->cart[] = [
            'laundry_package_id' => $package->id,
            'name' => $package->name,
            'price' => $this->isSubscriptionMode ? 0.0 : (float) $package->base_price,
            'quantity' => 1,
            'clothes' => [],
        ];

        $this->selectedPackageId = '';
    }

    public function removePackage(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function incrementPackage(int $index): void
    {
        $this->cart[$index]['quantity']++;
    }

    public function decrementPackage(int $index): void
    {
        if ($this->cart[$index]['quantity'] > 1) {
            $this->cart[$index]['quantity']--;
        }
    }

    public function addClothingItem(int $packageIndex, int $itemId): void
    {
        $item = ClothingItem::find($itemId);

        if (! $item || ! isset($this->cart[$packageIndex])) {
            return;
        }

        foreach ($this->cart[$packageIndex]['clothes'] as $ci => $clothesLine) {
            if ($clothesLine['clothing_item_id'] === $item->id) {
                $this->cart[$packageIndex]['clothes'][$ci]['quantity']++;

                return;
            }
        }

        $this->cart[$packageIndex]['clothes'][] = [
            'clothing_item_id' => $item->id,
            'name' => $item->name,
            'quantity' => 1,
        ];
    }

    public function removeClothingItem(int $packageIndex, int $clothesIndex): void
    {
        unset($this->cart[$packageIndex]['clothes'][$clothesIndex]);
        $this->cart[$packageIndex]['clothes'] = array_values($this->cart[$packageIndex]['clothes']);
    }

    public function submitOrder()
    {
        if ($this->isSubscriptionMode) {
            return $this->submitSubscriptionCollection();
        }

        $this->validate([
            'customerId' => ['required', 'exists:customers,id'],
            'discountReason' => [$this->discount > 0 ? 'required' : 'nullable', 'string', 'max:255'],
            'creditToApply' => ['numeric', 'min:0', 'max:'.$this->maxApplicableCredit],
            'paymentMethod' => [$this->remainingDue > 0 ? 'required' : 'nullable', 'in:cash,card,mixed'],
        ], [
            'customerId.required' => 'Select or add a customer first.',
            'discountReason.required' => 'A discount needs a reason.',
            'creditToApply.max' => 'Cannot apply more than the available store credit (or the order total).',
        ]);

        if (empty($this->cart)) {
            $this->addError('cart', 'Add at least one package before creating the order.');

            return;
        }

        try {
            $order = DB::transaction(function () {
                $order = Order::create([
                    'order_number' => Numbering::nextOrderNumber(),
                    'customer_id' => $this->customerId,
                    'user_id' => auth()->id(),
                    'order_source' => 'walk_in',
                    'subtotal' => $this->subtotal,
                    'discount' => $this->discount,
                    'discount_reason' => $this->discount > 0 ? $this->discountReason : null,
                    'extra_charge' => 0,
                ]);
                $order->refresh();

                $this->createLineItems($order);
                $this->applyCreditAndPayment($order);

                $order->receipt()->create([
                    'receipt_number' => Numbering::nextReceiptNumber(),
                    'reprint_count' => 0,
                ]);

                return $order;
            });
        } catch (QueryException $e) {
            $this->addError('cart', $this->friendlyDatabaseError($e));

            return;
        }

        session()->flash('status', "Order {$order->order_number} created.");

        $this->redirect(route('orders.show', $order), navigate: false);
    }

    protected function submitSubscriptionCollection()
    {
        $collection = $this->collection;

        if (! $collection || $collection->status !== 'scheduled') {
            $this->addError('cart', 'This collection is no longer available.');

            return;
        }

        $this->validate([
            'extraCharge' => [$this->overAllowanceCount > 0 ? 'required' : 'nullable', 'numeric', 'min:0.01'],
            'creditToApply' => ['numeric', 'min:0', 'max:'.$this->maxApplicableCredit],
            'paymentMethod' => [$this->remainingDue > 0 ? 'required' : 'nullable', 'in:cash,card,mixed'],
        ], [
            'extraCharge.required' => 'This collection is over the allowance -- enter the extra charge.',
            'creditToApply.max' => 'Cannot apply more than the available store credit (or the order total).',
        ]);

        if (empty($this->cart)) {
            $this->addError('cart', 'Record at least one package collected.');

            return;
        }

        try {
            $order = DB::transaction(function () use ($collection) {
                $order = Order::create([
                    'order_number' => Numbering::nextOrderNumber(),
                    'customer_id' => $collection->subscription->customer_id,
                    'collection_id' => $collection->id,
                    'user_id' => auth()->id(),
                    'order_source' => 'subscription',
                    'subtotal' => 0,
                    'discount' => 0,
                    'extra_charge' => $this->overAllowanceCount > 0 ? $this->extraCharge : 0,
                ]);
                $order->refresh();

                $this->createLineItems($order);
                $this->applyCreditAndPayment($order);

                $order->receipt()->create([
                    'receipt_number' => Numbering::nextReceiptNumber(),
                'reprint_count' => 0,
            ]);

                $collection->update(['status' => 'collected', 'collected_at' => now()]);
                CollectionScheduler::scheduleNext($collection->subscription, $collection->scheduled_date);

                return $order;
            });
        } catch (QueryException $e) {
            $this->addError('cart', $this->friendlyDatabaseError($e));

            return;
        }

        session()->flash('status', "Collection recorded as order {$order->order_number}.");

        $this->redirect(route('orders.show', $order), navigate: false);
    }

    /**
     * Marks a clothes line as "extra" once the running total for the whole
     * cart passes the subscription's allowance -- line-level granularity,
     * not per-unit, to keep the allocation simple and deterministic.
     */
    protected function createLineItems(Order $order): void
    {
        $cumulative = 0;

        foreach ($this->cart as $line) {
            $packageLine = $order->packageLines()->create([
                'laundry_package_id' => $line['laundry_package_id'],
                'package_name_snapshot' => $line['name'],
                'package_price_snapshot' => $line['price'],
                'quantity' => $line['quantity'],
            ]);

            foreach ($line['clothes'] as $clothesLine) {
                $cumulative += $clothesLine['quantity'];
                $isExtra = $this->isSubscriptionMode && $cumulative > $this->allowance;

                $packageLine->clothesLines()->create([
                    'clothing_item_id' => $clothesLine['clothing_item_id'],
                    'item_name_snapshot' => $clothesLine['name'],
                    'item_price_snapshot' => 0,
                    'quantity' => $clothesLine['quantity'],
                    'is_extra' => $isExtra,
                ]);
            }
        }
    }

    /**
     * Applies store credit (§2.5: "Apply credit_applied portion, collect the
     * remaining cash/card amount") then records the payment. The
     * credit_transactions insert is what actually enforces the balance check
     * (trg_credit_transactions_overdraft_guard, row-locked) -- creditToApply
     * being pre-validated against maxApplicableCredit is a UX nicety, not the
     * real guarantee against double-spend.
     */
    protected function applyCreditAndPayment(Order $order): void
    {
        if ($order->total_amount <= 0) {
            return;
        }

        $creditApplied = min($this->creditToApply, $order->total_amount);

        if ($creditApplied > 0) {
            $order->customer->creditTransactions()->create([
                'type' => 'debit',
                'amount' => $creditApplied,
                'reference_type' => 'order',
                'reference_id' => $order->id,
                'created_by' => auth()->id(),
            ]);
        }

        $order->payments()->create([
            'amount' => $order->total_amount,
            'credit_applied' => $creditApplied,
            'method' => $creditApplied >= $order->total_amount ? 'store_credit' : $this->paymentMethod,
            'received_by' => auth()->id(),
        ]);
    }

    /**
     * Translates a trigger's SIGNAL rejection into a message a staff member
     * can act on, instead of a raw SQLSTATE error reaching the UI.
     */
    protected function friendlyDatabaseError(QueryException $e): string
    {
        $message = $e->getMessage();

        return match (true) {
            str_contains($message, 'Payment would exceed order total') => 'This payment would exceed the order total. Refresh and try again.',
            str_contains($message, 'Insufficient store credit balance') => 'Insufficient store credit balance -- it may have just been redeemed elsewhere. Refresh and try again.',
            default => 'Something went wrong saving this order. No charge was made.',
        };
    }
};
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5" wire:key="terminal-root">

    <div class="bg-surface border border-line rounded-2xl p-5">
        <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-3">Customer</div>

        @if ($this->selectedCustomer)
            <div class="flex items-start justify-between p-3 rounded-lg bg-accent-soft">
                <div>
                    <div class="font-semibold text-accent-ink">{{ $this->selectedCustomer->full_name }}</div>
                    <div class="text-xs text-ink-muted font-mono">{{ $this->selectedCustomer->phone }}</div>
                </div>
                @unless ($this->isSubscriptionMode)
                    <button type="button" wire:click="clearCustomer" class="text-xs text-ink-muted hover:text-critical">Change</button>
                @endunless
            </div>
        @elseif (! $this->isSubscriptionMode)
            <input
                type="text"
                wire:model.live.debounce.300ms="customerSearch"
                placeholder="Search name or phone…"
                class="w-full bg-surface border-line-strong text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent rounded-lg shadow-sm text-sm mb-2"
            >
            @error('customerId') <p class="text-critical text-xs mb-2">{{ $message }}</p> @enderror

            @if ($this->customerResults->isNotEmpty())
                <div class="border border-line rounded-lg divide-y divide-line mb-3 max-h-48 overflow-y-auto">
                    @foreach ($this->customerResults as $result)
                        <button type="button" wire:click="selectCustomer({{ $result->id }})" class="w-full text-left px-3 py-2 text-sm hover:bg-surface-2">
                            <div class="text-ink">{{ $result->full_name }}</div>
                            <div class="text-ink-faint text-xs font-mono">{{ $result->phone }}</div>
                        </button>
                    @endforeach
                </div>
            @endif

            <button type="button" wire:click="$toggle('showNewCustomerForm')" class="text-xs text-accent-ink hover:underline">
                {{ $showNewCustomerForm ? 'Cancel new customer' : '+ New customer' }}
            </button>

            @if ($showNewCustomerForm)
                <div class="mt-3 space-y-2">
                    <input type="text" wire:model="newCustomerName" placeholder="Full name" class="w-full bg-surface border-line-strong rounded-lg shadow-sm text-sm">
                    @error('newCustomerName') <p class="text-critical text-xs">{{ $message }}</p> @enderror
                    <input type="text" wire:model="newCustomerPhone" placeholder="+220 555 1234" class="w-full bg-surface border-line-strong rounded-lg shadow-sm text-sm">
                    @error('newCustomerPhone') <p class="text-critical text-xs">{{ $message }}</p> @enderror
                    <button type="button" wire:click="createCustomer" class="w-full inline-flex items-center justify-center px-4 py-2 bg-accent rounded-lg text-white text-sm font-semibold">Add &amp; select</button>
                </div>
            @endif
        @endif

        @if ($this->isSubscriptionMode)
            <div class="mt-4 p-3 rounded-lg bg-surface-2 text-sm">
                <div class="flex justify-between text-ink-muted mb-1">
                    <span>Plan</span>
                    <span class="text-ink">{{ $this->collection?->subscription?->subscriptionPackage?->name }}</span>
                </div>
                <div class="flex justify-between text-ink-muted">
                    <span>Allowance</span>
                    <span class="font-mono tabular-nums {{ $this->overAllowanceCount > 0 ? 'text-critical font-semibold' : 'text-ink' }}">
                        {{ $this->clothesCount }} / {{ $this->allowance }}
                        @if ($this->overAllowanceCount > 0) (+{{ $this->overAllowanceCount }} over) @endif
                    </span>
                </div>
            </div>
        @endif

        <div class="mt-6">
            <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-3">Package</div>
            <div class="flex gap-2">
                <select wire:model="selectedPackageId" class="flex-1 bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
                    <option value="">Select a package…</option>
                    @foreach ($this->packages as $package)
                        <option value="{{ $package->id }}">
                            {{ $package->name }}
                            @unless ($this->isSubscriptionMode) — GMD {{ number_format($package->base_price, 2) }} @endunless
                        </option>
                    @endforeach
                </select>
                <button type="button" wire:click="addPackage" class="px-4 py-2 bg-accent-soft text-accent-ink rounded-lg text-sm font-semibold">Add</button>
            </div>
            @error('cart') <p class="text-critical text-xs mt-2">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="lg:col-span-2 bg-surface border border-line rounded-2xl p-5">
        <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-3">Cart</div>

        @forelse ($cart as $index => $line)
            <div class="border border-line rounded-xl p-4 mb-3" wire:key="pkg-{{ $index }}">
                <div class="flex items-center justify-between mb-3">
                    <div class="font-semibold text-ink">{{ $line['name'] }}</div>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="decrementPackage({{ $index }})" class="w-6 h-6 rounded bg-surface-2 text-ink-muted">−</button>
                            <span class="font-mono text-sm w-6 text-center tabular-nums">{{ $line['quantity'] }}</span>
                            <button type="button" wire:click="incrementPackage({{ $index }})" class="w-6 h-6 rounded bg-surface-2 text-ink-muted">+</button>
                        </div>
                        @unless ($this->isSubscriptionMode)
                            <span class="font-mono text-sm tabular-nums text-ink-muted">GMD {{ number_format($line['price'] * $line['quantity'], 2) }}</span>
                        @endunless
                        <button type="button" wire:click="removePackage({{ $index }})" class="text-critical text-xs hover:underline">Remove</button>
                    </div>
                </div>

                <div class="flex flex-wrap gap-1.5 mb-2">
                    @foreach ($line['clothes'] as $ci => $clothesLine)
                        <span class="inline-flex items-center gap-1.5 bg-pill-bg text-pill-ink font-mono text-xs px-2.5 py-1 rounded-full">
                            {{ $clothesLine['name'] }} × {{ $clothesLine['quantity'] }}
                            <button type="button" wire:click="removeClothingItem({{ $index }}, {{ $ci }})" class="text-ink-faint hover:text-critical">×</button>
                        </span>
                    @endforeach
                </div>

                <details class="text-sm">
                    <summary class="text-accent-ink cursor-pointer text-xs font-semibold">+ Add clothes to this package</summary>
                    <div class="mt-2 max-h-40 overflow-y-auto space-y-2">
                        @foreach ($this->clothingItemsByCategory as $categoryName => $items)
                            <div>
                                <div class="text-ink-faint text-xs font-mono uppercase mb-1">{{ $categoryName }}</div>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($items as $item)
                                        <button type="button" wire:click="addClothingItem({{ $index }}, {{ $item->id }})" class="px-2.5 py-1 rounded-full border border-line-strong text-xs text-ink-muted hover:border-accent hover:text-accent-ink">
                                            {{ $item->name }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </details>
            </div>
        @empty
            <div class="text-center py-10 text-ink-faint text-sm">
                {{ $this->isSubscriptionMode ? 'Select a package to record what was collected.' : 'Select a package to start the cart.' }}
            </div>
        @endforelse

        <div class="border-t border-line mt-4 pt-4 space-y-3">
            @if ($this->isSubscriptionMode)
                @if ($this->overAllowanceCount > 0)
                    <div class="flex items-center gap-3">
                        <label class="text-sm text-critical font-semibold w-28">Extra charge</label>
                        <input type="number" step="0.01" min="0" wire:model.live="extraCharge" class="w-32 bg-surface border-critical/40 rounded-lg shadow-sm text-sm font-mono">
                        <span class="text-xs text-ink-faint">for {{ $this->overAllowanceCount }} item(s) over allowance</span>
                    </div>
                    @error('extraCharge') <p class="text-critical text-xs">{{ $message }}</p> @enderror
                @else
                    <p class="text-sm text-success">Within allowance — no extra charge.</p>
                @endif
            @else
                <div class="flex items-center justify-between text-sm">
                    <span class="text-ink-muted">Subtotal</span>
                    <span class="font-mono tabular-nums text-ink">GMD {{ number_format($this->subtotal, 2) }}</span>
                </div>

                <div class="flex items-center gap-3">
                    <label class="text-sm text-ink-muted w-20">Discount</label>
                    <input type="number" step="0.01" min="0" wire:model.live="discount" class="w-32 bg-surface border-line-strong rounded-lg shadow-sm text-sm font-mono">
                    <input type="text" wire:model="discountReason" placeholder="Reason (required if discount > 0)" class="flex-1 bg-surface border-line-strong rounded-lg shadow-sm text-sm">
                </div>
                @error('discountReason') <p class="text-critical text-xs">{{ $message }}</p> @enderror
            @endif

            @if ($this->total > 0 && $this->availableCredit > 0)
                <div class="flex items-center gap-3">
                    <label class="text-sm text-ink-muted w-28">Store credit</label>
                    <input type="number" step="0.01" min="0" max="{{ $this->maxApplicableCredit }}" wire:model.live="creditToApply" class="w-32 bg-surface border-line-strong rounded-lg shadow-sm text-sm font-mono">
                    <span class="text-xs text-ink-faint">of GMD {{ number_format($this->availableCredit, 2) }} available</span>
                </div>
                @error('creditToApply') <p class="text-critical text-xs">{{ $message }}</p> @enderror
            @endif

            @if ($this->remainingDue > 0)
                <div class="flex items-center gap-3">
                    <label class="text-sm text-ink-muted w-28">Payment</label>
                    <select wire:model="paymentMethod" class="flex-1 bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="mixed">Mixed</option>
                    </select>
                </div>
            @endif

            @if ($this->creditToApply > 0)
                <div class="flex items-center justify-between text-sm">
                    <span class="text-ink-muted">Store credit applied</span>
                    <span class="font-mono tabular-nums text-accent-ink">− GMD {{ number_format(min($this->creditToApply, $this->total), 2) }}</span>
                </div>
            @endif

            <div class="flex items-center justify-between pt-2 border-t border-line">
                <span class="font-semibold text-ink">{{ $this->creditToApply > 0 ? 'Remaining due' : 'Total' }}</span>
                <span class="font-mono text-lg font-bold tabular-nums text-accent-ink">GMD {{ number_format($this->creditToApply > 0 ? $this->remainingDue : $this->total, 2) }}</span>
            </div>

            <button type="button" wire:click="submitOrder" wire:loading.attr="disabled" class="w-full inline-flex items-center justify-center px-5 py-3 bg-accent rounded-lg text-white font-semibold text-sm disabled:opacity-60">
                <span wire:loading.remove wire:target="submitOrder">{{ $this->isSubscriptionMode ? 'Record Collection' : 'Create Order' }}</span>
                <span wire:loading wire:target="submitOrder">Saving…</span>
            </button>
        </div>
    </div>
</div>
