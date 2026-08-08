<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DamageType;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
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

    protected function makeOrderWithPayment(float $amount, ?\DateTimeInterface $paidAt = null): Order
    {
        $customer = Customer::factory()->create();
        $order = Order::create([
            'order_number' => 'RPT-'.uniqid(),
            'customer_id' => $customer->id,
            'order_source' => 'walk_in',
            'subtotal' => $amount,
        ]);
        $order->refresh();
        $payment = $order->payments()->create(['amount' => $amount, 'method' => 'cash']);
        if ($paidAt) {
            $payment->timestamps = false;
            $payment->created_at = $paidAt;
            $payment->save();
        }

        return $order;
    }

    public function test_revenue_export_contains_only_payments_within_range_with_correct_totals(): void
    {
        $this->makeOrderWithPayment(100, now());
        $this->makeOrderWithPayment(50, now()->subDays(90)); // outside the default "month" range

        $response = $this->get(route('reports.export.revenue', ['period' => 'month']));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('100.00', $csv);
        $this->assertStringNotContainsString('50.00', $csv, 'A payment from 90 days ago should not appear in a "this month" export.');
    }

    public function test_revenue_export_excludes_refunded_payments(): void
    {
        $order = $this->makeOrderWithPayment(100);
        $payment = $order->payments()->first();
        // status isn't mass-assignable (see the security review) -- it only
        // ever moves via trg_refunds_apply_payment_status, triggered by a
        // real refund covering the full amount.
        \App\Models\Refund::create(['payment_id' => $payment->id, 'amount' => 100, 'refunded_by' => $this->app['auth']->id()]);
        $this->assertSame('refunded', $payment->fresh()->status);

        $response = $this->get(route('reports.export.revenue', ['period' => 'month']));
        $csv = $response->streamedContent();

        $this->assertStringNotContainsString('100.00', $csv, 'A fully refunded payment should not count as revenue.');
    }

    public function test_damage_export_reflects_report_and_resolution(): void
    {
        $order = $this->makeOrderWithPayment(100);
        $damageType = DamageType::factory()->create(['name' => 'Torn Fabric']);
        $damage = $order->damageRecords()->create([
            'damage_type_id' => $damageType->id,
            'item_description' => 'Sleeve torn',
            'status' => 'approved',
            'reported_by' => $this->app['auth']->id(),
        ]);
        $damage->resolution()->create([
            'resolution_type' => 'cash',
            'amount' => 20,
            'resolved_by' => $this->app['auth']->id(),
        ]);

        $response = $this->get(route('reports.export.damage', ['period' => 'month']));
        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Torn Fabric', $csv);
        $this->assertStringContainsString('Sleeve torn', $csv);
        $this->assertStringContainsString('cash', $csv);
        $this->assertStringContainsString('20.00', $csv);
    }

    public function test_expenses_export_and_totals_exclude_soft_deleted_expenses(): void
    {
        $category = ExpenseCategory::factory()->create(['name' => 'Utilities']);
        Expense::create([
            'expense_category_id' => $category->id,
            'description' => 'Live expense',
            'amount' => 30,
            'expense_date' => now(),
            'recorded_by' => $this->app['auth']->id(),
        ]);
        $deleted = Expense::create([
            'expense_category_id' => $category->id,
            'description' => 'Deleted expense',
            'amount' => 999,
            'expense_date' => now(),
            'recorded_by' => $this->app['auth']->id(),
        ]);
        $deleted->delete();

        $response = $this->get(route('reports.export.expenses', ['period' => 'month']));
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Live expense', $csv);
        $this->assertStringNotContainsString('Deleted expense', $csv, 'A soft-deleted expense should not appear in the export.');

        $indexResponse = $this->get(route('reports.index', ['period' => 'month']));
        $indexResponse->assertOk();
        // Totals grouped by category should reflect only the live expense (30), not 999 + 30.
        $indexResponse->assertViewHas('expensesTotal', 30.0);
    }

    public function test_an_explicit_date_range_overrides_the_period_shortcut(): void
    {
        $this->makeOrderWithPayment(75, now()->subDays(5));

        $response = $this->get(route('reports.export.revenue', [
            'from' => now()->subDays(10)->toDateString(),
            'to' => now()->toDateString(),
        ]));
        $csv = $response->streamedContent();

        $this->assertStringContainsString('75.00', $csv);
    }
}
