<?php

use App\Models\ClothingItem;
use App\Models\Customer;
use App\Models\LaundryPackage;
use App\Models\Order;
use App\Support\Numbering;
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

    public string $paymentMethod = 'cash';

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
        return collect($this->cart)->sum(fn ($line) => $line['price'] * $line['quantity']);
    }

    #[Computed]
    public function total(): float
    {
        return max(0, $this->subtotal - $this->discount);
    }

    public function selectCustomer(int $id): void
    {
        $this->customerId = $id;
        $this->customerSearch = '';
    }

    public function clearCustomer(): void
    {
        $this->customerId = null;
    }

    public function createCustomer(): void
    {
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
            'price' => (float) $package->base_price,
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
        $this->validate([
            'customerId' => ['required', 'exists:customers,id'],
            'discountReason' => [$this->discount > 0 ? 'required' : 'nullable', 'string', 'max:255'],
            'paymentMethod' => ['required', 'in:cash,card,mixed'],
        ], [
            'customerId.required' => 'Select or add a customer first.',
            'discountReason.required' => 'A discount needs a reason.',
        ]);

        if (empty($this->cart)) {
            $this->addError('cart', 'Add at least one package before creating the order.');

            return;
        }

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

            $order->payments()->create([
                'amount' => $order->total_amount,
                'credit_applied' => 0,
                'method' => $this->paymentMethod,
                'received_by' => auth()->id(),
            ]);

            $order->receipt()->create([
                'receipt_number' => Numbering::nextReceiptNumber(),
                'reprint_count' => 0,
            ]);

            return $order;
        });

        session()->flash('status', "Order {$order->order_number} created.");

        $this->redirect(route('orders.show', $order), navigate: false);
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
                <button type="button" wire:click="clearCustomer" class="text-xs text-ink-muted hover:text-critical">Change</button>
            </div>
        @else
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

        <div class="mt-6">
            <div class="font-mono text-xs uppercase tracking-wide text-ink-faint mb-3">Package</div>
            <div class="flex gap-2">
                <select wire:model="selectedPackageId" class="flex-1 bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
                    <option value="">Select a package…</option>
                    @foreach ($this->packages as $package)
                        <option value="{{ $package->id }}">{{ $package->name }} — GMD {{ number_format($package->base_price, 2) }}</option>
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
                        <span class="font-mono text-sm tabular-nums text-ink-muted">GMD {{ number_format($line['price'] * $line['quantity'], 2) }}</span>
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
            <div class="text-center py-10 text-ink-faint text-sm">Select a package to start the cart.</div>
        @endforelse

        <div class="border-t border-line mt-4 pt-4 space-y-3">
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

            <div class="flex items-center gap-3">
                <label class="text-sm text-ink-muted w-20">Payment</label>
                <select wire:model="paymentMethod" class="flex-1 bg-surface border-line-strong text-ink rounded-lg shadow-sm text-sm focus:border-accent focus:ring-accent">
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="mixed">Mixed</option>
                </select>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-line">
                <span class="font-semibold text-ink">Total</span>
                <span class="font-mono text-lg font-bold tabular-nums text-accent-ink">GMD {{ number_format($this->total, 2) }}</span>
            </div>

            <button type="button" wire:click="submitOrder" wire:loading.attr="disabled" class="w-full inline-flex items-center justify-center px-5 py-3 bg-accent rounded-lg text-white font-semibold text-sm disabled:opacity-60">
                <span wire:loading.remove wire:target="submitOrder">Create Order</span>
                <span wire:loading wire:target="submitOrder">Creating…</span>
            </button>
        </div>
    </div>
</div>
