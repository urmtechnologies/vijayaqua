<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_staff_and_filter_users_asynchronously(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'mobile' => '9999999999', 'role' => 'admin',
            'salary' => 0, 'password' => 'StrongPass123!',
        ]);

        $this->actingAs($admin)->post('/users', [
            'name' => 'Alice', 'mobile' => '9876543210', 'role' => 'staff',
            'salary' => '12500.50', 'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ])->assertRedirect('/users');

        $staff = User::where('mobile', '9876543210')->firstOrFail();
        $this->assertNull($staff->email);
        $this->assertTrue(Hash::check('SecurePass123', $staff->password));

        $this->actingAs($admin)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get('/users?search=Alice')
            ->assertOk()
            ->assertSeeText('Alice')
            ->assertDontSeeText('Admin');
    }

    public function test_staff_cannot_log_in_or_access_admin_pages(): void
    {
        $staff = User::create([
            'name' => 'Staff', 'mobile' => '9876543210', 'role' => 'staff',
            'salary' => 10000, 'password' => 'SecurePass123',
        ]);

        $this->postJson('/login', [
            'mobile' => $staff->mobile,
            'password' => 'SecurePass123',
        ])->assertStatus(422);

        $this->assertGuest();
        $this->actingAs($staff)->get('/users')->assertForbidden();
    }

    public function test_admin_can_edit_user_and_keep_password_when_blank(): void
    {
        $admin = User::create(['name' => 'Admin', 'mobile' => '9999999999', 'role' => 'admin', 'salary' => 0, 'password' => 'StrongPass123!']);
        $user = User::create(['name' => 'Alice', 'mobile' => '9876543210', 'role' => 'staff', 'salary' => 12000, 'password' => 'OriginalPass123']);

        $this->actingAs($admin)->get(route('users.edit', $user))->assertOk()->assertSee('Alice');
        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => 'Alice Updated', 'mobile' => '9876543210', 'role' => 'Supervisor',
            'salary' => '13000', 'password' => '', 'password_confirmation' => '',
        ])->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertSame('supervisor', $user->role);
        $this->assertSame('Alice Updated', $user->name);
        $this->assertTrue(Hash::check('OriginalPass123', $user->password));
    }

    public function test_role_typo_is_rejected_and_delete_soft_deletes(): void
    {
        $admin = User::create(['name' => 'Admin', 'mobile' => '9999999999', 'role' => 'admin', 'salary' => 0, 'password' => 'StrongPass123!']);
        $user = User::create(['name' => 'Alice', 'mobile' => '9876543210', 'role' => 'staff', 'salary' => 12000, 'password' => 'OriginalPass123']);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Bob', 'mobile' => '9876543211', 'role' => 'staf',
            'salary' => '10000', 'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ])->assertSessionHasErrors('role');

        $this->actingAs($admin)->delete(route('users.destroy', $user))->assertRedirect(route('users.index'));
        $this->assertSoftDeleted($user);
        $this->actingAs($admin)->get(route('users.edit', $user))->assertNotFound();
        $this->actingAs($admin)->delete(route('users.destroy', $admin))->assertNotFound();
    }

}
