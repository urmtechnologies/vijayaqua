<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PartnerAccount;
use App\Models\PartnerTransaction;
use App\Models\Product;
use App\Models\UpcomingOrder;
use App\Models\User;
use App\Support\StockBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerUpcomingOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'Admin', 'mobile' => '9999999999', 'role' => 'admin',
            'salary' => 0, 'password' => 'StrongPass123!',
        ]);
        $this->actingAs($admin);

        return $admin;
    }

    private function partnerData(string $name, string $type, int $amount): array
    {
        return [
            'partner_name' => $name, 'transaction_date' => '2026-09-28',
            'type' => $type, 'amount_rupees' => $amount, 'note' => 'Short note',
        ];
    }

    private function orderData(Product $first, Product $second): array
    {
        return [
            'customer_name' => 'Aqua Traders', 'mobile' => '9876543210',
            'scheduled_date' => '2026-10-01', 'note' => 'Morning delivery',
            'items' => [
                ['product_id' => $first->id, 'cartons' => 3],
                ['product_id' => $second->id, 'cartons' => 5],
            ],
        ];
    }

    public function test_partner_transactions_group_by_name_and_filtered_balance_is_correct(): void
    {
        $admin = $this->admin();
        $this->get(route('partner-ledger.create'))->assertOk();
        $this->post(route('partner-ledger.store'), [
            ...$this->partnerData('  Arun   Traders ', 'send', 100), 'user_id' => 99999,
        ])->assertRedirect(route('partner-ledger.index'));
        $this->post(route('partner-ledger.store'), $this->partnerData('arun traders', 'receive', 40))
            ->assertRedirect(route('partner-ledger.index'));

        $this->assertSame(1, PartnerAccount::count());
        $account = PartnerAccount::firstOrFail();
        $this->assertSame('Arun Traders', $account->name);
        $this->assertSame($admin->id, PartnerTransaction::firstOrFail()->user_id);
        $this->get(route('partners.show', $account))
            ->assertOk()->assertSeeText('Net sent')->assertSeeText('₹60');
        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('partners.show', ['partner' => $account->id, 'type' => 'receive', 'from' => '2026-09-28', 'to' => '2026-09-28']))
            ->assertOk()->assertSeeText('Net received')->assertSeeText('₹40');
        $this->get(route('partner-ledger.index', ['search' => 'Nothing']))
            ->assertOk()->assertSeeText('No partner transactions found.');
    }

    public function test_partner_edit_and_delete_recalculate_ledger_and_validate_amount(): void
    {
        $admin = $this->admin();
        $this->post(route('partner-ledger.store'), $this->partnerData('Partner One', 'send', 100))->assertRedirect();
        $entry = PartnerTransaction::firstOrFail();
        $this->put(route('partner-ledger.update', $entry), $this->partnerData('Partner One', 'receive', 30))
            ->assertRedirect(route('partner-ledger.index'));
        $this->assertSame($admin->id, $entry->fresh()->updated_by);
        $this->get(route('partner-ledger.index'))->assertOk()->assertSeeText('Net received')->assertSeeText('₹30');
        $this->post(route('partner-ledger.store'), $this->partnerData('Partner One', 'send', 0))
            ->assertSessionHasErrors('amount_rupees');
        $this->delete(route('partner-ledger.destroy', $entry))->assertRedirect();
        $this->assertSoftDeleted($entry);
        $this->get(route('partner-ledger.index'))->assertOk()->assertSeeText('No partner transactions found.');
    }

    public function test_upcoming_order_reuses_customer_and_keeps_stock_unchanged(): void
    {
        $admin = $this->admin();
        $first = Product::create(['name' => 'Jar', 'status' => 'active']);
        $second = Product::create(['name' => 'Bottle', 'status' => 'active']);
        Customer::create(['name' => 'Aqua Traders', 'mobile' => '9876543210']);
        $this->get(route('upcoming-orders.create'))->assertOk()->assertSee('Jar');

        $payload = $this->orderData($first, $second);
        $this->post(route('upcoming-orders.store'), [...$payload, 'user_id' => 99999])
            ->assertRedirect();
        $order = UpcomingOrder::with('items')->firstOrFail();
        $this->assertSame($admin->id, $order->user_id);
        $this->assertSame(1, Customer::count());
        $this->assertCount(2, $order->items);
        $this->assertSame(8, (int) $order->items->sum('cartons'));
        $this->assertSame(0, StockBalance::sold($first->id));
        $this->assertSame(0, StockBalance::available($first->id));
        $this->get(route('upcoming-orders.show', $order))->assertOk()
            ->assertSeeText('Morning delivery')->assertSeeText('Stock is deducted only when a sale is recorded.');

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('upcoming-orders.index', ['search' => 'Bottle', 'from' => '2026-10-01', 'to' => '2026-10-01']))
            ->assertOk()->assertSeeText('8');
        $this->get(route('upcoming-orders.index', ['to' => '2026-09-30']))
            ->assertOk()->assertSeeText('No upcoming orders found.');
    }

    public function test_upcoming_edit_validates_duplicate_or_inactive_products_and_delete_soft_deletes(): void
    {
        $admin = $this->admin();
        $first = Product::create(['name' => 'Jar', 'status' => 'active']);
        $second = Product::create(['name' => 'Bottle', 'status' => 'active']);
        $inactive = Product::create(['name' => 'Old Jar', 'status' => 'inactive']);
        $payload = $this->orderData($first, $second);

        $duplicate = $payload;
        $duplicate['items'][1]['product_id'] = $first->id;
        $this->post(route('upcoming-orders.store'), $duplicate)->assertSessionHasErrors('items.1.product_id');
        $this->post(route('upcoming-orders.store'), $payload)->assertRedirect();
        $order = UpcomingOrder::firstOrFail();
        $invalid = $payload;
        $invalid['items'][1]['product_id'] = $inactive->id;
        $this->put(route('upcoming-orders.update', $order), $invalid)->assertSessionHasErrors('items.1.product_id');

        $second->update(['status' => 'inactive']);
        $payload['items'][1]['cartons'] = 6;
        $this->get(route('upcoming-orders.edit', $order))->assertOk()->assertSee('Bottle');
        $this->put(route('upcoming-orders.update', $order), $payload)
            ->assertRedirect(route('upcoming-orders.show', $order));
        $this->assertSame($admin->id, $order->fresh()->updated_by);
        $this->assertSame(6, $order->items()->where('product_id', $second->id)->firstOrFail()->cartons);

        $this->delete(route('upcoming-orders.destroy', $order))->assertRedirect(route('upcoming-orders.index'));
        $this->assertSoftDeleted($order);
        $this->assertDatabaseHas('upcoming_order_items', ['upcoming_order_id' => $order->id, 'product_id' => $first->id]);
        $this->get(route('upcoming-orders.show', $order))->assertNotFound();
    }

    public function test_staff_cannot_access_new_modules(): void
    {
        $staff = User::create([
            'name' => 'Staff', 'mobile' => '9876543210', 'role' => 'staff',
            'salary' => 10000, 'password' => 'SecurePass123',
        ]);
        $this->actingAs($staff)->get(route('partner-ledger.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('partner-ledger.store'), [])->assertForbidden();
        $this->actingAs($staff)->get(route('upcoming-orders.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('upcoming-orders.store'), [])->assertForbidden();
    }
}
