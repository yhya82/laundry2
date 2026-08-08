<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsAndRolesSeeder::class);
        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);
        $this->actingAs($admin);
    }

    public function test_general_tab_saves_business_identity(): void
    {
        $response = $this->put(route('settings.update'), [
            'group' => 'general',
            'business_name' => 'ABC Laundry',
            'phone' => '+220 700 0000',
            'email' => 'hello@abclaundry.test',
            'address' => 'Serrekunda',
        ]);
        $response->assertSessionDoesntHaveErrors();

        $this->assertSame('ABC Laundry', Setting::get('branding.business_name'));
        $this->assertSame('+220 700 0000', Setting::get('branding.phone'));
        $this->assertSame('hello@abclaundry.test', Setting::get('branding.email'));
        $this->assertSame('Serrekunda', Setting::get('branding.address'));
    }

    public function test_general_tab_requires_a_business_name(): void
    {
        $response = $this->put(route('settings.update'), ['group' => 'general', 'business_name' => '']);
        $response->assertSessionHasErrors('business_name');
    }

    public function test_laundry_tab_saves_turnaround_hours(): void
    {
        $response = $this->put(route('settings.update'), [
            'group' => 'laundry',
            'default_turnaround_hours' => 48,
        ]);
        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('48', Setting::get('laundry.default_turnaround_hours'));
    }

    public function test_subscription_tab_saves_all_four_fields_including_unchecked_booleans(): void
    {
        // Omitting the checkboxes entirely (as an unchecked HTML checkbox
        // does) must save them as false, not leave the previous value.
        Setting::set('subscription.allow_new_signups', 'true', 'subscription', 'boolean');

        $response = $this->put(route('settings.update'), [
            'group' => 'subscription',
            'max_active_packages_per_customer' => 2,
            // allow_new_signups, charge_for_cycle_overage, walkin_extra_charge_enabled all omitted
        ]);
        $response->assertSessionDoesntHaveErrors();

        $this->assertSame('false', Setting::get('subscription.allow_new_signups'));
        $this->assertSame('false', Setting::get('subscription.charge_for_cycle_overage'));
        $this->assertSame('false', Setting::get('subscription.walkin_extra_charge_enabled'));
        $this->assertSame('2', Setting::get('subscription.max_active_packages_per_customer'));
    }

    public function test_payment_tab_toggles_store_credit(): void
    {
        $response = $this->put(route('settings.update'), [
            'group' => 'payment',
            'store_credit_enabled' => '1',
        ]);
        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('true', Setting::get('payment.store_credit_enabled'));
    }

    public function test_notification_tab_saves_each_channel_independently(): void
    {
        $response = $this->put(route('settings.update'), [
            'group' => 'notification',
            'sms_enabled' => '1',
            // whatsapp_enabled omitted -> false
            'email_enabled' => '1',
        ]);
        $response->assertSessionDoesntHaveErrors();

        $this->assertSame('true', Setting::get('notification.sms_enabled'));
        $this->assertSame('false', Setting::get('notification.whatsapp_enabled'));
        $this->assertSame('true', Setting::get('notification.email_enabled'));
    }

    public function test_order_tab_saves_discount_cap_and_assignment_toggle(): void
    {
        $response = $this->put(route('settings.update'), [
            'group' => 'order',
            'max_discount_percent' => 25,
            'discount_enabled' => '1',
            'assignment_enabled' => '1',
        ]);
        $response->assertSessionDoesntHaveErrors();

        $this->assertSame('25', Setting::get('order.max_discount_percent'));
        $this->assertSame('true', Setting::get('order.discount_enabled'));
        $this->assertSame('true', Setting::get('order.assignment_enabled'));
    }

    public function test_order_tab_requires_max_discount_percent(): void
    {
        $response = $this->put(route('settings.update'), ['group' => 'order']);
        $response->assertSessionHasErrors('max_discount_percent');
    }

    public function test_receipt_tab_saves_footer_message_and_logo_toggle(): void
    {
        $response = $this->put(route('settings.update'), [
            'group' => 'receipt',
            'show_logo' => '1',
            'footer_message' => 'Thank you for your business!',
        ]);
        $response->assertSessionDoesntHaveErrors();

        $this->assertSame('true', Setting::get('receipt.show_logo'));
        $this->assertSame('Thank you for your business!', Setting::get('receipt.footer_message'));
    }

    public function test_backup_tab_saves_retention_and_alert_email(): void
    {
        $response = $this->put(route('settings.update'), [
            'group' => 'backup',
            'retention_days' => 14,
            'alert_email' => 'ops@abclaundry.test',
        ]);
        $response->assertSessionDoesntHaveErrors();

        $this->assertSame('14', Setting::get('backup.retention_days'));
        $this->assertSame('ops@abclaundry.test', Setting::get('backup.alert_email'));
    }

    public function test_backup_tab_rejects_an_invalid_alert_email(): void
    {
        $response = $this->put(route('settings.update'), [
            'group' => 'backup',
            'alert_email' => 'not-an-email',
        ]);
        $response->assertSessionHasErrors('alert_email');
    }

    public function test_an_unknown_settings_group_is_rejected(): void
    {
        $response = $this->put(route('settings.update'), ['group' => 'not-a-real-group']);
        $response->assertStatus(422);
    }
}
