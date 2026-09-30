<?php

namespace Tests\Feature;

use App\Models\{ExpenseCategory, Product, StockEntry, User, VehicleEntry, VehiclePayment};
use App\Support\{StockBalance, VehicleWallet};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VehicleWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role, string $mobile): User
    {
        return User::create(['name' => ucfirst($role).$mobile, 'role' => $role,
            'mobile' => $mobile, 'salary' => 0, 'password' => 'SecurePass123']);
    }

    private function context(): array
    {
        $admin = $this->person('admin', '9999999999');
        $staff = $this->person('staff', '9876543210');
        $reference = $this->person('driver', '9876543211');
        $staff->permissions()->create(['module' => 'vehicle-entries', 'scope' => 'self',
            'can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true]);
        $staff->unsetRelation('permissions');
        $this->actingAs($admin);
        $product = Product::create(['name' => '20L Jar', 'status' => 'active']);
        return [$admin, $staff, $reference, $product];
    }

    private function trip(User $reference, Product $product, int $qty = 3): array
    {
        return ['reference_user_id' => $reference->id, 'from_destination' => 'Plant',
            'to_destination' => 'Market', 'entry_date' => today()->toDateString(),
            'amount_rupees' => '250.50', 'items' => [['product_id' => $product->id, 'cartons' => $qty]]];
    }

    private function payment(string $type, string $amount): array
    {
        return ['payment_date' => today()->toDateString(), 'entry_type' => $type,
            'method' => 'upi', 'amount_rupees' => $amount, 'note' => 'Trip payment'];
    }

    public function test_vehicle_approval_does_not_check_or_reduce_stock_and_only_then_credits_wallet(): void
    {
        [$admin, $staff, $reference, $product] = $this->context();
        $this->post(route('stock-entries.store'), ['entry_date' => today()->toDateString(),
            'items' => [['product_id' => $product->id, 'cartons' => 8, 'type' => 'manufacture']]])->assertRedirect();
        $this->actingAs($staff)->post(route('vehicle-entries.store'), $this->trip($reference, $product, 20))
            ->assertRedirect(route('vehicle-entries.account', $staff));
        $entry = VehicleEntry::firstOrFail();
        $this->assertSame('pending', $entry->approval_status);
        $this->assertSame(0, VehicleWallet::totals($staff->id)['balance']);
        $this->assertSame(8, StockBalance::available($product->id));
        $this->actingAs($admin);
        $product->update(['status' => 'inactive']);
        $this->postJson(route('approvals.approve', ['module' => 'vehicle-entries', 'id' => $entry->id]))->assertOk();
        $this->assertSame('approved', $entry->fresh()->approval_status);
        $this->assertSame(25050, VehicleWallet::totals($staff->id)['balance']);
        $this->assertSame(8, StockBalance::available($product->id));
        $this->postJson(route('approvals.approve', ['module' => 'vehicle-entries', 'id' => $entry->id]))->assertUnprocessable();
        $this->delete(route('stock-entries.destroy', StockEntry::firstOrFail()))->assertRedirect();
        $this->assertSame(0, StockBalance::available($product->id));
    }

    public function test_credit_debit_and_account_share_exact_approved_wallet_totals(): void
    {
        [$admin, $staff, $reference, $product] = $this->context();
        $this->actingAs($staff)->post(route('vehicle-entries.store'), $this->trip($reference, $product))->assertRedirect();
        $entry = VehicleEntry::firstOrFail();
        $this->post(route('vehicle-entries.store'), [...$this->trip($reference, $product), 'amount_rupees' => '100'])->assertRedirect();
        $this->actingAs($admin)->postJson(route('approvals.approve', ['module' => 'vehicle-entries', 'id' => $entry->id]))->assertOk();
        $this->postJson(route('vehicle-entries.payment', $staff), $this->payment('credit', '200.25'))->assertOk();
        $this->postJson(route('vehicle-entries.payment', $staff), $this->payment('credit', '51'))->assertUnprocessable()->assertJsonValidationErrors('amount_rupees');
        $this->postJson(route('vehicle-entries.payment', $staff), $this->payment('debit', '201'))->assertUnprocessable()->assertJsonValidationErrors('amount_rupees');
        $this->postJson(route('vehicle-entries.payment', $staff), $this->payment('debit', '25'))->assertOk();
        $this->assertSame(['charged' => 25050, 'paid' => 17525, 'balance' => 7525], VehicleWallet::totals($staff->id));
        $this->get(route('vehicle-entries.account', $staff))->assertOk()->assertSeeText('Total Payment')->assertSeeText('Total Paid')
            ->assertSee('₹250.50')->assertSee('₹175.25')->assertSee('₹75.25')->assertSeeText('Payment history');
        $this->withHeader('X-Requested-With', 'XMLHttpRequest')->get(route('vehicle-entries.account', $staff))->assertOk()->assertSee('₹75.25')->assertDontSee('<html', false);
        $this->flushHeaders();
        $this->put(route('vehicle-entries.update', $entry), [...$this->trip($reference, $product), 'amount_rupees' => '100'])->assertSessionHasErrors('amount_rupees');
        $this->delete(route('vehicle-entries.destroy', $entry))->assertSessionHasErrors('entry');
        $this->put(route('vehicle-entries.update', $entry), [...$this->trip($reference, $product, 500), 'amount_rupees' => '300'])->assertRedirect();
        $this->assertSame(12475, VehicleWallet::totals($staff->id)['balance']);
    }

    public function test_small_paise_payments_are_supported_and_zero_is_rejected(): void
    {
        [$admin, $staff, $reference, $product] = $this->context();
        $this->actingAs($staff)->post(route('vehicle-entries.store'), [...$this->trip($reference, $product), 'amount_rupees' => '0.50'])->assertRedirect();
        $entry = VehicleEntry::firstOrFail();
        $this->actingAs($admin)->postJson(route('approvals.approve', ['module' => 'vehicle-entries', 'id' => $entry->id]))->assertOk();
        $this->postJson(route('vehicle-entries.payment', $staff), $this->payment('credit', '0'))->assertUnprocessable();
        $this->postJson(route('vehicle-entries.payment', $staff), $this->payment('credit', '0.25'))->assertOk();
        $this->assertSame(25, VehicleWallet::totals($staff->id)['balance']);
    }

    public function test_list_displays_creator_and_view_links_to_creator_account(): void
    {
        [$admin, $staff, $reference, $product] = $this->context();
        $this->actingAs($staff)->post(route('vehicle-entries.store'), $this->trip($reference, $product))->assertRedirect();
        $this->post(route('vehicle-entries.store'), [...$this->trip($reference, $product), 'to_destination' => 'Depot'])->assertRedirect();
        $this->actingAs($admin)->get(route('vehicle-entries.index'))->assertOk()->assertSeeText('Market')->assertSeeText('Depot')
            ->assertSee(route('vehicle-entries.account', $staff), false)->assertSeeText('View')
            ->assertViewHas('entries', fn ($entries) => $entries->total() === 2 && $entries->first() instanceof VehicleEntry);
        $this->get(route('vehicle-entries.account', $staff))->assertOk()->assertViewHas('entries', fn ($entries) => $entries->total() === 2);
        $this->withHeader('X-Requested-With', 'XMLHttpRequest')->get(route('vehicle-entries.index', ['search' => 'Depot', 'approval' => 'pending']))
            ->assertOk()->assertSeeText('Depot')->assertDontSee('Market')->assertDontSee('<html', false);
        $this->get(route('approvals.index', ['module' => 'vehicle-entries']))->assertOk()->assertSeeText('View')->assertSee(route('vehicle-entries.account', $staff), false);
    }

    public function test_admin_cannot_create_entries_and_reference_cannot_be_self_or_admin(): void
    {
        [$admin, $staff, $reference, $product] = $this->context();
        $this->get(route('vehicle-entries.create'))->assertForbidden();
        $this->post(route('vehicle-entries.store'), $this->trip($reference, $product))->assertForbidden();
        $this->get(route('vehicle-entries.index'))->assertOk()->assertDontSee(route('vehicle-entries.create'), false);
        $this->get(route('dashboard'))->assertOk()->assertDontSee(route('vehicle-entries.create'), false);
        $this->actingAs($staff)->get(route('vehicle-entries.create'))->assertOk()
            ->assertViewHas('staff', fn ($users) => $users->contains('id', $reference->id) && ! $users->contains('id', $staff->id) && ! $users->contains('id', $admin->id));
        $this->post(route('vehicle-entries.store'), $this->trip($staff, $product))->assertSessionHasErrors('reference_user_id');
        $this->post(route('vehicle-entries.store'), $this->trip($admin, $product))->assertSessionHasErrors('reference_user_id');
        $this->post(route('vehicle-entries.store'), $this->trip($reference, $product))->assertRedirect();
        $entry = VehicleEntry::firstOrFail();
        $this->actingAs($admin)->get(route('vehicle-entries.edit', $entry))->assertOk()
            ->assertViewHas('staff', fn ($users) => ! $users->contains('id', $staff->id));
        $this->put(route('vehicle-entries.update', $entry), $this->trip($staff, $product))->assertSessionHasErrors('reference_user_id');
        $this->get(route('vehicle-entries.account', $staff))->assertOk()->assertDontSee(route('vehicle-entries.create'), false);
    }

    public function test_staff_permissions_scope_requests_and_block_approval_and_payment(): void
    {
        [$admin, $staff, $reference, $product] = $this->context();
        $this->actingAs($staff)->post(route('vehicle-entries.store'), $this->trip($reference, $product))->assertRedirect();
        $entry = VehicleEntry::firstOrFail();
        $this->post(route('vehicle-entries.payment', $staff), $this->payment('credit', '1'))->assertForbidden();
        $this->post(route('approvals.approve', ['module' => 'vehicle-entries', 'id' => $entry->id]))->assertForbidden();
        $this->actingAs($reference)->get(route('vehicle-entries.index'))->assertForbidden();
        $reference->permissions()->create(['module' => 'vehicle-entries', 'scope' => 'self', 'can_view' => true]);
        $reference->unsetRelation('permissions');
        $this->get(route('vehicle-entries.index'))->assertOk()->assertDontSee('Market');
        $this->get(route('vehicle-entries.account', $reference))->assertNotFound();
        $this->actingAs($admin)->postJson(route('approvals.approve', ['module' => 'vehicle-entries', 'id' => $entry->id]))->assertOk();
        $this->actingAs($staff)->get(route('vehicle-entries.edit', $entry))->assertForbidden();
    }

    public function test_deleting_the_last_unpaid_entry_returns_to_list(): void
    {
        [$admin, $staff, $reference, $product] = $this->context();
        $this->actingAs($staff)->post(route('vehicle-entries.store'), $this->trip($reference, $product))->assertRedirect();
        $this->delete(route('vehicle-entries.destroy', VehicleEntry::firstOrFail()))->assertRedirect(route('vehicle-entries.index'));
    }

    public function test_different_references_share_one_creator_wallet_and_do_not_merge_other_creators(): void
    {
        [$admin, $staff, $reference, $product] = $this->context();
        $staff->update(['name' => 'Naresh']);
        $reference->update(['name' => 'Mahesh']);
        $secondReference = $this->person('staff', '9876543212');
        $secondReference->update(['name' => 'Vikash']);
        $otherCreator = $this->person('staff', '9876543213');
        $otherCreator->permissions()->create(['module' => 'vehicle-entries', 'scope' => 'self', 'can_view' => true, 'can_create' => true]);
        $otherCreator->unsetRelation('permissions');
        $this->actingAs($staff);
        foreach ([[$reference, '2500'], [$secondReference, '2000'], [$secondReference, '1200']] as [$person, $amount]) {
            $this->post(route('vehicle-entries.store'), [...$this->trip($person, $product), 'amount_rupees' => $amount])
                ->assertRedirect(route('vehicle-entries.account', $staff));
        }
        $this->actingAs($otherCreator)->post(route('vehicle-entries.store'), [...$this->trip($reference, $product), 'amount_rupees' => '500'])->assertRedirect();
        $this->actingAs($admin);
        foreach (VehicleEntry::all() as $entry) {
            $this->postJson(route('approvals.approve', ['module' => 'vehicle-entries', 'id' => $entry->id]))->assertOk();
        }
        $this->assertSame(['charged' => 570000, 'paid' => 0, 'balance' => 570000], VehicleWallet::totals($staff->id));
        $this->assertSame(50000, VehicleWallet::totals($otherCreator->id)['charged']);
        $this->assertSame(0, VehicleWallet::totals($reference->id)['charged']);
        $list = $this->get(route('vehicle-entries.index'))->assertOk();
        $list->assertSee('class="va-vehicle-list-person"><strong>Naresh</strong>', false);
        $this->assertSame(3, substr_count($list->getContent(), 'href="'.route('vehicle-entries.account', $staff).'"'));
        $this->get(route('vehicle-entries.account', $staff))->assertOk()->assertSee('₹5,700')->assertSeeText('Reference: Mahesh')
            ->assertSeeText('Reference: Vikash')->assertViewHas('entries', fn ($entries) => $entries->total() === 3)
            ->assertViewHas('user', fn ($user) => $user->id === $staff->id);
        $this->postJson(route('vehicle-entries.payment', $staff), $this->payment('credit', '2000'))->assertOk();
        $this->postJson(route('vehicle-entries.payment', $staff), $this->payment('debit', '500'))->assertOk();
        $this->assertSame(['charged' => 570000, 'paid' => 150000, 'balance' => 420000], VehicleWallet::totals($staff->id));
        $this->assertSame(50000, VehicleWallet::totals($otherCreator->id)['balance']);
        $this->assertSame($staff->id, VehiclePayment::firstOrFail()->account_user_id);
        $this->assertNull(VehiclePayment::firstOrFail()->reference_user_id);
        $this->withHeader('X-Requested-With', 'XMLHttpRequest')->get(route('vehicle-entries.account', $staff))
            ->assertOk()->assertSee('₹4,200')->assertSee('₹1,500')->assertSeeText('Payment history');
        $this->flushHeaders();
        $this->actingAs($staff)->get(route('vehicle-entries.account', $otherCreator))->assertNotFound();
        $this->get(route('vehicle-entries.account', $staff))->assertOk()->assertSee(route('vehicle-entries.create'), false);
    }

    public function test_changing_reference_keeps_creator_wallet_owner_and_payment_history(): void
    {
        [$admin, $staff, $reference, $product] = $this->context();
        $secondReference = $this->person('staff', '9876543212');
        $this->actingAs($staff)->post(route('vehicle-entries.store'), $this->trip($reference, $product))->assertRedirect();
        $entry = VehicleEntry::firstOrFail();
        $this->actingAs($admin)->postJson(route('approvals.approve', ['module' => 'vehicle-entries', 'id' => $entry->id]))->assertOk();
        $this->postJson(route('vehicle-entries.payment', $staff), $this->payment('credit', '200'))->assertOk();
        $this->put(route('vehicle-entries.update', $entry), [...$this->trip($secondReference, $product), 'user_id' => $admin->id])
            ->assertRedirect(route('vehicle-entries.account', $staff));
        $this->assertSame($staff->id, $entry->fresh()->user_id);
        $this->assertSame($secondReference->id, $entry->fresh()->reference_user_id);
        $this->assertSame(['charged' => 25050, 'paid' => 20000, 'balance' => 5050], VehicleWallet::totals($staff->id));
        $this->assertSame(0, VehicleWallet::totals($secondReference->id)['paid']);
        $this->get(route('vehicle-entries.account', $staff))->assertOk()->assertSee('₹50.50')->assertSeeText('Trip payment');
    }

    public function test_legacy_reference_payments_are_preserved_in_one_creator_wallet_and_migration_is_retryable(): void
    {
        [$admin, $staff, $reference, $product] = $this->context();
        $secondReference = $this->person('staff', '9876543212');
        $this->actingAs($staff);
        foreach ([[$reference, '2500'], [$secondReference, '2000'], [$secondReference, '1200']] as [$person, $amount]) {
            $this->post(route('vehicle-entries.store'), [...$this->trip($person, $product), 'amount_rupees' => $amount])->assertRedirect();
        }
        $this->actingAs($admin);
        foreach (VehicleEntry::all() as $entry) {
            $this->postJson(route('approvals.approve', ['module' => 'vehicle-entries', 'id' => $entry->id]))->assertOk();
        }
        foreach ([[$reference, '800'], [$secondReference, '200']] as [$person, $amount]) {
            DB::table('vehicle_payments')->insert([...$this->payment('credit', $amount),
                'reference_user_id' => $person->id, 'user_id' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $migration = require database_path('migrations/2026_09_30_000005_group_vehicle_wallets_by_creator.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseCount('vehicle_payments', 2);
        $this->assertSame([$staff->id], VehiclePayment::distinct()->pluck('account_user_id')->all());
        $this->assertSame([$reference->id, $secondReference->id], VehiclePayment::orderBy('id')->pluck('reference_user_id')->all());
        $this->assertSame([$admin->id], VehiclePayment::distinct()->pluck('user_id')->all());
        $this->assertSame(['charged' => 570000, 'paid' => 100000, 'balance' => 470000], VehicleWallet::totals($staff->id));
        $this->get(route('vehicle-entries.account', $staff))->assertOk()->assertSee('₹4,700')->assertSee('₹1,000')->assertSee('₹800')->assertSee('₹200');
    }

    public function test_ambiguous_legacy_payment_is_preserved_until_its_creator_is_assigned(): void
    {
        [$admin, $staff, $reference, $product] = $this->context();
        $otherCreator = $this->person('staff', '9876543212');
        $otherCreator->permissions()->create(['module' => 'vehicle-entries', 'scope' => 'self', 'can_create' => true]);
        $otherCreator->unsetRelation('permissions');
        foreach ([$staff, $otherCreator] as $creator) {
            $this->actingAs($creator)->post(route('vehicle-entries.store'), $this->trip($reference, $product))->assertRedirect();
        }
        $this->actingAs($admin);
        foreach (VehicleEntry::all() as $entry) {
            $this->postJson(route('approvals.approve', ['module' => 'vehicle-entries', 'id' => $entry->id]))->assertOk();
        }
        $id = DB::table('vehicle_payments')->insertGetId([...$this->payment('credit', '100'),
            'reference_user_id' => $reference->id, 'user_id' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
        $migration = require database_path('migrations/2026_09_30_000005_group_vehicle_wallets_by_creator.php');
        try {
            $migration->up();
            $this->fail('An ambiguous existing payment must not be guessed or counted twice.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Vehicle payment #'.$id, $error->getMessage());
        }
        $this->assertDatabaseHas('vehicle_payments', ['id' => $id, 'account_user_id' => null, 'reference_user_id' => $reference->id, 'amount_rupees' => 100]);
        $this->postJson(route('vehicle-entries.payment', $staff), $this->payment('credit', '50'))
            ->assertUnprocessable()->assertJsonValidationErrors('amount_rupees');
        $this->assertDatabaseCount('vehicle_payments', 1);
        DB::table('vehicle_payments')->where('id', $id)->update(['account_user_id' => $staff->id]);
        $migration->up();
        $this->assertSame(10000, VehicleWallet::totals($staff->id)['paid']);
        $this->assertSame(0, VehicleWallet::totals($otherCreator->id)['paid']);
    }

    public function test_returned_payment_history_remains_visible_after_last_trip_is_deleted(): void
    {
        [$admin, $staff, $reference, $product] = $this->context();
        $this->actingAs($staff)->post(route('vehicle-entries.store'), $this->trip($reference, $product))->assertRedirect();
        $entry = VehicleEntry::firstOrFail();
        $this->actingAs($admin)->postJson(route('approvals.approve', ['module' => 'vehicle-entries', 'id' => $entry->id]))->assertOk();
        $this->postJson(route('vehicle-entries.payment', $staff), $this->payment('credit', '100'))->assertOk();
        $this->postJson(route('vehicle-entries.payment', $staff), $this->payment('debit', '100'))->assertOk();
        $this->delete(route('vehicle-entries.destroy', $entry))->assertRedirect(route('vehicle-entries.account', $staff));
        $this->get(route('vehicle-entries.account', $staff))->assertOk()->assertSeeText('Payment history')->assertSeeText('Credit')->assertSeeText('Debit');
        $this->assertSame(['charged' => 0, 'paid' => 0, 'balance' => 0], VehicleWallet::totals($staff->id));
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

}
