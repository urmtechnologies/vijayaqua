<?php

namespace Tests\Feature;

use App\Models\{Customer, Product, Sale, StockEntry, User};
use App\Support\SaleMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartySalesWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $mobile, string $role): User
    {
        return User::create(['name' => $role.' '.$mobile, 'mobile' => $mobile, 'role' => $role,
            'salary' => 12000, 'password' => 'SafePassword123']);
    }

    private function stockedProduct(): Product
    {
        $product = Product::create(['name' => '20 litre bottle', 'status' => 'active']);
        $entry = StockEntry::create(['entry_date' => today()->toDateString()]);
        $entry->items()->create(['product_id' => $product->id, 'cartons' => 500, 'type' => 'manufacture']);
        return $product;
    }

    public function test_short_sale_keeps_draft_out_of_wallet_and_preserves_paise_and_history(): void
    {
        $admin = $this->user('9999999999', 'admin');
        $this->actingAs($admin);
        $product = $this->stockedProduct();
        $this->post('/sales', ['party_name' => 'Rajesh Kumar', 'mobile' => '9876543210',
            'sale_date' => today()->toDateString()])->assertRedirect();
        $sale = Sale::firstOrFail();
        $customer = Customer::firstOrFail();
        $this->assertTrue($sale->is_draft);
        $this->assertSame(0, SaleMoney::paise($customer->balance_rupees));

        $this->put('/sales/'.$sale->id, ['items' => [[
            'product_id' => $product->id, 'cartons' => 100, 'rate_rupees' => '120.10',
        ]], 'reference_user_id' => null])->assertRedirect('/customers/'.$customer->id);
        $sale->refresh();
        $this->assertFalse($sale->is_draft);
        $this->assertSame(1_201_000, SaleMoney::paise($sale->total_rupees));
        $this->assertSame(1_201_000, SaleMoney::paise($customer->fresh()->balance_rupees));

        $this->post('/payments', ['sale_id' => $sale->id, 'payment_date' => today()->toDateString(),
            'amount_rupees' => '10.10', 'method' => 'upi'])->assertRedirect('/customers/'.$customer->id);
        $this->assertSame(1_199_990, SaleMoney::paise($customer->fresh()->balance_rupees));
        $this->get('/sales')->assertOk()->assertSee('Rajesh Kumar');
        $this->get('/customers/'.$customer->id)->assertOk()->assertSee($sale->invoice_no)->assertSee('10.10');
    }

    public function test_staff_with_create_permission_can_finish_own_draft_before_admin_approval(): void
    {
        $admin = $this->user('9999999999', 'admin');
        $staff = $this->user('9876543211', 'staff');
        $staff->permissions()->create(['module' => 'sales', 'scope' => 'self', 'can_create' => true]);
        $this->actingAs($admin);
        $product = $this->stockedProduct();
        $this->actingAs($staff);
        $this->post('/sales', ['party_name' => 'Vikash', 'mobile' => '9876543212',
            'sale_date' => today()->toDateString()])->assertRedirect();
        $sale = Sale::firstOrFail();
        $this->get('/sales/'.$sale->id.'/edit')->assertOk();
        $this->put('/sales/'.$sale->id, ['items' => [[
            'product_id' => $product->id, 'cartons' => 1, 'rate_rupees' => '120.10',
        ]]])->assertRedirect();
        $this->assertSame('pending', $sale->fresh()->approval_status);
        $this->assertSame(0, SaleMoney::paise($sale->customer->fresh()->balance_rupees));
        $this->get('/sales')->assertOk()->assertSee('Vikash')->assertSee('Pending approval');
        $this->actingAs($admin)->post('/approvals/sales/'.$sale->id)->assertRedirect();
        $this->assertSame(12_010, SaleMoney::paise($sale->customer->fresh()->balance_rupees));
    }

    public function test_staff_only_sees_their_own_sales_under_a_shared_party(): void
    {
        $admin = $this->user('9999999999', 'admin');
        $firstStaff = $this->user('9876543211', 'staff');
        $secondStaff = $this->user('9876543212', 'staff');
        foreach ([$firstStaff, $secondStaff] as $staff) {
            $staff->permissions()->create(['module' => 'sales', 'scope' => 'self',
                'can_view' => true, 'can_create' => true, 'can_edit' => true]);
        }
        $this->actingAs($admin);
        $product = $this->stockedProduct();
        $party = ['party_name' => 'Shared Party', 'mobile' => '9876543210',
            'sale_date' => today()->toDateString()];
        $this->post('/sales', $party)->assertRedirect();
        $adminSale = Sale::firstOrFail();
        $this->put('/sales/'.$adminSale->id, ['items' => [[
            'product_id' => $product->id, 'cartons' => 1, 'rate_rupees' => '100.00',
        ]]])->assertRedirect();

        $this->actingAs($firstStaff)->post('/sales', $party)->assertRedirect();
        $staffSale = Sale::orderByDesc('id')->firstOrFail();
        $this->put('/sales/'.$staffSale->id, ['items' => [[
            'product_id' => $product->id, 'cartons' => 1, 'rate_rupees' => '20.50',
        ]]])->assertRedirect();
        $customer = Customer::firstOrFail();
        $this->get('/customers/'.$customer->id)->assertOk()
            ->assertSee($staffSale->invoice_no)->assertDontSee($adminSale->invoice_no);
        $this->get('/sales/'.$adminSale->id.'/edit')->assertForbidden();

        $this->actingAs($secondStaff)->get('/sales')->assertOk()->assertDontSee('Shared Party');
        $this->get('/customers/'.$customer->id)->assertNotFound();
        $this->actingAs($admin)->post('/approvals/sales/'.$staffSale->id)->assertRedirect();
        $this->assertSame(12_050, SaleMoney::paise($customer->fresh()->balance_rupees));
        $this->actingAs($firstStaff)->get('/sales')->assertOk()
            ->assertSee('₹20.50')->assertDontSee('₹120.50');
    }
}
