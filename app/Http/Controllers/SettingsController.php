<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $settings = Setting::all()->keyBy('key');

        return view('settings.index', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $group = $request->input('group');

        match ($group) {
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

        return back()->with('status', ucfirst($group).' settings saved.');
    }

    protected function saveGeneral(Request $request): void
    {
        $request->merge(['phone' => PhoneNumber::normalize($request->input('phone'))]);

        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'phone' => ['required', 'string', 'regex:/^\+220[0-9]{9}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'phone.regex' => 'Enter a valid 9-digit phone number (e.g. 555123456).',
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
        $validated = $request->validate([
            'max_active_packages_per_customer' => ['nullable', 'integer', 'min:1'],
        ]);

        Setting::set('subscription.allow_new_signups', $request->boolean('allow_new_signups') ? 'true' : 'false', 'subscription', 'boolean');
        Setting::set('subscription.charge_for_cycle_overage', $request->boolean('charge_for_cycle_overage') ? 'true' : 'false', 'subscription', 'boolean');
        Setting::set('subscription.max_active_packages_per_customer', $validated['max_active_packages_per_customer'] ?? null, 'subscription', 'integer');
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
