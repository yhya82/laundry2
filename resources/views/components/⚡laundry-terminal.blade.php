<?php

use App\Events\CollectionStatusChanged;
use App\Events\OrderStatusChanged;
use App\Events\PaymentReceived;
use App\Models\ClothesCategory;
use App\Models\ClothingItem;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\LaundryPackage;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionPackage;
use App\Support\CollectionScheduler;
use App\Support\Numbering;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $customerId = null;

    /**
     * True while $newCustomerName/$newCustomerPhone hold a walk-in
     * customer's details that have been filled in and confirmed via the
     * modal, but deliberately not yet written to the customers table --
     * see createCustomer()'s doc comment for why. Never true for a
     * subscription-type new customer; that path still creates eagerly.
     */
    public bool $usingPendingCustomer = false;

    public string $customerSearch = '';

    public bool $showNewCustomerForm = false;

    public string $newCustomerName = '';

    public string $newCustomerPhone = '';

    public string $newCustomerType = 'walk_in';

    /**
     * Only meaningful while newCustomerType is 'subscription' -- carried
     * through to addSubscriptionPackage() once staff actually pick a plan
     * for this customer, since a subscription can't be created from this
     * modal alone (it only creates the Customer row).
     */
    public bool $newCustomerNonScheduled = false;

    /**
     * @var list<array{laundry_package_id: int, name: string, price: float, quantity: int, clothes: list<array{clothing_item_id: int, name: string, quantity: int}>}>
     */
    public array $cart = [];

    /**
     * Which cart line newly-clicked clothes cards are added to -- kept in
     * sync by addPackage()/removePackage() so it's always a valid index
     * into $cart whenever the cart isn't empty.
     */
    public ?int $activePackageLineIndex = null;

    /**
     * Subscription mode's own clothes list -- kept separate from $cart
     * entirely, since a subscription collection was never really "picking a
     * package"; that concept only ever existed as a container to satisfy
     * order_package_lines.laundry_package_id (see createSubscriptionLineItems()).
     * Not showing that container in the UI at all means there's nothing here
     * for staff to accidentally remove and get stuck behind.
     *
     * @var list<array{clothing_item_id: int, name: string, quantity: int}>
     */
    public array $subscriptionClothes = [];

    public string $selectedPackageId = '';

    public ?int $activeCategoryFilter = null;

    public float $discount = 0;

    public string $discountReason = '';

    /**
     * Walk-in only -- a manual addition to the order total (rush service,
     * special handling, etc.), the mirror image of discount. Gated by its
     * own Settings toggle, same shape as discount: a reason is required
     * once the amount is above 0. Entirely separate from cycleOverageCharge
     * below, which is the subscription side's own charge concept.
     */
    public float $walkInExtraCharge = 0;

    public string $walkInExtraChargeReason = '';

    /**
     * The only chargeable "over the limit" event for a subscription
     * collection: against the subscription's own max_clothes_per_cycle,
     * accumulated across every collection in the cycle. A single visit
     * running over the package's per-visit clothes_allowance is shown for
     * context (see allowance()/overAllowanceCount()) but is never itself
     * chargeable -- only the cycle-wide total crossing its cap is.
     */
    public float $cycleOverageCharge = 0;

    public float $creditToApply = 0;

    public string $paymentMethod = 'cash';

    public string $notes = '';

    /**
     * 'pay_now' (default) collects an amount immediately; 'pay_on_pickup'
     * skips payment entirely and leaves the full remainingDue as balance,
     * settled later via Order Detail's "Record Payment" action.
     */
    public string $paymentTiming = 'pay_now';

    /**
     * Left null (not pre-filled) so staff always type a deliberate amount
     * for a partial collection rather than editing down from a full total.
     * trg_payments_cap_guard already allows multiple payments to accumulate
     * up to total_amount, so collecting less than the full remaining due
     * here just leaves a real balance for later.
     */
    public ?float $customAmount = null;

    /**
     * Set right after a successful submit instead of redirecting away --
     * drives the View Order / New Order confirmation modal. The rest of the
     * Terminal's state is already fully reset by that point (see
     * resetForNewOrder()), so dismissing the modal via New Order is just
     * clearing this one property.
     */
    public ?int $lastSubmittedOrderId = null;

    /**
     * When set, the Terminal is in subscription mode: customer is locked to
     * the collection's subscription, packages/clothes are priced at 0
     * (covered by the subscription fee), and only over-allowance clothes
     * carry a manual extra charge. See Master Document §2.2.
     */
    public ?int $collectionId = null;

    /**
     * Set when arriving via the customer profile's "Walk-in Order" choice
     * (see maybeEnterExistingSubscription()) -- keeps a subscription-type
     * customer out of subscription mode entirely for this visit, so a
     * one-off item can be rung up separately from their plan. Only relevant
     * pre-entry; mounting straight into a specific collectionId already
     * bypasses this decision entirely.
     */
    public bool $forceWalkIn = false;

    public function mount(?int $collectionId = null, ?int $customerId = null, bool $forceWalkIn = false): void
    {
        $this->forceWalkIn = $forceWalkIn;

        if ($collectionId !== null) {
            $collection = Collection::with('subscription')->find($collectionId);

            if ($collection && $collection->status === 'scheduled' && $collection->subscription->status === 'active') {
                $this->collectionId = $collectionId;
                $this->customerId = $collection->subscription->customer_id;
            }
        } elseif ($customerId !== null && Customer::whereKey($customerId)->exists()) {
            $this->customerId = $customerId;
            $this->maybeEnterExistingSubscription();
        }
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
        $base = $this->collection?->subscription?->subscriptionPackage?->clothes_allowance ?? 0;

        // A cancelled collection combined into this one folds its whole
        // slot in here -- one visit, full allowance for each slot it covers.
        $slots = 1 + ($this->collection ? $this->collection->absorbedCollections()->count() : 0);

        return $base * $slots;
    }

    #[Computed]
    public function clothesCount(): int
    {
        if ($this->isSubscriptionMode) {
            return collect($this->subscriptionClothes)->sum('quantity');
        }

        return collect($this->cart)->sum(fn ($line) => collect($line['clothes'])->sum('quantity'));
    }

    /**
     * Informational only -- see the cycleOverageCharge doc comment above for
     * why a single visit running over this never itself requires a charge.
     */
    #[Computed]
    public function overAllowanceCount(): int
    {
        return max(0, $this->clothesCount - $this->allowance);
    }

    /**
     * The cycle-wide cap -- the cycle's own snapshot once one exists, else
     * the subscription's current value for a legacy collection that
     * predates cycles.
     */
    #[Computed]
    public function cycleMaxClothes(): int
    {
        if (! $this->isSubscriptionMode) {
            return 0;
        }

        $cycle = $this->collection?->subscriptionCycle;

        return $cycle
            ? $cycle->max_clothes_snapshot
            : (int) ($this->collection?->subscription?->max_clothes_per_cycle ?? 0);
    }

    /**
     * Clothes already recorded against this cycle from earlier collections
     * in it -- this visit's own (not yet submitted) cart is added on top by
     * cycleOverageCount(), not counted here.
     */
    #[Computed]
    public function cycleClothesCollectedSoFar(): int
    {
        $cycle = $this->isSubscriptionMode ? $this->collection?->subscriptionCycle : null;

        return $cycle ? $cycle->clothesCollected() : 0;
    }

    #[Computed]
    public function cycleOverageCount(): int
    {
        if (! $this->isSubscriptionMode) {
            return 0;
        }

        return max(0, ($this->cycleClothesCollectedSoFar + $this->clothesCount) - $this->cycleMaxClothes);
    }

    #[Computed]
    public function chargeForCycleOverage(): bool
    {
        return Setting::get('subscription.charge_for_cycle_overage', 'false') === 'true';
    }

    #[Computed]
    public function discountEnabled(): bool
    {
        return Setting::get('order.discount_enabled', 'false') === 'true';
    }

    #[Computed]
    public function walkInExtraChargeEnabled(): bool
    {
        return Setting::get('subscription.walkin_extra_charge_enabled', 'false') === 'true';
    }

    #[Computed]
    public function lastSubmittedOrder()
    {
        return $this->lastSubmittedOrderId ? Order::find($this->lastSubmittedOrderId) : null;
    }

    #[Computed]
    public function customerResults()
    {
        if ($this->customerId !== null || $this->usingPendingCustomer || trim($this->customerSearch) === '') {
            return collect();
        }

        $term = '%'.$this->customerSearch.'%';

        return Customer::where('full_name', 'like', $term)
            ->orWhere('phone', 'like', $term)
            ->orderBy('full_name')
            ->limit(8)
            ->get();
    }

    /**
     * A pending walk-in customer (see $usingPendingCustomer) has no id yet,
     * so this returns an unsaved Customer built from the staged name/phone
     * instead of querying for one -- every existing ->full_name/->phone/
     * ->customer_type/->store_credit_balance read elsewhere keeps working
     * unchanged (an unsaved model's balance is simply null, same as ??0
     * already treats a real new customer with nothing credited yet).
     */
    #[Computed]
    public function selectedCustomer()
    {
        if ($this->usingPendingCustomer) {
            return new Customer([
                'full_name' => $this->newCustomerName,
                'phone' => $this->newCustomerPhone,
                'customer_type' => 'walk_in',
            ]);
        }

        return $this->customerId ? Customer::find($this->customerId) : null;
    }

    /**
     * Before subscription mode is entered, a subscription-type customer picks
     * from Subscription Packages (starting/reusing their actual subscription);
     * everyone else picks from Laundry Packages, exactly as today. forceWalkIn
     * opts a subscription-type customer out of that entirely, for a one-off
     * order that shouldn't touch their plan. Once subscription mode is
     * entered there's no package picking left to show at all (see
     * $subscriptionClothes), so this only ever renders pre-entry.
     */
    #[Computed]
    public function packages()
    {
        if (! $this->isSubscriptionMode && ! $this->forceWalkIn && $this->selectedCustomer?->customer_type === 'subscription') {
            return SubscriptionPackage::where('is_active', true)->orderBy('name')->get();
        }

        return LaundryPackage::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function choosingSubscriptionPackage(): bool
    {
        return ! $this->isSubscriptionMode && ! $this->forceWalkIn && $this->selectedCustomer?->customer_type === 'subscription';
    }

    #[Computed]
    public function clothesCategories()
    {
        return ClothesCategory::orderBy('name')->get();
    }

    #[Computed]
    public function filteredClothingItems()
    {
        return ClothingItem::with('category')
            ->when($this->activeCategoryFilter, fn ($q) => $q->where('clothes_category_id', $this->activeCategoryFilter))
            ->orderBy('name')
            ->get();
    }

    /**
     * What's left of the current cycle's flat monthly price -- shared across
     * every collection in that cycle, not just this one. Starts at the
     * package's price snapshot and drops as payments land against the
     * cycle's anchor order, from any collection in it. A collection with no
     * cycle (legacy, predating this) behaves as its own lone cycle of one.
     */
    #[Computed]
    public function cycleBalanceDue(): float
    {
        if (! $this->isSubscriptionMode) {
            return 0.0;
        }

        $cycle = $this->collection?->subscriptionCycle;

        if ($cycle) {
            return $cycle->balanceDue();
        }

        return (float) ($this->collection?->subscription?->subscriptionPackage?->monthly_price ?? 0);
    }

    #[Computed]
    public function cyclePaymentCompleted(): bool
    {
        return $this->isSubscriptionMode && $this->cycleBalanceDue <= 0;
    }

    #[Computed]
    public function subtotal(): float
    {
        if ($this->isSubscriptionMode) {
            return $this->cycleBalanceDue;
        }

        return collect($this->cart)->sum(fn ($line) => $line['price'] * $line['quantity']);
    }

    #[Computed]
    public function total(): float
    {
        if ($this->isSubscriptionMode) {
            return max(0, $this->subtotal + $this->cycleOverageCharge);
        }

        return max(0, $this->subtotal - $this->discount + $this->walkInExtraCharge);
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

    /**
     * The cash/card portion actually being collected right now. Pay on
     * Pickup collects nothing regardless of what's in customAmount; Pay Now
     * clamps whatever's typed to remainingDue.
     */
    #[Computed]
    public function amountToCollect(): float
    {
        if ($this->paymentTiming === 'pay_on_pickup') {
            return 0.0;
        }

        return min(max(0, (float) ($this->customAmount ?? 0)), $this->remainingDue);
    }

    public function selectCustomer(int $id): void
    {
        // Reads collectionId directly rather than the memoized isSubscriptionMode
        // computed -- same reason as addPackage() below: maybeEnterExistingSubscription()
        // is exactly what sets collectionId, so reading the computed first would
        // cache a stale "not in subscription mode yet" for the render that follows.
        if ($this->collectionId !== null) {
            return;
        }

        $this->customerId = $id;
        $this->customerSearch = '';
        $this->newCustomerNonScheduled = false;

        $this->maybeEnterExistingSubscription();
    }

    /**
     * Skips the Plan picker entirely when this customer already has exactly
     * one active subscription with an open collection -- picking the same
     * plan again there would just reuse it anyway (see addSubscriptionPackage()),
     * so there's nothing for staff to actually decide. Multiple active
     * subscriptions is left alone rather than guessing which one they mean.
     * forceWalkIn skips this entirely -- staff explicitly chose Walk-in
     * Order over Use Subscription, so nothing should auto-enter for them.
     */
    protected function maybeEnterExistingSubscription(): void
    {
        if ($this->forceWalkIn) {
            return;
        }

        if ($this->selectedCustomer?->customer_type !== 'subscription') {
            return;
        }

        $subscriptions = Subscription::where('customer_id', $this->customerId)
            ->where('status', 'active')
            ->get();

        if ($subscriptions->count() !== 1) {
            return;
        }

        $collection = $subscriptions->first()->collections()->where('status', 'scheduled')->orderBy('scheduled_date')->first();

        if ($collection) {
            $this->collectionId = $collection->id;
        }
    }

    /**
     * Also available (and fully functional, not just visible) mid-subscription
     * -- picking the wrong customer/plan shouldn't strand the cashier, so this
     * unwinds the whole in-progress collection: cart, active line, and the
     * collection lock itself, back to a blank slate.
     */
    public function clearCustomer(): void
    {
        $this->customerId = null;
        $this->usingPendingCustomer = false;
        $this->newCustomerName = '';
        $this->newCustomerPhone = '';
        $this->newCustomerType = 'walk_in';
        $this->collectionId = null;
        $this->cart = [];
        $this->subscriptionClothes = [];
        $this->activePackageLineIndex = null;
        $this->selectedPackageId = '';
        $this->newCustomerNonScheduled = false;
        $this->customAmount = null;
        $this->paymentTiming = 'pay_now';
    }

    /**
     * Runs right after a successful submit, in place of the old redirect --
     * everything gets wiped back to a blank slate immediately (same as
     * clearCustomer() plus the order-level fields it doesn't touch), so the
     * Terminal underneath the View Order / New Order modal is already ready
     * for the next transaction the moment it appears. forceWalkIn is
     * deliberately left alone -- that's a whole-session choice, not
     * per-order state.
     */
    protected function resetForNewOrder(Order $order): void
    {
        $this->clearCustomer();
        $this->customerSearch = '';
        $this->discount = 0;
        $this->discountReason = '';
        $this->walkInExtraCharge = 0;
        $this->walkInExtraChargeReason = '';
        $this->cycleOverageCharge = 0;
        $this->creditToApply = 0;
        $this->paymentMethod = 'cash';
        $this->notes = '';
        $this->lastSubmittedOrderId = $order->id;
    }

    /**
     * A walk-in customer's row is deliberately NOT created here -- only
     * staged (see $usingPendingCustomer) and actually written inside
     * submitOrder()'s own transaction, right alongside the order itself, so
     * a failed/abandoned order never leaves an orphaned customer behind.
     * This is Terminal-specific: CustomerController::store() (the Customers
     * page's own "+ New Customer") is a fully independent, standalone path
     * and is untouched by this -- adding a customer there was never
     * conditional on an order.
     *
     * Subscription-type customers are the one exception and still create
     * eagerly: picking a plan (addSubscriptionPackage()) creates a real
     * Subscription -- and schedules its first cycle/collection -- the
     * moment a package is chosen, well before any order/collection
     * submission exists to defer into. Deferring that too would mean
     * deferring subscription creation itself, a much larger change than
     * asked for here.
     */
    public function createCustomer(): void
    {
        if ($this->isSubscriptionMode) {
            return;
        }

        $this->validate([
            'newCustomerName' => ['required', 'string', 'max:255'],
            'newCustomerPhone' => ['required', 'string', 'regex:/^[+0-9][0-9 ()\-]{6,19}$/', 'unique:customers,phone'],
            'newCustomerType' => ['required', 'in:walk_in,subscription'],
        ], [], ['newCustomerName' => 'name', 'newCustomerPhone' => 'phone', 'newCustomerType' => 'customer type']);

        if ($this->newCustomerType === 'subscription') {
            $customer = DB::transaction(fn () => Customer::create([
                'full_name' => $this->newCustomerName,
                'phone' => $this->newCustomerPhone,
                'customer_type' => 'subscription',
            ]));

            $this->customerId = $customer->id;
            $this->newCustomerName = '';
            $this->newCustomerPhone = '';
            $this->newCustomerType = 'walk_in';
            $this->showNewCustomerForm = false;

            return;
        }

        $this->usingPendingCustomer = true;
        $this->showNewCustomerForm = false;
    }

    public function addPackage(): void
    {
        if ($this->selectedPackageId === '') {
            return;
        }

        // Deliberately reads collectionId directly rather than the memoized
        // choosingSubscriptionPackage/isSubscriptionMode computeds -- Livewire
        // caches a computed's result for the rest of the request the moment
        // it's first read, and addSubscriptionPackage() below is exactly what
        // changes collectionId, so touching those computeds first would lock
        // in a stale "not in subscription mode yet" for the render that follows.
        if (! $this->forceWalkIn && $this->collectionId === null && $this->selectedCustomer?->customer_type === 'subscription') {
            $this->addSubscriptionPackage();

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
                $this->activePackageLineIndex = $index;

                return;
            }
        }

        $this->cart[] = [
            'laundry_package_id' => $package->id,
            'name' => $package->name,
            'price' => $this->isSubscriptionMode ? 0.0 : (float) $package->base_price,
            'quantity' => 1,
            'clothes_allowed' => $package->clothes_allowed,
            'clothes' => [],
        ];

        $this->selectedPackageId = '';
        $this->activePackageLineIndex = array_key_last($this->cart);
    }

    public function removePackage(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
        $this->activePackageLineIndex = empty($this->cart) ? null : min($this->activePackageLineIndex ?? 0, count($this->cart) - 1);
    }

    public function clearCart(): void
    {
        if ($this->isSubscriptionMode) {
            $this->subscriptionClothes = [];

            return;
        }

        $this->cart = [];
        $this->activePackageLineIndex = null;
    }

    /**
     * Reverses the earlier "subscriptions only ever start from their own
     * dedicated flow" rule for the Terminal specifically: picking a plan
     * here starts (or reuses) a real Subscription and its first Collection,
     * then drops straight into the exact same collection-locked mode the
     * Collections-page "Collect" action already uses -- nothing downstream
     * (pricing, allowance, extra charge, submit) needed to change.
     */
    protected function addSubscriptionPackage(): void
    {
        if (! $this->selectedCustomer) {
            return;
        }

        $package = SubscriptionPackage::find($this->selectedPackageId);

        if (! $package) {
            return;
        }

        $subscription = Subscription::where('customer_id', $this->selectedCustomer->id)
            ->where('subscription_package_id', $package->id)
            ->where('status', 'active')
            ->first();

        if (! $subscription) {
            $maxPackages = (int) Setting::get('subscription.max_active_packages_per_customer', '1');
            $activeCount = Subscription::where('customer_id', $this->selectedCustomer->id)->where('status', 'active')->count();

            if ($maxPackages > 0 && $activeCount >= $maxPackages) {
                $this->addError('cart', "This customer already has the maximum of {$maxPackages} active package(s).");

                return;
            }

            $subscription = Subscription::create([
                'customer_id' => $this->selectedCustomer->id,
                'subscription_package_id' => $package->id,
                'status' => 'active',
                'start_date' => now()->toDateString(),
                // This quick-start path has no form to override the collection
                // count/max clothes on, so those just take the package's own
                // defaults -- staff wanting those customized should use the
                // dedicated New Subscription form instead. Collection type is
                // the one exception: it's captured on the New Customer modal
                // (newCustomerNonScheduled), since it can't be changed later.
                'collections_per_month' => $package->collections_per_month,
                'collection_type' => $this->newCustomerNonScheduled ? 'non_scheduled' : 'scheduled',
                'max_clothes_per_cycle' => $package->max_clothes_per_cycle,
            ]);

            $this->newCustomerNonScheduled = false;

            CollectionScheduler::scheduleFirstCycle($subscription);
        }

        $collection = $subscription->collections()->where('status', 'scheduled')->orderBy('scheduled_date')->first();

        if (! $collection) {
            $this->addError('cart', 'This subscription has no scheduled collection to record against.');

            return;
        }

        $this->collectionId = $collection->id;
        $this->selectedPackageId = '';
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

        if (! $item) {
            return;
        }

        if ($this->isSubscriptionMode) {
            foreach ($this->subscriptionClothes as $ci => $clothesLine) {
                if ($clothesLine['clothing_item_id'] === $item->id) {
                    $this->subscriptionClothes[$ci]['quantity']++;

                    return;
                }
            }

            $this->subscriptionClothes[] = [
                'clothing_item_id' => $item->id,
                'name' => $item->name,
                'quantity' => 1,
            ];

            return;
        }

        if (! isset($this->cart[$packageIndex])) {
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

    public function incrementClothingItem(int $packageIndex, int $clothesIndex): void
    {
        if (! isset($this->cart[$packageIndex]['clothes'][$clothesIndex])) {
            return;
        }

        $this->cart[$packageIndex]['clothes'][$clothesIndex]['quantity']++;
    }

    /**
     * Decrementing to 0 removes the line entirely -- a clothes item, unlike
     * a package line, has no reason to stay pinned at a forced minimum of 1.
     */
    public function decrementClothingItem(int $packageIndex, int $clothesIndex): void
    {
        if (! isset($this->cart[$packageIndex]['clothes'][$clothesIndex])) {
            return;
        }

        $this->setClothingItemQuantity($packageIndex, $clothesIndex, $this->cart[$packageIndex]['clothes'][$clothesIndex]['quantity'] - 1);
    }

    public function setClothingItemQuantity(int $packageIndex, int $clothesIndex, int $quantity): void
    {
        if (! isset($this->cart[$packageIndex]['clothes'][$clothesIndex])) {
            return;
        }

        if ($quantity <= 0) {
            $this->removeClothingItem($packageIndex, $clothesIndex);

            return;
        }

        $this->cart[$packageIndex]['clothes'][$clothesIndex]['quantity'] = $quantity;
    }

    public function removeClothingItem(int $packageIndex, int $clothesIndex): void
    {
        unset($this->cart[$packageIndex]['clothes'][$clothesIndex]);
        $this->cart[$packageIndex]['clothes'] = array_values($this->cart[$packageIndex]['clothes']);
    }

    public function incrementSubscriptionClothesLine(int $index): void
    {
        if (! isset($this->subscriptionClothes[$index])) {
            return;
        }

        $this->subscriptionClothes[$index]['quantity']++;
    }

    public function decrementSubscriptionClothesLine(int $index): void
    {
        if (! isset($this->subscriptionClothes[$index])) {
            return;
        }

        $this->setSubscriptionClothesQuantity($index, $this->subscriptionClothes[$index]['quantity'] - 1);
    }

    public function setSubscriptionClothesQuantity(int $index, int $quantity): void
    {
        if (! isset($this->subscriptionClothes[$index])) {
            return;
        }

        if ($quantity <= 0) {
            $this->removeSubscriptionClothesLine($index);

            return;
        }

        $this->subscriptionClothes[$index]['quantity'] = $quantity;
    }

    public function removeSubscriptionClothesLine(int $index): void
    {
        unset($this->subscriptionClothes[$index]);
        $this->subscriptionClothes = array_values($this->subscriptionClothes);
    }

    public function submitOrder()
    {
        if ($this->isSubscriptionMode) {
            return $this->submitSubscriptionCollection();
        }

        $this->sanitizeCart();

        $maxDiscountPercent = (float) Setting::get('order.max_discount_percent', '100');

        $this->validate([
            'customerId' => $this->usingPendingCustomer ? ['nullable'] : ['required', 'exists:customers,id'],
            // Re-validated here, not just trusted from createCustomer()'s
            // earlier pass -- phone uniqueness in particular could have
            // changed in the meantime (another order creating the same
            // number), and this is the actual point of commitment.
            'newCustomerName' => [$this->usingPendingCustomer ? 'required' : 'nullable', 'string', 'max:255'],
            'newCustomerPhone' => [$this->usingPendingCustomer ? 'required' : 'nullable', 'string', 'regex:/^[+0-9][0-9 ()\-]{6,19}$/', 'unique:customers,phone'],
            'discountReason' => [$this->discount > 0 ? 'required' : 'nullable', 'string', 'max:255'],
            'discount' => [
                'numeric', 'min:0',
                function ($attribute, $value, $fail) use ($maxDiscountPercent) {
                    if ($this->subtotal > 0 && ($value / $this->subtotal * 100) > $maxDiscountPercent) {
                        $fail("Discount cannot exceed {$maxDiscountPercent}% of the subtotal.");
                    }
                },
            ],
            'walkInExtraChargeReason' => [$this->walkInExtraChargeEnabled && $this->walkInExtraCharge > 0 ? 'required' : 'nullable', 'string', 'max:255'],
            'walkInExtraCharge' => ['numeric', 'min:0'],
            'creditToApply' => ['numeric', 'min:0', 'max:'.$this->maxApplicableCredit],
            'customAmount' => $this->paymentTiming === 'pay_now' && $this->remainingDue > 0
                ? ['required', 'numeric', 'min:0.01', 'max:'.$this->remainingDue]
                : ['nullable', 'numeric', 'min:0'],
            'paymentMethod' => [$this->amountToCollect > 0 ? 'required' : 'nullable', 'in:cash,card,mixed'],
        ], [
            'customerId.required' => 'Select or add a customer first.',
            'newCustomerName.required' => 'Enter a name for the new customer.',
            'newCustomerPhone.required' => 'Enter a phone number for the new customer.',
            'discountReason.required' => 'A discount needs a reason.',
            'walkInExtraChargeReason.required' => 'An extra charge needs a reason.',
            'creditToApply.max' => 'Cannot apply more than the available store credit (or the order total).',
            'customAmount.required' => 'Enter an amount to collect, or switch to Pay on Pickup.',
            'customAmount.min' => 'Amount must be greater than 0.',
            'customAmount.max' => 'Cannot collect more than the remaining due.',
        ]);

        if (empty($this->cart)) {
            $this->addError('cart', 'Add at least one package before creating the order.');

            return;
        }

        // Setting can be toggled off between an amount being typed and
        // submit -- ignored rather than trusted whenever it's disabled, same
        // as discount already is via discountEnabled not gating anything
        // server-side either (its own cap is what's actually enforced).
        $chargesWalkInExtra = $this->walkInExtraChargeEnabled && $this->walkInExtraCharge > 0;

        try {
            [$order, $payments, $customerId] = DB::transaction(function () use ($chargesWalkInExtra) {
                // The whole point of deferring creation in createCustomer():
                // a pending walk-in customer only ever actually lands in the
                // customers table here, inside the same transaction as the
                // order itself -- if anything below throws, this rolls back
                // right along with it, same as it would for any other
                // MySQL/QueryException failure in this transaction.
                $customerId = $this->usingPendingCustomer
                    ? Customer::create([
                        'full_name' => $this->newCustomerName,
                        'phone' => $this->newCustomerPhone,
                        'customer_type' => 'walk_in',
                    ])->id
                    : $this->customerId;

                $order = Order::create([
                    'order_number' => Numbering::nextOrderNumber(),
                    'customer_id' => $customerId,
                    'user_id' => auth()->id(),
                    'order_source' => 'walk_in',
                    'subtotal' => $this->subtotal,
                    'discount' => $this->discount,
                    'discount_reason' => $this->discount > 0 ? $this->discountReason : null,
                    'extra_charge' => $chargesWalkInExtra ? $this->walkInExtraCharge : 0,
                    'extra_charge_reason' => $chargesWalkInExtra ? $this->walkInExtraChargeReason : null,
                    'notes' => trim($this->notes) !== '' ? trim($this->notes) : 'None',
                ]);
                $order->refresh();

                $this->createLineItems($order);
                $payments = $this->applyCreditAndPayment($order);

                $order->receipt()->create([
                    'receipt_number' => Numbering::nextReceiptNumber(),
                    'reprint_count' => 0,
                ]);

                return [$order, $payments, $customerId];
            });
        } catch (QueryException $e) {
            $this->addError('cart', $this->friendlyDatabaseError($e));

            return;
        }

        $this->customerId = $customerId;
        $this->usingPendingCustomer = false;

        // Broadcast only after the transaction has actually committed --
        // broadcasting from inside it risks announcing data that a later
        // failure in the same transaction would roll back.
        OrderStatusChanged::dispatch($order, null);
        foreach ($payments as $payment) {
            PaymentReceived::dispatch($payment);
        }

        $this->resetForNewOrder($order);
    }

    protected function submitSubscriptionCollection()
    {
        $collection = $this->collection;

        if (! $collection || $collection->status !== 'scheduled') {
            $this->addError('cart', 'This collection is no longer available.');

            return;
        }

        $this->sanitizeSubscriptionClothes();

        $chargesForCycleOverage = $this->chargeForCycleOverage && $this->cycleOverageCount > 0;

        $this->validate([
            // cycleOverageCharge is a float defaulting to 0.0, never null --
            // 'nullable' wouldn't actually skip 'min:0.01' against that
            // default, so the two rule sets are kept fully separate instead.
            'cycleOverageCharge' => $chargesForCycleOverage ? ['required', 'numeric', 'min:0.01'] : ['numeric', 'min:0'],
            'creditToApply' => ['numeric', 'min:0', 'max:'.$this->maxApplicableCredit],
            'customAmount' => $this->paymentTiming === 'pay_now' && $this->remainingDue > 0
                ? ['required', 'numeric', 'min:0.01', 'max:'.$this->remainingDue]
                : ['nullable', 'numeric', 'min:0'],
            'paymentMethod' => [$this->amountToCollect > 0 ? 'required' : 'nullable', 'in:cash,card,mixed'],
        ], [
            'cycleOverageCharge.required' => 'This cycle is over its max clothes limit — enter the overage charge.',
            'creditToApply.max' => 'Cannot apply more than the available store credit (or the order total).',
            'customAmount.required' => 'Enter an amount to collect, or switch to Pay on Pickup.',
            'customAmount.min' => 'Amount must be greater than 0.',
            'customAmount.max' => 'Cannot collect more than the remaining due.',
        ]);

        if (empty($this->subscriptionClothes)) {
            $this->addError('cart', 'Record at least one clothing item collected.');

            return;
        }

        if (! LaundryPackage::where('is_active', true)->exists()) {
            $this->addError('cart', 'No active laundry package exists to record this collection against.');

            return;
        }

        try {
            [$order, $payments] = DB::transaction(function () use ($collection, $chargesForCycleOverage) {
                // A cycle's flat monthly price belongs to the cycle itself
                // (payments.subscription_id + subscription_cycle_id), not to
                // any one collection's order -- every collection in the
                // cycle draws against the same shared balance. The order
                // created here only ever carries this visit's own
                // cycle_overage_charge, if any (the only chargeable
                // over-the-limit event -- see cycleOverageCharge's doc
                // comment). A legacy collection predating cycles
                // (subscriptionCycle null) falls back to the old behavior:
                // the price goes directly on its own order, since there's no
                // cycle to attach a cycle-level payment to.
                $cycle = $collection->subscriptionCycle;

                $order = Order::create([
                    'order_number' => Numbering::nextOrderNumber(),
                    'customer_id' => $collection->subscription->customer_id,
                    'collection_id' => $collection->id,
                    'user_id' => auth()->id(),
                    'order_source' => 'subscription',
                    'subtotal' => $cycle ? 0 : $this->subtotal,
                    'discount' => 0,
                    'extra_charge' => 0,
                    'cycle_overage_charge' => $chargesForCycleOverage ? $this->cycleOverageCharge : 0,
                    'notes' => trim($this->notes) !== '' ? trim($this->notes) : 'None',
                ]);
                $order->refresh();

                $this->createLineItems($order);
                $payments = $cycle
                    ? $this->applyCreditAndPayment($cycle, $order)
                    : $this->applyCreditAndPayment($order);

                $order->receipt()->create([
                    'receipt_number' => Numbering::nextReceiptNumber(),
                    'reprint_count' => 0,
                ]);

                $collection->update(['status' => 'collected', 'collected_at' => now()]);
                $cycle?->closeIfExhausted();

                return [$order, $payments];
            });
        } catch (QueryException $e) {
            $this->addError('cart', $this->friendlyDatabaseError($e));

            return;
        }

        OrderStatusChanged::dispatch($order, null);
        CollectionStatusChanged::dispatch($collection);
        foreach ($payments as $payment) {
            PaymentReceived::dispatch($payment);
        }

        $this->resetForNewOrder($order);
    }

    /**
     * $cart is an ordinary public Livewire property, so the client can push
     * an arbitrary syncInput update to any of its values (price, quantity,
     * even the package id) that bypasses addPackage()/incrementPackage()
     * entirely. Called before subtotal is first read in submitOrder() --
     * once read, Livewire's #[Computed] caches it for the rest of the
     * request, so this has to run first or the tampered value sticks.
     * Re-derives name/price from the real package row and drops any line
     * whose package no longer exists; quantity is clamped, never trusted.
     */
    protected function sanitizeCart(): void
    {
        $packages = LaundryPackage::whereIn('id', collect($this->cart)->pluck('laundry_package_id'))
            ->get()
            ->keyBy('id');

        $this->cart = collect($this->cart)
            ->filter(fn ($line) => $packages->has($line['laundry_package_id']))
            ->map(function ($line) use ($packages) {
                $package = $packages[$line['laundry_package_id']];

                $line['name'] = $package->name;
                $line['price'] = (float) $package->base_price;
                // Lower bound guards against 0/negative; the upper bound is
                // a sanity cap, not a business rule -- no real walk-in visit
                // needs 100+ of the same package line, so anything past that
                // is tampering (or a stuck click), not a legitimate order.
                $line['quantity'] = min(100, max(1, (int) $line['quantity']));

                return $line;
            })
            ->values()
            ->all();
    }

    /**
     * Same tampering exposure as $cart (see sanitizeCart() above), and the
     * stakes are arguably higher here: quantity feeds cycleOverageCount,
     * which decides whether a charge is even required at all -- a tampered
     * negative quantity could zero out an overage that's really there and
     * skip the charge entirely, not just misprice a line. Called before
     * cycleOverageCount is first read in submitSubscriptionCollection().
     * Clothes lines snapshot at price 0 regardless (the cycle's flat price
     * is what's actually charged), so name/id are re-derived for data
     * integrity, not because a tampered price could be smuggled through here.
     */
    protected function sanitizeSubscriptionClothes(): void
    {
        $items = ClothingItem::whereIn('id', collect($this->subscriptionClothes)->pluck('clothing_item_id'))
            ->get()
            ->keyBy('id');

        $this->subscriptionClothes = collect($this->subscriptionClothes)
            ->filter(fn ($line) => $items->has($line['clothing_item_id']))
            ->map(function ($line) use ($items) {
                $line['name'] = $items[$line['clothing_item_id']]->name;
                $line['quantity'] = min(100, max(1, (int) $line['quantity']));

                return $line;
            })
            ->values()
            ->all();
    }

    /**
     * Marks a clothes line as "extra" once the running total for the whole
     * cart passes the subscription's allowance -- line-level granularity,
     * not per-unit, to keep the allocation simple and deterministic.
     */
    protected function createLineItems(Order $order): void
    {
        if ($this->isSubscriptionMode) {
            $this->createSubscriptionLineItems($order);

            return;
        }

        foreach ($this->cart as $line) {
            $packageLine = $order->packageLines()->create([
                'laundry_package_id' => $line['laundry_package_id'],
                'package_name_snapshot' => $line['name'],
                'package_price_snapshot' => $line['price'],
                'quantity' => $line['quantity'],
            ]);

            foreach ($line['clothes'] as $clothesLine) {
                $packageLine->clothesLines()->create([
                    'clothing_item_id' => $clothesLine['clothing_item_id'],
                    'item_name_snapshot' => $clothesLine['name'],
                    'item_price_snapshot' => 0,
                    'quantity' => $clothesLine['quantity'],
                    'is_extra' => false,
                ]);
            }
        }
    }

    /**
     * Subscription clothes never went through a real package pick (see
     * $subscriptionClothes) -- a single container line is created here,
     * invisibly, purely to satisfy order_package_lines.laundry_package_id.
     * Its price always snapshots to 0; the subscription fee is what's
     * actually being charged, via the cycle.
     */
    protected function createSubscriptionLineItems(Order $order): void
    {
        // Guaranteed to exist -- submitSubscriptionCollection() checks
        // before opening the transaction this runs inside.
        $container = LaundryPackage::where('is_active', true)->orderBy('name')->first();

        $packageLine = $order->packageLines()->create([
            'laundry_package_id' => $container->id,
            'package_name_snapshot' => $container->name,
            'package_price_snapshot' => 0,
            'quantity' => 1,
        ]);

        $cumulative = 0;

        foreach ($this->subscriptionClothes as $clothesLine) {
            $cumulative += $clothesLine['quantity'];
            $isExtra = $cumulative > $this->allowance;

            $packageLine->clothesLines()->create([
                'clothing_item_id' => $clothesLine['clothing_item_id'],
                'item_name_snapshot' => $clothesLine['name'],
                'item_price_snapshot' => 0,
                'quantity' => $clothesLine['quantity'],
                'is_extra' => $isExtra,
            ]);
        }
    }

    /**
     * Applies store credit (§2.5: "Apply credit_applied portion, collect the
     * remaining cash/card amount") then records payment, across one or more
     * targets in priority order -- e.g. a subscription cycle's shared flat
     * price first, then the current visit's own order for any
     * over-allowance extra charge left over once the cycle's price is
     * settled. Walk-in orders just pass their single order through. Each
     * target's own balanceDue() is what's drawn against, since a cycle may
     * already carry payments from an earlier collection in it.
     * trg_credit_transactions_overdraft_guard and trg_payments_cap_guard are
     * what actually enforce correctness per target; creditToApply/
     * amountToCollect being pre-validated against the combined total is a UX
     * nicety, not the real guarantee against double-spend or overpaying any
     * single target.
     *
     * @return list<\App\Models\Payment>
     */
    protected function applyCreditAndPayment(Order|SubscriptionCycle ...$targets): array
    {
        $creditRemaining = Setting::get('payment.store_credit_enabled', 'true') === 'true'
            ? $this->creditToApply
            : 0;
        $cashRemaining = $this->amountToCollect;
        $payments = [];

        foreach ($targets as $target) {
            $due = $target->balanceDue();

            if ($due <= 0) {
                continue;
            }

            $creditApplied = min($creditRemaining, $due);
            $cashApplied = min($cashRemaining, $due - $creditApplied);
            $paymentAmount = round($creditApplied + $cashApplied, 2);

            $customer = $target instanceof Order ? $target->customer : $target->subscription->customer;

            if ($creditApplied > 0) {
                $customer->creditTransactions()->create([
                    'type' => 'debit',
                    'amount' => $creditApplied,
                    'reference_type' => $target instanceof Order ? 'order' : 'subscription_cycle',
                    'reference_id' => $target->id,
                    'created_by' => auth()->id(),
                ]);
            }

            if ($paymentAmount > 0) {
                $attributes = [
                    'amount' => $paymentAmount,
                    'credit_applied' => $creditApplied,
                    'method' => $creditApplied >= $paymentAmount ? 'store_credit' : $this->paymentMethod,
                    'received_by' => auth()->id(),
                ];

                $payments[] = $target instanceof Order
                    ? $target->payments()->create($attributes)
                    : $target->payments()->create($attributes + ['subscription_id' => $target->subscription_id]);
            }

            $creditRemaining = round($creditRemaining - $creditApplied, 2);
            $cashRemaining = round($cashRemaining - $cashApplied, 2);
        }

        return $payments;
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
            str_contains($message, 'Payment would exceed cycle total') => 'This payment would exceed the subscription cycle total — it may have just been paid elsewhere. Refresh and try again.',
            str_contains($message, 'Insufficient store credit balance') => 'Insufficient store credit balance — it may have just been redeemed elsewhere. Refresh and try again.',
            default => 'Something went wrong saving this order. No charge was made.',
        };
    }
};
?>

<div wire:key="terminal-root">

    @if ($showNewCustomerForm)
        <div class="fixed inset-0 bg-ink/40 backdrop-blur-sm z-40 flex items-center justify-center p-4" wire:click.self="$set('showNewCustomerForm', false)" x-transition.opacity>
            <div class="bg-surface border border-line rounded-2xl shadow-xl w-full max-w-md p-6" wire:click.stop x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-ink">New Customer</h3>
                    <button type="button" wire:click="$set('showNewCustomerForm', false)" class="text-ink-faint hover:text-ink transition-colors" aria-label="Close">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <input type="text" wire:model="newCustomerName" placeholder="Full name" class="w-full bg-surface border-line-strong rounded-lg shadow-sm text-sm transition-shadow focus:shadow-md">
                        @error('newCustomerName') <p class="text-critical text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <input type="text" wire:model="newCustomerPhone" placeholder="+220 555 1234" class="w-full bg-surface border-line-strong rounded-lg shadow-sm text-sm transition-shadow focus:shadow-md">
                        @error('newCustomerPhone') <p class="text-critical text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <div class="flex gap-3">
                            <label class="flex-1 inline-flex items-center justify-center gap-1.5 text-sm text-ink border border-line-strong rounded-lg py-2 cursor-pointer has-[:checked]:border-accent has-[:checked]:bg-accent-soft has-[:checked]:text-accent-ink transition-colors">
                                <input type="radio" wire:model.live="newCustomerType" value="walk_in" class="text-accent focus:ring-accent">
                                Walk-in
                            </label>
                            <label class="flex-1 inline-flex items-center justify-center gap-1.5 text-sm text-ink border border-line-strong rounded-lg py-2 cursor-pointer has-[:checked]:border-accent has-[:checked]:bg-accent-soft has-[:checked]:text-accent-ink transition-colors">
                                <input type="radio" wire:model.live="newCustomerType" value="subscription" class="text-accent focus:ring-accent">
                                Subscription
                            </label>
                        </div>
                        @error('newCustomerType') <p class="text-critical text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    @if ($newCustomerType === 'subscription')
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" wire:model="newCustomerNonScheduled" class="rounded border-line-strong text-accent focus:ring-accent">
                            Non-scheduled collections
                        </label>
                        <p class="text-xs text-ink-faint -mt-2">Customer can come in any day, instead of fixed pickup dates. Leave unchecked for Scheduled.</p>
                    @endif

                    <button type="button" wire:click="createCustomer" class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-accent rounded-lg text-white text-sm font-semibold hover:opacity-90 transition-opacity">Add &amp; select</button>
                </div>
            </div>
        </div>
    @endif

    @if ($this->lastSubmittedOrder)
        <div class="fixed inset-0 bg-ink/40 backdrop-blur-sm z-40 flex items-center justify-center p-4" x-transition.opacity>
            <div class="bg-surface border border-line rounded-2xl shadow-xl w-full max-w-sm p-6 text-center" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                <div class="w-12 h-12 rounded-full bg-success-soft text-success flex items-center justify-center mx-auto mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h3 class="font-semibold text-ink text-lg mb-1">{{ $this->lastSubmittedOrder->order_source === 'subscription' ? 'Collection Recorded' : 'Order Created' }}</h3>
                <p class="text-sm text-ink-muted mb-5">{{ $this->lastSubmittedOrder->order_number }} · GMD {{ number_format($this->lastSubmittedOrder->total_amount, 2) }}</p>
                <div class="space-y-2">
                    <a href="{{ route('orders.show', $this->lastSubmittedOrder) }}" class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-accent rounded-lg text-white text-sm font-semibold hover:opacity-90 transition-opacity">
                        View Order
                    </a>
                    <button type="button" wire:click="$set('lastSubmittedOrderId', null)" class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-surface-2 rounded-lg text-ink text-sm font-semibold hover:bg-line transition-colors">
                        New Order
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <div class="lg:col-span-2 space-y-5">

            <div class="bg-surface border border-line rounded-2xl shadow-sm p-5">
                <div class="mb-3">
                    @if ($this->selectedCustomer)
                        <div class="flex items-center justify-between gap-2 p-2.5 rounded-lg bg-accent-soft">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-8 h-8 rounded-full bg-accent text-white flex items-center justify-center text-xs font-bold flex-none">
                                    {{ Str::substr($this->selectedCustomer->full_name, 0, 1) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="font-semibold text-accent-ink text-sm truncate">{{ $this->selectedCustomer->full_name }}</div>
                                    <div class="text-xs text-ink-muted font-mono">{{ $this->selectedCustomer->phone }}</div>
                                </div>
                            </div>
                            <button type="button" wire:click="clearCustomer" class="inline-flex items-center gap-1.5 bg-surface border border-line-strong text-ink-muted hover:text-critical hover:border-critical text-xs font-semibold px-3 py-1.5 rounded-lg flex-none transition-colors">
                                <x-nav-icon name="arrow-right" class="w-3 h-3 rotate-180" />
                                Change
                            </button>
                        </div>
                    @elseif (! $this->isSubscriptionMode)
                        <div class="flex items-start gap-3">
                            <div class="flex-1 relative">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-faint pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5A6.5 6.5 0 114 10.5a6.5 6.5 0 0113 0z" />
                                </svg>
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="customerSearch"
                                    placeholder="Search customers by name or phone…"
                                    class="w-full pl-9 bg-surface border-line-strong text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent rounded-lg shadow-sm text-sm transition-shadow"
                                >
                                @error('customerId') <p class="text-critical text-xs mt-1">{{ $message }}</p> @enderror

                                @if ($this->customerResults->isNotEmpty())
                                    <div class="border border-line rounded-lg divide-y divide-line mt-2 max-h-48 overflow-y-auto shadow-sm">
                                        @foreach ($this->customerResults as $result)
                                            <button type="button" wire:click="selectCustomer({{ $result->id }})" class="w-full text-left px-3 py-2 text-sm hover:bg-surface-2 transition-colors">
                                                <div class="text-ink">{{ $result->full_name }}</div>
                                                <div class="text-ink-faint text-xs font-mono">{{ $result->phone }}</div>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <button type="button" wire:click="$set('showNewCustomerForm', true)" class="flex-none px-4 py-2 bg-accent-soft text-accent-ink rounded-lg text-sm font-semibold whitespace-nowrap hover:bg-accent hover:text-white transition-colors">
                                + New Customer
                            </button>
                        </div>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="$set('activeCategoryFilter', null)" class="px-3 py-1 rounded-full text-xs font-semibold transition-colors {{ $activeCategoryFilter === null ? 'bg-accent text-white' : 'bg-surface-2 text-ink-muted hover:text-ink' }}">
                        All
                    </button>
                    @foreach ($this->clothesCategories as $category)
                        <button type="button" wire:click="$set('activeCategoryFilter', {{ $category->id }})" class="px-3 py-1 rounded-full text-xs font-semibold transition-colors {{ $activeCategoryFilter === $category->id ? 'bg-accent text-white' : 'bg-surface-2 text-ink-muted hover:text-ink' }}">
                            {{ $category->name }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="bg-surface border border-line rounded-2xl shadow-sm p-5">
                @if ($this->isSubscriptionMode)
                    <div class="flex items-center justify-between mb-3">
                        <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold">Clothes</div>
                        <span class="text-xs font-medium text-accent-ink bg-accent-soft px-2.5 py-1 rounded-full">Recording this collection</span>
                    </div>
                @elseif (count($cart) > 1)
                    <div class="flex items-center justify-between mb-3">
                        <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold">Clothes</div>
                        <select wire:model.live="activePackageLineIndex" class="bg-surface border-line-strong text-ink rounded-lg shadow-sm text-xs focus:border-accent focus:ring-accent">
                            @foreach ($cart as $i => $line)
                                <option value="{{ $i }}">Adding to: {{ $line['name'] }} #{{ $i + 1 }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif (count($cart) === 1)
                    <div class="flex items-center justify-between mb-3">
                        <div class="font-mono text-xs uppercase tracking-wide text-ink font-bold">Clothes</div>
                        <span class="text-xs font-medium text-accent-ink bg-accent-soft px-2.5 py-1 rounded-full">Adding to: {{ $cart[0]['name'] }}</span>
                    </div>
                @endif

                <div class="relative">
                    @if (! $this->isSubscriptionMode && empty($cart))
                        <div class="absolute inset-0 z-10 bg-surface/80 backdrop-blur-sm rounded-xl flex flex-col items-center justify-center text-center px-6">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 mx-auto mb-3 text-ink-faint opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6l1 3h3a1 1 0 011 1v2a4 4 0 01-4 4h-.5M9 3H6a1 1 0 00-1 1v2a4 4 0 004 4h.5M9 3l-1 3m7-3l1 3m-8 0h8m-8 0a4 4 0 004 4 4 4 0 004-4M8 21h8a1 1 0 001-1v-6.5a4.5 4.5 0 00-9 0V20a1 1 0 001 1z" />
                            </svg>
                            <p class="text-sm font-semibold text-ink">Select a package to add clothes.</p>
                        </div>
                    @endif

                    @if ($this->filteredClothingItems->isEmpty())
                        <div class="text-center py-20 text-ink-faint">
                            <p class="text-sm">No clothes match your search.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-6 sm:grid-cols-8 xl:grid-cols-10 gap-1.5 max-h-[32rem] overflow-y-auto pr-1">
                            @foreach ($this->filteredClothingItems as $item)
                                @php
                                    $qtyInCart = $this->isSubscriptionMode
                                        ? collect($subscriptionClothes)->firstWhere('clothing_item_id', $item->id)['quantity'] ?? 0
                                        : ($activePackageLineIndex !== null
                                            ? collect($cart[$activePackageLineIndex]['clothes'] ?? [])->firstWhere('clothing_item_id', $item->id)['quantity'] ?? 0
                                            : 0);
                                @endphp
                                <button
                                    type="button"
                                    wire:click="addClothingItem({{ $activePackageLineIndex ?? 0 }}, {{ $item->id }})"
                                    class="relative bg-surface-2 border rounded-lg p-1.5 text-center transition-all duration-150 hover:-translate-y-0.5 hover:shadow-md {{ $qtyInCart > 0 ? 'border-accent ring-1 ring-accent' : 'border-line hover:border-accent hover:bg-accent-soft' }}"
                                >
                                    @if ($qtyInCart > 0)
                                        <span class="absolute -top-1 -right-1 min-w-[1rem] h-4 px-0.5 rounded-full bg-accent text-white text-[8px] font-bold font-mono flex items-center justify-center shadow">{{ $qtyInCart }}</span>
                                    @endif
                                    @if ($item->image_path)
                                        <img src="{{ Storage::url($item->image_path) }}" alt="" class="w-full aspect-square object-cover rounded mb-1">
                                    @else
                                        <div class="w-full aspect-square rounded bg-surface flex items-center justify-center mb-1 text-ink-faint text-xs font-semibold">
                                            {{ Str::substr($item->name, 0, 1) }}
                                        </div>
                                    @endif
                                    <div class="text-[9px] text-ink truncate leading-tight">{{ $item->name }}</div>
                                    <div class="text-[8px] text-ink-faint truncate leading-tight">{{ $item->category->name ?? '—' }}</div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-surface border border-line rounded-2xl shadow-sm p-5 flex flex-col">
            <div class="flex items-center justify-between mb-4">
                <div class="text-lg font-bold text-ink">Cart</div>
                @if (! empty($cart) || ! empty($subscriptionClothes))
                    <button type="button" wire:click="clearCart" class="text-xs font-semibold text-critical hover:underline">Clear Cart</button>
                @endif
            </div>

            <div class="max-h-[14rem] overflow-y-auto pr-1 -mr-1">
                @if ($this->isSubscriptionMode)
                    @forelse ($subscriptionClothes as $ci => $clothesLine)
                        <div class="flex items-center justify-between border border-line rounded-xl p-2.5 mb-2 shadow-sm hover:border-line-strong transition-colors" wire:key="sub-clothes-{{ $ci }}">
                            <span class="font-medium text-ink text-sm truncate">{{ $clothesLine['name'] }}</span>
                            <div class="flex items-center gap-1.5 flex-none">
                                <button type="button" wire:click="decrementSubscriptionClothesLine({{ $ci }})" class="w-6 h-6 rounded-full bg-surface-2 text-ink-muted hover:bg-line transition-colors">−</button>
                                <input type="number" min="0" wire:change="setSubscriptionClothesQuantity({{ $ci }}, $event.target.value)" value="{{ $clothesLine['quantity'] }}" class="w-12 text-center font-mono text-sm bg-surface border-line-strong rounded-lg shadow-sm py-1">
                                <button type="button" wire:click="incrementSubscriptionClothesLine({{ $ci }})" class="w-6 h-6 rounded-full bg-surface-2 text-ink-muted hover:bg-line transition-colors">+</button>
                                <button type="button" wire:click="removeSubscriptionClothesLine({{ $ci }})" class="text-ink-faint hover:text-critical transition-colors ml-1" title="Remove">
                                    <x-nav-icon name="trash" class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-ink-faint text-sm">No clothes recorded yet.</div>
                    @endforelse
                @else
                    @forelse ($cart as $index => $line)
                        <div class="border border-line rounded-xl p-3 mb-3 shadow-sm hover:border-line-strong transition-colors" wire:key="pkg-{{ $index }}">
                            <div class="flex items-center justify-between mb-2">
                                <div class="font-semibold text-ink text-sm">{{ $line['name'] }}</div>
                                <button type="button" wire:click="removePackage({{ $index }})" class="text-critical text-xs hover:underline">Remove</button>
                            </div>
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <button type="button" wire:click="decrementPackage({{ $index }})" class="w-6 h-6 rounded-full bg-surface-2 text-ink-muted hover:bg-line transition-colors">−</button>
                                    <span class="font-mono text-sm w-6 text-center tabular-nums">{{ $line['quantity'] }}</span>
                                    <button type="button" wire:click="incrementPackage({{ $index }})" class="w-6 h-6 rounded-full bg-surface-2 text-ink-muted hover:bg-line transition-colors">+</button>
                                </div>
                                <span class="font-mono text-xs tabular-nums text-ink-muted">GMD {{ number_format($line['price'] * $line['quantity'], 2) }}</span>
                            </div>
                            <div class="space-y-1.5">
                                @forelse ($line['clothes'] as $ci => $clothesLine)
                                    <div class="flex items-center justify-between bg-surface-2 rounded-lg px-2 py-1" wire:key="clothes-{{ $index }}-{{ $ci }}">
                                        <span class="font-mono text-xs text-ink truncate">{{ $clothesLine['name'] }}</span>
                                        <div class="flex items-center gap-1.5 flex-none">
                                            <button type="button" wire:click="decrementClothingItem({{ $index }}, {{ $ci }})" class="w-5 h-5 rounded-full bg-surface text-ink-muted hover:bg-line transition-colors text-xs leading-none">−</button>
                                            <input type="number" min="0" wire:change="setClothingItemQuantity({{ $index }}, {{ $ci }}, $event.target.value)" value="{{ $clothesLine['quantity'] }}" class="w-10 text-center font-mono text-xs bg-surface border-line-strong rounded shadow-sm py-0.5">
                                            <button type="button" wire:click="incrementClothingItem({{ $index }}, {{ $ci }})" class="w-5 h-5 rounded-full bg-surface text-ink-muted hover:bg-line transition-colors text-xs leading-none">+</button>
                                            <button type="button" wire:click="removeClothingItem({{ $index }}, {{ $ci }})" class="text-ink-faint hover:text-critical transition-colors ml-1" title="Remove">
                                                <x-nav-icon name="trash" class="w-3 h-3" />
                                            </button>
                                        </div>
                                    </div>
                                @empty
                                    <span class="text-ink-faint text-xs">No clothes added yet.</span>
                                @endforelse
                            </div>
                            @php $lineClothesCount = collect($line['clothes'])->sum('quantity'); @endphp
                            @if (($line['clothes_allowed'] ?? null) !== null)
                                <div class="text-xs font-mono mt-2 {{ $lineClothesCount > $line['clothes_allowed'] ? 'text-critical font-semibold' : 'text-ink-faint' }}">
                                    {{ $lineClothesCount }} / {{ $line['clothes_allowed'] }} items
                                    @if ($lineClothesCount > $line['clothes_allowed'])
                                        (+{{ $lineClothesCount - $line['clothes_allowed'] }} over)
                                    @endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-6 text-ink-faint text-sm">Cart is empty.</div>
                    @endforelse
                @endif
            </div>

        <div class="space-y-4">
            @if ($this->isSubscriptionMode)
                <div class="p-3 rounded-xl bg-surface-2 text-sm flex flex-wrap gap-x-6 gap-y-1.5">
                    <div class="flex gap-2"><span class="text-ink-muted">Plan</span><span class="text-ink font-medium">{{ $this->collection?->subscription?->subscriptionPackage?->name }}</span></div>
                    <div class="flex gap-2">
                        <span class="text-ink-muted">Allowance</span>
                        <span class="font-mono tabular-nums {{ $this->overAllowanceCount > 0 ? 'text-critical font-semibold' : 'text-ink' }}">
                            {{ $this->clothesCount }} / {{ $this->allowance }}
                            @if ($this->overAllowanceCount > 0) (+{{ $this->overAllowanceCount }} over) @endif
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <span class="text-ink-muted">Cycle max clothes</span>
                        <span class="font-mono tabular-nums {{ $this->cycleOverageCount > 0 ? 'text-critical font-semibold' : 'text-ink' }}">
                            {{ $this->cycleClothesCollectedSoFar + $this->clothesCount }} / {{ $this->cycleMaxClothes }}
                            @if ($this->cycleOverageCount > 0) (+{{ $this->cycleOverageCount }} over) @endif
                        </span>
                    </div>
                    @if ($this->collection && $this->collection->absorbedCollections->isNotEmpty())
                        <div class="flex gap-2">
                            <span class="text-ink-muted">Combined visit</span>
                            <span class="font-mono text-accent-ink">
                                covers {{ $this->collection->absorbedCollections->count() + 1 }} slots (cancelled: {{ $this->collection->absorbedCollections->pluck('scheduled_date')->map(fn ($d) => $d?->format('M d') ?? 'anytime')->implode(', ') }})
                            </span>
                        </div>
                    @endif
                    <div class="flex gap-2">
                        <span class="text-ink-muted">This cycle</span>
                        @if ($this->cyclePaymentCompleted)
                            <span class="font-mono text-success font-semibold">Payment completed</span>
                        @else
                            <span class="font-mono tabular-nums text-critical font-semibold">GMD {{ number_format($this->cycleBalanceDue, 2) }} due</span>
                        @endif
                    </div>
                </div>
            @endif

            @unless ($this->isSubscriptionMode)
                @if ($this->selectedCustomer)
                    <div>
                        <label class="text-xs font-mono uppercase tracking-wide text-ink-faint mb-1.5 block">{{ $this->choosingSubscriptionPackage ? 'Plan' : 'Package' }}</label>
                        <div class="flex gap-2">
                            <select wire:model="selectedPackageId" class="flex-1 min-w-0 bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent transition-shadow">
                                <option value="">{{ $this->choosingSubscriptionPackage ? 'Select a plan…' : 'Select a package…' }}</option>
                                @foreach ($this->packages as $package)
                                    <option value="{{ $package->id }}">
                                        {{ $package->name }}
                                        @if ($this->choosingSubscriptionPackage)
                                            — GMD {{ number_format($package->monthly_price, 2) }}/mo, {{ $package->clothes_allowance }} items
                                        @else
                                            — GMD {{ number_format($package->base_price, 2) }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="addPackage" class="px-4 py-2 bg-accent text-white rounded-lg text-sm font-semibold whitespace-nowrap hover:opacity-90 transition-opacity">
                                {{ $this->choosingSubscriptionPackage ? 'Start Subscription' : 'Add Package' }}
                            </button>
                        </div>
                    </div>
                @else
                    <p class="text-ink-faint text-sm">Select a customer to choose a package.</p>
                @endif
            @endunless
            @error('cart') <p class="text-critical text-xs">{{ $message }}</p> @enderror
        </div>

        <div class="border-t border-line mt-4 pt-4 space-y-3">
            <div>
                <label class="text-xs font-mono uppercase tracking-wide text-ink-faint mb-1.5 block">Notes</label>
                <textarea wire:model="notes" rows="2" placeholder="e.g. No bleach" class="w-full bg-surface border-line-strong text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent rounded-lg shadow-sm text-sm"></textarea>
                <p class="text-xs text-ink-faint mt-1">Leave blank for "None".</p>
            </div>

            @if ($this->isSubscriptionMode)
                @if ($this->cyclePaymentCompleted)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-ink-muted">Monthly fee</span>
                        <span class="font-mono text-success font-semibold">Payment completed</span>
                    </div>
                @else
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-ink-muted">Monthly fee due</span>
                        <span class="font-mono tabular-nums text-ink">GMD {{ number_format($this->subtotal, 2) }}</span>
                    </div>
                @endif

                @if ($this->overAllowanceCount > 0)
                    <p class="text-sm text-ink-muted">{{ $this->overAllowanceCount }} item(s) over the per-visit allowance this visit — not chargeable on its own, only the cycle's max clothes limit below is.</p>
                @else
                    <p class="text-sm text-success">Within the per-visit allowance.</p>
                @endif

                @if ($this->chargeForCycleOverage && $this->cycleOverageCount > 0)
                    <div>
                        <label class="text-xs font-mono uppercase tracking-wide text-critical font-bold mb-1.5 block">Cycle overage charge</label>
                        <input type="number" step="0.01" min="0" wire:model.live="cycleOverageCharge" class="w-full bg-surface border-critical/40 rounded-lg shadow-sm text-sm font-mono">
                        <p class="text-xs text-ink-faint mt-1">for {{ $this->cycleOverageCount }} item(s) over this cycle's max clothes limit</p>
                    </div>
                    @error('cycleOverageCharge') <p class="text-critical text-xs">{{ $message }}</p> @enderror
                @elseif ($this->cycleOverageCount > 0)
                    <p class="text-sm text-ink-muted">{{ $this->cycleOverageCount }} item(s) over this cycle's max clothes limit — no charge (disabled in Settings).</p>
                @else
                    <p class="text-sm text-success">Within this cycle's max clothes limit.</p>
                @endif
            @else
                <div class="flex items-center justify-between text-sm">
                    <span class="text-ink-muted">Subtotal</span>
                    <span class="font-mono tabular-nums text-ink">GMD {{ number_format($this->subtotal, 2) }}</span>
                </div>

                @if ($this->discountEnabled)
                    <div>
                        <label class="text-xs font-mono uppercase tracking-wide text-ink-faint mb-1.5 block">Discount</label>
                        <div class="flex gap-2">
                            <input type="number" step="0.01" min="0" wire:model.live="discount" class="w-24 bg-surface border-line-strong rounded-lg shadow-sm text-sm font-mono">
                            <input type="text" wire:model="discountReason" placeholder="Reason (required if discount > 0)" class="flex-1 bg-surface border-line-strong rounded-lg shadow-sm text-sm">
                        </div>
                    </div>
                    @error('discountReason') <p class="text-critical text-xs">{{ $message }}</p> @enderror
                @endif

                @if ($this->walkInExtraChargeEnabled)
                    <div>
                        <label class="text-xs font-mono uppercase tracking-wide text-ink-faint mb-1.5 block">Extra charge</label>
                        <div class="flex gap-2">
                            <input type="number" step="0.01" min="0" wire:model.live="walkInExtraCharge" class="w-24 bg-surface border-line-strong rounded-lg shadow-sm text-sm font-mono">
                            <input type="text" wire:model="walkInExtraChargeReason" placeholder="Reason (required if extra charge > 0)" class="flex-1 bg-surface border-line-strong rounded-lg shadow-sm text-sm">
                        </div>
                    </div>
                    @error('walkInExtraChargeReason') <p class="text-critical text-xs">{{ $message }}</p> @enderror
                @endif
            @endif

            @if ($this->total > 0 && $this->availableCredit > 0)
                <div>
                    <label class="text-xs font-mono uppercase tracking-wide text-ink-faint mb-1.5 block">Store credit</label>
                    <input type="number" step="0.01" min="0" max="{{ $this->maxApplicableCredit }}" wire:model.live="creditToApply" class="w-full bg-surface border-line-strong rounded-lg shadow-sm text-sm font-mono">
                    <p class="text-xs text-ink-faint mt-1">of GMD {{ number_format($this->availableCredit, 2) }} available</p>
                </div>
                @error('creditToApply') <p class="text-critical text-xs">{{ $message }}</p> @enderror
            @endif

            @if ($this->remainingDue > 0)
                <div>
                    <div class="flex gap-3">
                        <label class="flex-1 inline-flex items-center justify-center gap-1.5 text-sm text-ink border border-line-strong rounded-lg py-2 cursor-pointer has-[:checked]:border-accent has-[:checked]:bg-accent-soft has-[:checked]:text-accent-ink transition-colors">
                            <input type="radio" wire:model.live="paymentTiming" value="pay_now" class="text-accent focus:ring-accent">
                            Pay Now
                        </label>
                        <label class="flex-1 inline-flex items-center justify-center gap-1.5 text-sm text-ink border border-line-strong rounded-lg py-2 cursor-pointer has-[:checked]:border-accent has-[:checked]:bg-accent-soft has-[:checked]:text-accent-ink transition-colors">
                            <input type="radio" wire:model.live="paymentTiming" value="pay_on_pickup" class="text-accent focus:ring-accent">
                            Pay on Pickup
                        </label>
                    </div>
                </div>

                @if ($paymentTiming === 'pay_now')
                    <div>
                        <label class="text-xs font-mono uppercase tracking-wide text-ink-faint mb-1.5 block">Amount</label>
                        <input type="number" step="0.01" min="0" max="{{ $this->remainingDue }}" wire:model.live="customAmount" placeholder="0.00" class="w-full bg-surface border-line-strong rounded-lg shadow-sm text-sm font-mono">
                        <p class="text-xs text-ink-faint mt-1">of GMD {{ number_format($this->remainingDue, 2) }} due — enter less than the full amount to collect only part now, rest is collected later from Order Detail</p>
                    </div>
                    @error('customAmount') <p class="text-critical text-xs">{{ $message }}</p> @enderror

                    @if ($this->amountToCollect > 0)
                        <div>
                            <label class="text-xs font-mono uppercase tracking-wide text-ink-faint mb-1.5 block">Payment Method</label>
                            <select wire:model="paymentMethod" class="w-full bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="mixed">Mixed</option>
                            </select>
                        </div>
                    @endif
                @else
                    <p class="text-sm text-ink-muted">Full balance of GMD {{ number_format($this->remainingDue, 2) }} will be due on pickup.</p>
                @endif
            @endif

            @if ($this->creditToApply > 0)
                <div class="flex items-center justify-between text-sm">
                    <span class="text-ink-muted">Store credit applied</span>
                    <span class="font-mono tabular-nums text-accent-ink">− GMD {{ number_format(min($this->creditToApply, $this->total), 2) }}</span>
                </div>
            @endif

            <div class="flex items-center justify-between pt-3 border-t border-line">
                <span class="font-semibold text-ink">{{ $this->creditToApply > 0 ? 'Remaining due' : 'Total' }}</span>
                <span class="font-mono text-xl font-bold tabular-nums text-accent-ink">GMD {{ number_format($this->creditToApply > 0 ? $this->remainingDue : $this->total, 2) }}</span>
            </div>

            <button type="button" wire:click="submitOrder" wire:loading.attr="disabled" class="w-full inline-flex items-center justify-center px-5 py-3.5 bg-accent rounded-xl text-white font-semibold text-sm shadow-sm hover:opacity-90 hover:shadow-md active:scale-[0.99] transition-all disabled:opacity-60">
                <span wire:loading.remove wire:target="submitOrder">{{ $this->isSubscriptionMode ? 'Record Collection' : 'Create Order' }}</span>
                <span wire:loading wire:target="submitOrder" class="inline-flex items-center gap-2">
                    <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Saving…
                </span>
            </button>
        </div>
        </div>
    </div>
</div>
