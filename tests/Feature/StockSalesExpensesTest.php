<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockEntry;
use App\Models\User;
use App\Support\StockBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockSalesExpensesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::create(['name' => 'Admin', 'mobile' => '9999999999', 'role' => 'admin', 'salary' => 0, 'password' => 'StrongPass123!']);
        $this->actingAs($admin);

        return $admin;
    }

    private function entry(Product $product, int $qty, string $type = 'manufacture'): StockEntry
    {
        $this->post(route('stock-entries.store'), [
            'entry_date' => '2026-09-27',
            'items' => [['product_id' => $product->id, 'cartons' => $qty, 'type' => $type]],
        ])->assertRedirect(route('stock-entries.index'));

        return StockEntry::latest('id')->firstOrFail();
    }

    private function salePayload(Product $product, int $qty, int $paid = 0): array
    {
        return [
            'party_name' => 'Aqua Traders', 'mobile' => '9876543210', 'sale_date' => '2026-09-27',
            'items' => [['product_id' => $product->id, 'cartons' => $qty, 'rate_rupees' => 100]],
            'discount_rupees' => 0, 'vehicle_charge_rupees' => 0,
            'initial_paid_rupees' => $paid, 'payment_method' => $paid ? 'cash' : '',
            'due_date' => $paid < $qty * 100 ? '2026-10-01' : null,
        ];
    }

    public function test_mixed_stock_types_filter_by_matching_product_and_overview_shows_balance(): void
    {
        $this->admin();
        $jar = Product::create(['name' => '20 Litre Jar', 'status' => 'active']);
        $bottle = Product::create(['name' => 'Bottle', 'status' => 'active']);
        $this->post(route('stock-entries.store'), [
            'entry_date' => '2026-09-27',
            'items' => [
                ['product_id' => $jar->id, 'cartons' => 10, 'type' => 'manufacture'],
                ['product_id' => $bottle->id, 'cartons' => 5, 'type' => 'purchase'],
            ],
        ])->assertRedirect();
        $entry = StockEntry::firstOrFail();
        $this->assertNull($entry->type);
        $this->assertSame(['manufacture', 'purchase'], $entry->items()->orderBy('id')->pluck('type')->all());
        $this->get(route('stock-entries.show', $entry))->assertOk()->assertSeeText('Purchase')->assertSeeText('Manufacture');

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('stock-entries.index', ['type' => 'purchase', 'search' => 'Bottle']))
            ->assertOk()->assertSeeText('5 CTN');
        $this->get(route('stock-entries.index', ['type' => 'purchase', 'search' => 'Jar']))
            ->assertOk()->assertSeeText('No stock entries found.');

        $this->post(route('sales.store'), $this->salePayload($jar, 3))->assertRedirect();
        $this->assertSame(7, StockBalance::available($jar->id));
        $this->get(route('stock.overview'))->assertOk()->assertSeeText('7')->assertSeeText('20 Litre Jar');
        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('stock.overview', ['availability' => 'available', 'search' => 'Jar']))
            ->assertOk()->assertSeeText('7');
    }

    public function test_sale_edit_rechecks_stock_and_keeps_payments_and_delete_returns_unpaid_stock(): void
    {
        $admin = $this->admin();
        $product = Product::create(['name' => 'Jar', 'status' => 'active']);
        $this->entry($product, 10);
        $this->post(route('sales.store'), $this->salePayload($product, 3, 100))->assertRedirect();
        $sale = Sale::firstOrFail();

        $payload = $this->salePayload($product, 11);
        unset($payload['initial_paid_rupees'], $payload['payment_method']);
        $this->put(route('sales.update', $sale), $payload)->assertSessionHasErrors('items.0.cartons');
        $this->assertSame(7, StockBalance::available($product->id));

        $payload['items'][0]['cartons'] = 1;
        $payload['discount_rupees'] = 1;
        $this->put(route('sales.update', $sale), $payload)->assertSessionHasErrors('items');
        $payload['discount_rupees'] = 0;
        $payload['items'][0]['cartons'] = 4;
        $this->put(route('sales.update', $sale), $payload)->assertRedirect(route('sales.show', $sale));
        $this->assertSame($admin->id, $sale->fresh()->updated_by);
        $this->assertSame(6, StockBalance::available($product->id));
        $this->delete(route('sales.destroy', $sale))->assertSessionHasErrors('sale');
        $this->assertSame(1, $sale->payments()->count());

        $this->post(route('sales.store'), $this->salePayload($product, 2))->assertRedirect();
        $unpaid = Sale::orderByDesc('id')->firstOrFail();
        $this->assertSame(4, StockBalance::available($product->id));
        $this->delete(route('sales.destroy', $unpaid))->assertRedirect(route('sales.index'));
        $this->assertSoftDeleted($unpaid);
        $this->assertSame(6, StockBalance::available($product->id));
    }

    public function test_stock_entry_delete_refuses_sold_stock_and_soft_deletes_safe_entry(): void
    {
        $this->admin();
        $product = Product::create(['name' => 'Jar', 'status' => 'active']);
        $first = $this->entry($product, 5);
        $second = $this->entry($product, 2, 'purchase');
        $this->post(route('sales.store'), $this->salePayload($product, 6))->assertRedirect();
        $this->delete(route('stock-entries.destroy', $first))->assertSessionHasErrors('stock_entry');
        $this->assertSame(1, StockBalance::available($product->id));
        $this->post(route('stock-entries.store'), [
            'entry_date' => '2026-09-27',
            'items' => [['product_id' => $product->id, 'cartons' => 3, 'type' => 'purchase']],
        ])->assertRedirect();
        $this->delete(route('stock-entries.destroy', $second))->assertRedirect(route('stock-entries.index'));
        $this->assertSoftDeleted($second);
        $this->assertSame(2, StockBalance::available($product->id));
    }

    public function test_expenses_reuse_category_case_insensitively_filter_edit_and_soft_delete(): void
    {
        $admin = $this->admin();
        $first = ['expense_date' => '2026-09-27', 'title' => 'Fuel', 'category_name' => 'Transport', 'amount_rupees' => 200, 'notes' => 'Truck'];
        $this->post(route('expenses.store'), $first)->assertRedirect(route('expenses.index'));
        $this->post(route('expenses.store'), [...$first, 'title' => 'Delivery', 'category_name' => 'transport', 'amount_rupees' => 300])->assertRedirect();
        $this->assertSame(1, ExpenseCategory::count());
        $this->assertSame(2, Expense::count());
        $this->get(route('expenses.create'))->assertOk()->assertSee('Transport');
        $category = ExpenseCategory::firstOrFail();
        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('expenses.index', ['category' => $category->id, 'from' => '2026-09-27', 'to' => '2026-09-27']))
            ->assertOk()->assertSeeText('₹500');

        $expense = Expense::firstOrFail();
        $this->put(route('expenses.update', $expense), [...$first, 'amount_rupees' => 250])->assertRedirect();
        $this->assertSame($admin->id, $expense->fresh()->updated_by);
        $this->delete(route('expenses.destroy', $expense))->assertRedirect();
        $this->assertSoftDeleted($expense);
        $this->get(route('expenses.index'))->assertOk()->assertSeeText('₹300');
    }
}
