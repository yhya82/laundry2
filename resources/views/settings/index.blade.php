<x-app-layout>
    <x-slot name="header">Settings</x-slot>

    <div x-data="{ tab: 'general' }">
        <div class="flex gap-1 border-b border-line overflow-x-auto mb-6">
            @foreach (['general' => 'General', 'laundry' => 'Laundry', 'subscription' => 'Subscription', 'payment' => 'Payment', 'notification' => 'Notification', 'order' => 'Order', 'backup' => 'Backup'] as $key => $label)
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
                    @if ($settings->get('branding.logo_path'))
                        <img src="{{ Storage::url($settings->get('branding.logo_path')->value) }}" alt="" class="w-12 h-12 rounded object-cover mb-2">
                    @endif
                    <input id="logo" name="logo" type="file" accept="image/*" class="block w-full text-sm text-ink-muted file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-accent-soft file:text-accent-ink file:text-xs file:font-semibold">
                    <x-input-error :messages="$errors->get('logo')" class="mt-1.5" />
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
                <div>
                    <x-input-label for="max_concurrent_washing" value="Max orders washing at once" />
                    <x-text-input id="max_concurrent_washing" name="max_concurrent_washing" type="number" min="1" max="1000" class="block w-full" value="{{ $settings->get('laundry.max_concurrent_washing')?->value }}" />
                    <p class="text-xs text-ink-faint mt-1">Blocks moving an order to Washing once this many orders are already washing, to avoid mixing clothes. Leave blank for no limit.</p>
                    <x-input-error :messages="$errors->get('max_concurrent_washing')" class="mt-1.5" />
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
                <p class="text-xs text-ink-faint">When off, creating a new subscription is blocked with a message -- existing subscriptions keep running normally.</p>

                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" name="charge_for_extra_clothes" value="1" class="rounded border-line-strong text-accent focus:ring-accent" @checked($settings->get('subscription.charge_for_extra_clothes')?->value !== 'false')>
                    Charge for clothes over the plan allowance
                </label>
                <p class="text-xs text-ink-faint">When off, the Terminal still shows over-allowance items but never requires or applies an extra charge for them.</p>

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
                <p class="text-xs text-ink-faint -mt-2">When off, the Discount field is hidden from the cart entirely -- staff can't apply one.</p>
                <div>
                    <x-input-label for="max_discount_percent" value="Maximum discount (% of subtotal)" />
                    <x-text-input id="max_discount_percent" name="max_discount_percent" type="number" min="0" max="100" class="block w-full" value="{{ $settings->get('order.max_discount_percent')?->value ?? 100 }}" required />
                    <p class="text-xs text-ink-faint mt-1">Enforced at the Terminal -- a discount above this is rejected before the order is created.</p>
                    <x-input-error :messages="$errors->get('max_discount_percent')" class="mt-1.5" />
                </div>
                <x-primary-button>Save Order settings</x-primary-button>
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
                    <p class="text-xs text-ink-faint mt-1">Stored for the backup automation introduced in a later phase -- not yet enforced by a running job.</p>
                    <x-input-error :messages="$errors->get('retention_days')" class="mt-1.5" />
                </div>
                <x-primary-button>Save Backup settings</x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>
