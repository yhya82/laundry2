<x-app-layout>
    <x-slot name="header">Settings</x-slot>

    <div x-data="{ tab: 'general' }">
        <div class="flex gap-1 border-b border-line overflow-x-auto mb-6">
            @foreach (['general' => 'General', 'laundry' => 'Laundry', 'subscription' => 'Subscription', 'payment' => 'Payment', 'notification' => 'Notification', 'order' => 'Order', 'receipt' => 'Receipt', 'backup' => 'Backup'] as $key => $label)
                <button
                    type="button"
                    @click="tab = '{{ $key }}'"
                    class="px-4 py-2.5 text-sm font-semibold whitespace-nowrap border-b-2"
                    :class="tab === '{{ $key }}' ? 'text-accent-ink border-accent' : 'text-ink-faint border-transparent hover:text-ink'"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div x-show="tab === 'general'" class="bg-surface border border-line rounded-2xl p-6 max-w-xl">
            <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="group" value="general">
                <div>
                    <x-input-label for="business_name" value="Business name" />
                    <x-text-input id="business_name" name="business_name" type="text" class="block w-full" value="{{ $settings->get('branding.business_name')?->value ?? config('app.name') }}" required />
                    <x-input-error :messages="$errors->get('business_name')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="logo" value="Logo (optional)" />
                    @if ($brandingLogoUrl = \App\Support\MediaUrl::temporary($settings->get('branding.logo_path')?->value))
                        <img src="{{ $brandingLogoUrl }}" alt="" class="w-12 h-12 rounded object-cover mb-2">
                    @endif
                    <input id="logo" name="logo" type="file" accept="image/*" class="block w-full text-sm text-ink-muted file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-accent-soft file:text-accent-ink file:text-xs file:font-semibold">
                    <x-input-error :messages="$errors->get('logo')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="phone" value="Phone (optional)" />
                    <x-text-input id="phone" name="phone" type="text" class="block w-full" value="{{ $settings->get('branding.phone')?->value }}" />
                    <x-input-error :messages="$errors->get('phone')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="email" value="Email (optional)" />
                    <x-text-input id="email" name="email" type="email" class="block w-full" value="{{ $settings->get('branding.email')?->value }}" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="address" value="Address (optional)" />
                    <x-text-input id="address" name="address" type="text" class="block w-full" value="{{ $settings->get('branding.address')?->value }}" />
                    <p class="text-xs text-ink-faint mt-1">Phone, email, and address show on printed receipts alongside the business name and logo.</p>
                    <x-input-error :messages="$errors->get('address')" class="mt-1.5" />
                </div>
                <x-primary-button>Save General settings</x-primary-button>
            </form>
        </div>

        <div x-show="tab === 'laundry'" x-cloak class="bg-surface border border-line rounded-2xl p-6 max-w-xl">
            <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="group" value="laundry">
                <div>
                    <x-input-label for="default_turnaround_hours" value="Default turnaround (hours)" />
                    <x-text-input id="default_turnaround_hours" name="default_turnaround_hours" type="number" min="1" max="720" class="block w-full" value="{{ $settings->get('laundry.default_turnaround_hours')?->value }}" />
                    <p class="text-xs text-ink-faint mt-1">Shown as an estimated ready time on active orders. Leave blank to hide it.</p>
                    <x-input-error :messages="$errors->get('default_turnaround_hours')" class="mt-1.5" />
                </div>
                <x-primary-button>Save Laundry settings</x-primary-button>
            </form>
        </div>

        <div x-show="tab === 'subscription'" x-cloak class="bg-surface border border-line rounded-2xl p-6 max-w-xl">
            <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="group" value="subscription">
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" name="allow_new_signups" value="1" class="rounded border-line-strong text-accent focus:ring-accent" @checked($settings->get('subscription.allow_new_signups')?->value !== 'false')>
                    Allow new subscription sign-ups
                </label>
                <p class="text-xs text-ink-faint">When off, creating a new subscription is blocked with a message — existing subscriptions keep running normally.</p>

                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" name="charge_for_cycle_overage" value="1" class="rounded border-line-strong text-accent focus:ring-accent" @checked($settings->get('subscription.charge_for_cycle_overage')?->value === 'true')>
                    Charge when a cycle exceeds its max clothes limit
                </label>
                <p class="text-xs text-ink-faint">The Terminal always shows a per-visit item count for context, but only this cycle-wide limit is ever billable. When off, the Terminal just shows a warning with the amount over instead of requiring a charge.</p>

                <div>
                    <x-input-label for="max_active_packages_per_customer" value="Max active packages per customer" />
                    <x-text-input id="max_active_packages_per_customer" name="max_active_packages_per_customer" type="number" min="1" class="block w-full" value="{{ $settings->get('subscription.max_active_packages_per_customer')?->value ?? 1 }}" />
                    <p class="text-xs text-ink-faint mt-1">Blocks creating a new subscription for a customer once they're at this many active ones. Leave blank for unlimited. Doesn't affect anyone already over it.</p>
                    <x-input-error :messages="$errors->get('max_active_packages_per_customer')" class="mt-1.5" />
                </div>

                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" name="walkin_extra_charge_enabled" value="1" class="rounded border-line-strong text-accent focus:ring-accent" @checked($settings->get('subscription.walkin_extra_charge_enabled')?->value === 'true')>
                    Allow an extra charge on walk-in orders
                </label>
                <p class="text-xs text-ink-faint">Separate from the cycle overage charge above, and only for walk-in (non-subscription) orders at the Terminal -- e.g. rush service or special handling not covered by the package price. When off, the Terminal has no way to add one.</p>

                <x-primary-button>Save Subscription settings</x-primary-button>
            </form>
        </div>

        <div x-show="tab === 'payment'" x-cloak class="bg-surface border border-line rounded-2xl p-6 max-w-xl">
            <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="group" value="payment">
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" name="store_credit_enabled" value="1" class="rounded border-line-strong text-accent focus:ring-accent" @checked($settings->get('payment.store_credit_enabled')?->value !== 'false')>
                    Allow store credit to be applied at the Terminal
                </label>
                <p class="text-xs text-ink-faint">When off, the Terminal ignores any credit amount entered and charges the full total.</p>
                <x-primary-button>Save Payment settings</x-primary-button>
            </form>
        </div>

        <div x-show="tab === 'notification'" x-cloak class="bg-surface border border-line rounded-2xl p-6 max-w-xl space-y-4">
            <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="group" value="notification">
                @foreach (['sms_enabled' => 'SMS', 'whatsapp_enabled' => 'WhatsApp', 'email_enabled' => 'Email'] as $field => $label)
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" name="{{ $field }}" value="1" class="rounded border-line-strong text-accent focus:ring-accent" @checked($settings->get("notification.{$field}")?->value !== 'false')>
                        {{ $label }} notifications
                    </label>
                @endforeach
                <p class="text-xs text-ink-faint">Disabled channels still write to the notifications table and log the message; they just skip the actual send.</p>
                <x-primary-button>Save Notification settings</x-primary-button>
            </form>
        </div>

        <div x-show="tab === 'order'" x-cloak class="bg-surface border border-line rounded-2xl p-6 max-w-xl">
            <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="group" value="order">
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="discount_enabled" name="discount_enabled" value="1" class="rounded border-line-strong text-accent focus:ring-accent" @checked($settings->get('order.discount_enabled')?->value === 'true')>
                    <x-input-label for="discount_enabled" value="Allow discounts at the Terminal" class="mb-0" />
                </div>
                <p class="text-xs text-ink-faint -mt-2">When off, the Discount field is hidden from the cart entirely — staff can't apply one.</p>
                <div>
                    <x-input-label for="max_discount_percent" value="Maximum discount (% of subtotal)" />
                    <x-text-input id="max_discount_percent" name="max_discount_percent" type="number" min="0" max="100" class="block w-full" value="{{ $settings->get('order.max_discount_percent')?->value ?? 100 }}" required />
                    <p class="text-xs text-ink-faint mt-1">Enforced at the Terminal — a discount above this is rejected before the order is created.</p>
                    <x-input-error :messages="$errors->get('max_discount_percent')" class="mt-1.5" />
                </div>

                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" name="assignment_enabled" value="1" class="rounded border-line-strong text-accent focus:ring-accent" @checked($settings->get('order.assignment_enabled')?->value === 'true')>
                    Enable order assignment
                </label>
                <p class="text-xs text-ink-faint">Lets staff with the order-assignment permission assign an order to a specific staff member from the Orders list, purely for tracking -- it never restricts who can actually act on an order. When on, everyone without that permission only ever sees orders assigned to them (never unassigned ones); staff with the permission still see and can assign everything.</p>

                <x-primary-button>Save Order settings</x-primary-button>
            </form>
        </div>

        <div x-show="tab === 'receipt'" x-cloak class="bg-surface border border-line rounded-2xl p-6 max-w-xl">
            <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="group" value="receipt">
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" name="show_logo" value="1" class="rounded border-line-strong text-accent focus:ring-accent" @checked($settings->get('receipt.show_logo')?->value === 'true')>
                    Show logo on receipt
                </label>
                <p class="text-xs text-ink-faint">Uses the logo uploaded under General. Has no effect if no logo has been uploaded there. Phone, email, and address are also pulled from General.</p>

                <div>
                    <x-input-label for="footer_message" value="Footer message" />
                    <x-text-input id="footer_message" name="footer_message" type="text" class="block w-full" value="{{ $settings->get('receipt.footer_message')?->value ?? 'Thank you for your business.' }}" />
                    <p class="text-xs text-ink-faint mt-1">Shown at the bottom of every printed receipt.</p>
                    <x-input-error :messages="$errors->get('footer_message')" class="mt-1.5" />
                </div>

                <x-primary-button>Save Receipt settings</x-primary-button>
            </form>
        </div>

        <div x-show="tab === 'backup'" x-cloak class="bg-surface border border-line rounded-2xl p-6 max-w-xl">
            <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="group" value="backup">
                <div>
                    <x-input-label for="retention_days" value="Backup retention (days)" />
                    <x-text-input id="retention_days" name="retention_days" type="number" min="1" max="3650" class="block w-full" value="{{ $settings->get('backup.retention_days')?->value }}" />
                    <p class="text-xs text-ink-faint mt-1">Backups older than this are deleted by <code>backup:cleanup</code> (scheduled daily).</p>
                    <x-input-error :messages="$errors->get('retention_days')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="alert_email" value="Alert email" />
                    <x-text-input id="alert_email" name="alert_email" type="email" class="block w-full" value="{{ $settings->get('backup.alert_email')?->value }}" />
                    <p class="text-xs text-ink-faint mt-1">Where backup failures and health-check warnings are sent. Leave blank to disable email alerts.</p>
                    <x-input-error :messages="$errors->get('alert_email')" class="mt-1.5" />
                </div>
                <x-primary-button>Save Backup settings</x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>
