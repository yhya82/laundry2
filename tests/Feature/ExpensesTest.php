<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpensesTest extends TestCase
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

    public function test_an_expense_can_be_recorded(): void
    {
        $category = ExpenseCategory::factory()->create();

        $response = $this->post(route('expenses.store'), [
            'expense_category_id' => $category->id,
            'description' => 'Detergent restock',
            'amount' => 45.50,
            'expense_date' => now()->toDateString(),
        ]);
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('expenses', [
            'description' => 'Detergent restock',
            'amount' => 45.50,
        ]);
    }

    public function test_a_negative_or_zero_amount_is_rejected(): void
    {
        $category = ExpenseCategory::factory()->create();

        $response = $this->post(route('expenses.store'), [
            'expense_category_id' => $category->id,
            'description' => 'Bad expense',
            'amount' => 0,
            'expense_date' => now()->toDateString(),
        ]);
        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseMissing('expenses', ['description' => 'Bad expense']);
    }

    public function test_an_expense_can_be_updated(): void
    {
        $category = ExpenseCategory::factory()->create();
        $expense = Expense::create([
            'expense_category_id' => $category->id,
            'description' => 'Original',
            'amount' => 20,
            'expense_date' => now()->toDateString(),
            'recorded_by' => $this->app['auth']->id(),
        ]);

        $response = $this->put(route('expenses.update', $expense), [
            'expense_category_id' => $category->id,
            'description' => 'Updated',
            'amount' => 25,
            'expense_date' => now()->toDateString(),
        ]);
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'description' => 'Updated', 'amount' => 25]);
    }

    public function test_deleting_an_expense_soft_deletes_it_and_it_disappears_from_the_index(): void
    {
        $category = ExpenseCategory::factory()->create();
        $expense = Expense::create([
            'expense_category_id' => $category->id,
            'description' => 'To be removed',
            'amount' => 15,
            'expense_date' => now()->toDateString(),
            'recorded_by' => $this->app['auth']->id(),
        ]);

        $response = $this->delete(route('expenses.destroy', $expense));
        $response->assertSessionDoesntHaveErrors();

        // Soft delete: the row is preserved (deleted_at set), not gone from the table.
        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);

        $response = $this->get(route('expenses.index'));
        $response->assertDontSee('To be removed');
    }

    public function test_expense_category_names_must_be_unique(): void
    {
        ExpenseCategory::factory()->create(['name' => 'Utilities']);

        $response = $this->post(route('expenses.categories.store'), ['name' => 'Utilities']);
        $response->assertSessionHasErrors('name');
        $this->assertSame(1, ExpenseCategory::where('name', 'Utilities')->count());
    }
}
