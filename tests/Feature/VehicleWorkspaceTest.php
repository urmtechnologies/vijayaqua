<?php

namespace Tests\Feature;

use App\Models\{ExpenseCategory, PartnerTransaction, Product, User, VehicleEntry};
use App\Support\{StockBalance, VehicleWallet};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class VehicleWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role, string $mobile): User
    {
        return User::create(['name' => ucfirst($role).$mobile, 'role' => $role,
            'mobile' => $mobile, 'salary' => 0, 'password' => 'SecurePass123']);
    }

    private function trip(User $reference, Product $product, int $qty = 3): array
    {
        return ['reference_user_id' => $reference->id, 'from_destination' => 'Plant',
            'to_destination' => 'Market', 'entry_date' => today()->toDateString(),
            'amount_rupees' => '250.50', 'items' => [['product_id' => $product->id, 'cartons' => $qty]]];
    }

    public function test_staff_vehicle_dispatch_needs_approval_before_it_changes_stock_or_wallet(): void
    {
        $admin = $this->person('admin', '9999999999');
        $staff = $this->person('staff', '9876543210');
        $reference = $this->person('driver', '9876543211');
        $staff->permissions()->create(['module' => 'vehicle-entries', 'scope' => 'self',
            'can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true]);
        $staff->unsetRelation('permissions');
        $this->actingAs($admin);
        $product = Product::create(['name' => '20L Jar', 'status' => 'active']);
        $this->post(route('stock-entries.store'), ['entry_date' => today()->toDateString(),
            'items' => [['product_id' => $product->id, 'cartons' => 8, 'type' => 'manufacture']]])->assertRedirect();

        $this->actingAs($staff)->post(route('vehicle-entries.store'), $this->trip($reference, $product))
            ->assertRedirect(route('vehicle-entries.account', $reference));
        $entry = VehicleEntry::firstOrFail();
        $this->assertSame('pending', $entry->approval_status);
        $this->assertSame(8, StockBalance::available($product->id));
        $this->assertSame(0, VehicleWallet::totals($reference->id)['balance']);
        $this->actingAs($staff)->get(route('vehicle-entries.account', $reference))->assertOk()->assertSee('Market');
        $this->post(route('vehicle-entries.payment', $reference), [])->assertForbidden();
        $this->post(route('approvals.approve', ['module' => 'vehicle-entries', 'id' => $entry->id]))->assertForbidden();

        $this->actingAs($admin)->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('approvals.index', ['module' => 'vehicle-entries']))->assertOk()->assertSee('Vehicle entries')->assertSee('>1</span>');
        $this->postJson(route('approvals.approve', ['module' => 'vehicle-entries', 'id' => $entry->id]))->assertOk();
        $this->assertSame(5, StockBalance::available($product->id));
        $this->assertSame(25050, VehicleWallet::totals($reference->id)['balance']);
        $this->actingAs($staff)->get(route('vehicle-entries.edit', $entry))->assertForbidden();

        $this->actingAs($admin)->post(route('vehicle-entries.payment', $reference), [
            'payment_date' => today()->toDateString(), 'entry_type' => 'credit', 'method' => 'upi',
            'amount_rupees' => '200.25',
        ])->assertRedirect();
        $this->assertSame(['charged' => 25050, 'paid' => 20025, 'balance' => 5025], VehicleWallet::totals($reference->id));
        $this->post(route('vehicle-entries.payment', $reference), [
            'payment_date' => today()->toDateString(), 'entry_type' => 'credit', 'method' => 'cash',
            'amount_rupees' => '51',
        ])->assertSessionHasErrors('amount_rupees');
        $this->post(route('vehicle-entries.payment', $reference), [
            'payment_date' => today()->toDateString(), 'entry_type' => 'debit', 'method' => 'cash',
            'amount_rupees' => '25',
        ])->assertRedirect();
        $this->assertSame(7525, VehicleWallet::totals($reference->id)['balance']);
        $this->get(route('vehicle-entries.index'))->assertOk()->assertSee('₹75.25');
        $this->get(route('vehicle-entries.account', $reference))->assertOk()->assertSee('₹75.25');
        $this->put(route('vehicle-entries.update', $entry), [...$this->trip($reference, $product), 'amount_rupees' => '100'])
            ->assertSessionHasErrors('amount_rupees');
        $this->delete(route('vehicle-entries.destroy', $entry))->assertSessionHasErrors('entry');
        $this->assertSame(5, StockBalance::available($product->id));
    }

    public function test_approved_vehicle_trip_is_grouped_and_stock_cannot_be_removed_beneath_dispatch(): void
    {
        $admin = $this->person('admin', '9999999999');
        $reference = $this->person('driver', '9876543211');
        $this->actingAs($admin);
        $product = Product::create(['name' => 'Bottle', 'status' => 'active']);
        $this->post(route('stock-entries.store'), ['entry_date' => today()->toDateString(),
            'items' => [['product_id' => $product->id, 'cartons' => 4, 'type' => 'purchase']]])->assertRedirect();
        $this->post(route('vehicle-entries.store'), $this->trip($reference, $product, 2))->assertRedirect();
        $this->post(route('vehicle-entries.store'), $this->trip($reference, $product, 1))->assertRedirect();
        $this->get(route('vehicle-entries.index'))->assertOk()->assertSeeText('2 entries');
        $this->assertSame(1, StockBalance::available($product->id));
        $this->delete(route('stock-entries.destroy', \App\Models\StockEntry::firstOrFail()))->assertSessionHasErrors('stock_entry');
    }

    public function test_admin_manages_expense_categories_and_staff_only_selects_them(): void
    {
        $admin = $this->person('admin', '9999999999');
        $staff = $this->person('staff', '9876543210');
        $staff->permissions()->create(['module' => 'expenses', 'scope' => 'self', 'can_create' => true]);
        $staff->unsetRelation('permissions');
        $this->actingAs($admin)->post(route('expense-categories.store'), ['name' => 'Vehicle Fuel'])->assertRedirect();
        $category = ExpenseCategory::firstOrFail();
        $this->actingAs($staff)->get(route('expenses.create'))->assertOk()->assertSee('Vehicle Fuel');
        $this->post(route('expense-categories.store'), ['name' => 'Hidden'])->assertForbidden();
        $this->post(route('expenses.store'), ['expense_date' => today()->toDateString(),
            'title' => 'Diesel', 'expense_category_id' => $category->id, 'amount_rupees' => '500'])->assertRedirect();
        $this->actingAs($admin)->delete(route('expense-categories.destroy', $category))->assertSessionHasErrors('category');
    }

    public function test_partner_image_is_webp_and_only_admin_can_upload_it(): void
    {
        if (! function_exists('imagewebp')) $this->markTestSkipped('PHP GD WebP is required.');
        $admin = $this->person('admin', '9999999999');
        $staff = $this->person('staff', '9876543210');
        $staff->permissions()->create(['module' => 'partner-ledger', 'scope' => 'self', 'can_create' => true]);
        $staff->unsetRelation('permissions');
        $data = ['partner_name' => 'Transporter', 'transaction_date' => today()->toDateString(),
            'type' => 'send', 'amount_rupees' => '100', 'attachment' => UploadedFile::fake()->image('receipt.jpg')];
        $this->actingAs($staff)->post(route('partner-ledger.store'), $data)->assertForbidden();
        $data['attachment'] = UploadedFile::fake()->image('receipt.jpg');
        $this->actingAs($admin)->post(route('partner-ledger.store'), $data)->assertRedirect();
        $path = PartnerTransaction::firstOrFail()->attachment_path;
        $this->assertStringEndsWith('.webp', $path);
        $this->assertFileExists(public_path($path));
        @unlink(public_path($path));
    }
}
