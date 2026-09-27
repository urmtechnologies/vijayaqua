<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'mobile' => '9999999999', 'role' => 'admin',
            'salary' => 0, 'password' => 'StrongPass123!',
        ]);
    }

    public function test_users_and_products_keep_their_creator_id(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Alice', 'mobile' => '9876543210', 'role' => 'staff',
            'salary' => '12000', 'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ])->assertRedirect(route('users.index'));

        $staff = User::where('mobile', '9876543210')->firstOrFail();
        $this->assertSame($admin->id, $staff->user_id);

        $this->actingAs($admin)->post(route('products.store'), [
            'name' => '20 Litre Jar', 'status' => 'active', 'user_id' => $staff->id,
        ])->assertRedirect(route('products.index'));

        $product = Product::where('name', '20 Litre Jar')->firstOrFail();
        $this->assertSame($admin->id, $product->user_id);
    }

    public function test_products_can_be_filtered_updated_and_soft_deleted_from_one_page(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('products.store'), [
            'name' => 'Small Bottle', 'status' => 'inactive',
        ])->assertRedirect(route('products.index'));
        $product = Product::firstOrFail();

        $this->actingAs($admin)->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('products.index', ['status' => 'inactive', 'search' => 'Small']))
            ->assertOk()->assertSeeText('Small Bottle');

        $this->actingAs($admin)->put(route('products.update', $product), [
            'name' => 'Small Bottle', 'status' => 'active',
        ])->assertRedirect(route('products.index'));
        $this->assertSame('active', $product->fresh()->status);
        $this->assertSame($admin->id, $product->fresh()->user_id);
        $this->assertSame($admin->id, $product->fresh()->updated_by);

        $this->actingAs($admin)->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'));
        $this->assertSoftDeleted($product);
    }

    public function test_non_admin_cannot_manage_products(): void
    {
        $staff = User::create([
            'name' => 'Staff', 'mobile' => '9876543210', 'role' => 'staff',
            'salary' => 10000, 'password' => 'SecurePass123',
        ]);

        $this->actingAs($staff)->get(route('products.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('products.store'), [
            'name' => 'Bottle', 'status' => 'active',
        ])->assertForbidden();
    }
}
