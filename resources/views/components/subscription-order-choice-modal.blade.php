@props(['customer', 'cycleState', 'live' => false, 'subscription' => null, 'packages' => null])

<div
    x-data="{ open: {{ $live ? 'true' : 'false' }} }"
    x-on:open-panel.window="$event.detail === 'new-order-choice' && (open = true)"
    x-on:close-panel.window="$event.detail === 'new-order-choice' && (open = false)"
    x-on:keydown.escape.window="open = false"
>
    <div x-show="open" x-cloak class="fixed inset-0 bg-ink/40 backdrop-blur-sm z-40 flex items-center justify-center p-4" x-transition.opacity @click.self="open = false">
        <div class="bg-surface border border-line rounded-2xl shadow-xl w-full max-w-sm p-6" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-ink">Create Order</h3>
                <button type="button" @click="open = false" class="text-ink-faint hover:text-ink" aria-label="Close">✕</button>
            </div>
            <p class="text-sm text-ink-muted mb-4">
                @if ($cycleState === 'exhausted')
                    This customer's current subscription cycle is finished. Renew it, or record a separate walk-in order.
                @else
                    This customer has a subscription cycle running. How should this order be recorded?
                @endif
            </p>
            <div class="space-y-2">
                @if ($cycleState === 'open')
                    @if ($live)
                        <button type="button" wire:click="useExistingSubscription" class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-line-strong text-sm font-medium text-ink hover:border-accent hover:bg-accent-soft transition-colors w-full text-left">
                            <span class="w-8 h-8 rounded-lg bg-success-soft text-success flex items-center justify-center flex-none"><x-nav-icon name="repeat" class="w-4 h-4" /></span>
                            <span class="flex-1">
                                <span class="block">Use Subscription</span>
                                <span class="block text-xs text-ink-faint font-normal">Record against their current plan</span>
                            </span>
                        </button>
                    @else
                        <a href="{{ route('orders.create', ['customer' => $customer->id]) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-line-strong text-sm font-medium text-ink hover:border-accent hover:bg-accent-soft transition-colors">
                            <span class="w-8 h-8 rounded-lg bg-success-soft text-success flex items-center justify-center flex-none"><x-nav-icon name="repeat" class="w-4 h-4" /></span>
                            <span class="flex-1">
                                <span class="block">Use Subscription</span>
                                <span class="block text-xs text-ink-faint font-normal">Record against their current plan</span>
                            </span>
                        </a>
                    @endif
                @else
                    <button type="button" @click="open = false; $dispatch('open-panel', 'renew-cycle-{{ $subscription->id }}')" class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-line-strong text-sm font-medium text-ink hover:border-accent hover:bg-accent-soft transition-colors w-full text-left">
                        <span class="w-8 h-8 rounded-lg bg-success-soft text-success flex items-center justify-center flex-none"><x-nav-icon name="repeat" class="w-4 h-4" /></span>
                        <span class="flex-1">
                            <span class="block">Renew</span>
                            <span class="block text-xs text-ink-faint font-normal">Start a new cycle on their plan</span>
                        </span>
                    </button>
                    <x-renew-cycle-modal :subscription="$subscription" :packages="$packages" />
                @endif

                @if ($live)
                    <button type="button" wire:click="$set('forceWalkIn', true)" class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-line-strong text-sm font-medium text-ink hover:border-accent hover:bg-accent-soft transition-colors w-full text-left">
                        <span class="w-8 h-8 rounded-lg bg-accent-soft text-accent-ink flex items-center justify-center flex-none"><x-nav-icon name="clipboard" class="w-4 h-4" /></span>
                        <span class="flex-1">
                            <span class="block">Walk-in Order</span>
                            <span class="block text-xs text-ink-faint font-normal">A separate one-off order, paid on its own</span>
                        </span>
                    </button>
                @else
                    <a href="{{ route('orders.create', ['customer' => $customer->id, 'mode' => 'walk_in']) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-line-strong text-sm font-medium text-ink hover:border-accent hover:bg-accent-soft transition-colors">
                        <span class="w-8 h-8 rounded-lg bg-accent-soft text-accent-ink flex items-center justify-center flex-none"><x-nav-icon name="clipboard" class="w-4 h-4" /></span>
                        <span class="flex-1">
                            <span class="block">Walk-in Order</span>
                            <span class="block text-xs text-ink-faint font-normal">A separate one-off order, paid on its own</span>
                        </span>
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
