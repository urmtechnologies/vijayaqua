<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\StockBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'mobile' => '9999999999', 'role' => 'admin',
            'salary' => 0, 'password' => 'StrongPass123!',
        ]);
    }

    private function receive(Product $product, int $cartons): void
    {
        $this->post(route('stock-entries.store'), [
            'entry_date' => '2026-09-27', 'type' => 'manufacture',
            'items' => [['product_id' => $product->id, 'cartons' => $cartons, 'type' => 'manufacture']],
        ])->assertRedirect(route('stock-entries.index'));
    }

    private function sale(Product $product, int $cartons, int $paid): array
    {
        return [
            'party_name' => 'Aqua Traders', 'mobile' => '9876543210',
            'sale_date' => '2026-09-27',
            'items' => [['product_id' => $product->id, 'cartons' => $cartons, 'rate_rupees' => 100]],
            'discount_rupees' => 0, 'vehicle_charge_rupees' => 0,
            'initial_paid_rupees' => $paid, 'payment_method' => $paid ? 'upi' : '',
            'due_date' => $paid < $cartons * 100 ? '2026-10-01' : null,
        ];
    }

    public function test_repeat_party_gets_new_immutable_invoice_and_separate_payments(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $product = Product::create(['name' => '20 Litre Jar', 'status' => 'active']);
        $this->receive($product, 10);

        $this->post(route('sales.store'), $this->sale($product, 2, 50))->assertRedirect();
        $first = Sale::with('items', 'payments')->firstOrFail();
        $this->assertSame('VA-INV-000001', $first->invoice_no);
        $this->assertSame($admin->id, $first->user_id);
        $this->assertSame(200, (int) $first->total_rupees);
        $this->assertSame(50, (int) $first->payments->firstOrFail()->amount_rupees);

        $this->post(route('sales.store'), $this->sale($product, 1, 0))->assertRedirect();
        $this->assertSame(1, Customer::count());
        $this->assertSame(2, Sale::count());
        $this->assertSame(200, (int) $first->fresh()->total_rupees);
        $this->get(route('sales.edit', $first))->assertOk()->assertSee($first->invoice_no);

        $this->post(route('payments.store'), [
            'sale_id' => $first->id, 'payment_date' => '2026-09-27',
            'amount_rupees' => 150, 'method' => 'cash', 'reference' => 'Receipt 1',
        ])->assertRedirect(route('sales.show', $first));
        $this->assertSame(2, $first->payments()->count());
        $this->assertSame(200, (int) $first->payments()->sum('amount_rupees'));
        $this->get(route('customers.show', $first->customer))->assertOk()->assertSee('VA-INV-000001')->assertSee('VA-INV-000002');
    }

    public function test_oversell_overpayment_and_stock_reduction_are_blocked(): void
    {
        $this->actingAs($this->admin());
        $product = Product::create(['name' => '20 Litre Jar', 'status' => 'active']);
        $this->receive($product, 2);

        $this->post(route('sales.store'), $this->sale($product, 3, 0))
            ->assertSessionHasErrors('items.0.cartons');
        $this->assertSame(0, Sale::count());

        $this->post(route('sales.store'), $this->sale($product, 2, 0))->assertRedirect();
        $sale = Sale::firstOrFail();
        $this->post(route('payments.store'), [
            'sale_id' => $sale->id, 'payment_date' => '2026-09-27',
            'amount_rupees' => 201, 'method' => 'cash',
        ])->assertSessionHasErrors('amount_rupees');

        $entry = \App\Models\StockEntry::firstOrFail();
        $this->put(route('stock-entries.update', $entry), [
            'entry_date' => '2026-09-27', 'type' => 'purchase',
            'items' => [['product_id' => $product->id, 'cartons' => 1, 'type' => 'purchase']],
        ])->assertSessionHasErrors('items');
        $this->assertSame(2, $entry->items()->firstOrFail()->cartons);
        $this->assertSame('manufacture', $entry->fresh()->type);
    }

    public function test_each_sale_reduces_available_cartons_for_the_next_sale(): void
    {
        $this->actingAs($this->admin());
        $product = Product::create(['name' => '20 Litre Jar', 'status' => 'active']);
        $this->receive($product, 10);

        $this->post(route('sales.store'), $this->sale($product, 3, 0))->assertRedirect();
        $this->assertSame(10, StockBalance::received($product->id));
        $this->assertSame(3, StockBalance::sold($product->id));
        $this->assertSame(7, StockBalance::available($product->id));
        $this->get(route('sales.create'))->assertOk()->assertSee('"available":7', false);

        $this->post(route('sales.store'), $this->sale($product, 8, 0))
            ->assertSessionHasErrors('items.0.cartons');
        $this->assertSame(1, Sale::count());

        $this->post(route('sales.store'), $this->sale($product, 7, 0))->assertRedirect();
        $this->assertSame(0, StockBalance::available($product->id));
    }

    public function test_stock_type_filter_and_edit_audit(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $product = Product::create(['name' => '20 Litre Jar', 'status' => 'active']);
        $this->receive($product, 5);
        $entry = \App\Models\StockEntry::firstOrFail();
        $this->put(route('stock-entries.update', $entry), [
            'entry_date' => '2026-09-27', 'type' => 'purchase',
            'items' => [['product_id' => $product->id, 'cartons' => 5, 'type' => 'purchase']],
        ])->assertRedirect(route('stock-entries.show', $entry));
        $this->assertSame($admin->id, $entry->fresh()->updated_by);
        $this->withHeader('X-Requested-With', 'XMLHttpRequest')->get(route('stock-entries.index', ['type' => 'purchase']))
            ->assertOk()->assertSeeText('Purchase');
    }

    public function test_invoice_calculates_discount_vehicle_charge_and_due_in_whole_rupees(): void
    {
        $this->actingAs($this->admin());
        $product = Product::create(['name' => '20 Litre Jar', 'status' => 'active']);
        $this->receive($product, 5);
        $payload = $this->sale($product, 3, 100);
        $payload['discount_rupees'] = 25;
        $payload['vehicle_charge_rupees'] = 5;
        $payload['reference'] = 'Order 42';

        $this->post(route('sales.store'), $payload)->assertRedirect();
        $sale = Sale::firstOrFail();
        $this->assertSame(300, (int) $sale->subtotal_rupees);
        $this->assertSame(280, (int) $sale->total_rupees);
        $this->assertSame('Order 42', $sale->reference);
        $this->get(route('sales.show', $sale))->assertOk()->assertSee('VA-INV-000001')->assertSeeText('₹180');
    }

    public function test_large_summary_difference_keeps_every_digit(): void
    {
        $this->assertSame('8999999999999999', \App\Support\RupeeAmount::difference('10000000000000000', '1000000000000001'));
    }

    public function test_partial_payment_requires_due_date_and_method(): void
    {
        $this->actingAs($this->admin());
        $product = Product::create(['name' => '20 Litre Jar', 'status' => 'active']);
        $this->receive($product, 2);
        $payload = $this->sale($product, 1, 50);
        $payload['due_date'] = null;
        $payload['payment_method'] = '';

        $this->post(route('sales.store'), $payload)->assertSessionHasErrors('payment_method');
        $payload['payment_method'] = 'cash';
        $this->post(route('sales.store'), $payload)->assertSessionHasErrors('due_date');
        $this->assertSame(0, Sale::count());
    }

    public function test_sale_party_and_payment_date_filters_include_the_selected_day(): void
    {
        $this->actingAs($this->admin());
        $product = Product::create(['name' => '20 Litre Jar', 'status' => 'active']);
        $this->receive($product, 2);
        $this->get(route('sales.create'))->assertOk()->assertSee('20 Litre Jar');
        $this->post(route('sales.store'), $this->sale($product, 1, 40))->assertRedirect();
        $sale = Sale::firstOrFail();
        $this->get(route('payments.create', ['invoice' => $sale->invoice_no]))
            ->assertOk()->assertSee($sale->invoice_no);
        $this->get(route('payments.lookup', ['invoice' => $sale->invoice_no]))
            ->assertOk()->assertJsonPath('due', 60);
        $this->get(route('customers.lookup', ['mobile' => '9876543210']))
            ->assertOk()->assertJsonPath('found', true);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('sales.index', ['from' => '2026-09-27', 'to' => '2026-09-27']))
            ->assertOk()->assertSee($sale->invoice_no);
        $this->get(route('customers.show', $sale->customer, false).'?from=2026-09-27&to=2026-09-27')
            ->assertOk()->assertSee($sale->invoice_no);
        $this->get(route('payments.index', ['from' => '2026-09-27', 'to' => '2026-09-27']))
            ->assertOk()->assertSee($sale->invoice_no);

        $this->get(route('sales.index', ['to' => '2026-09-26']))
            ->assertOk()->assertSeeText('No invoices found.');
    }
}
