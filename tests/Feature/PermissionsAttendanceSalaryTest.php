<?php

namespace Tests\Feature;

use App\Models\{Attendance, Expense, Product, Sale, Salary, StockEntry, User};
use App\Support\{SalaryMath, StockBalance};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PermissionsAttendanceSalaryTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role, string $mobile, int $salary = 12000): User
    {
        return User::create(['name' => ucfirst($role).$mobile, 'mobile' => $mobile,
            'role' => $role, 'salary' => $salary, 'password' => 'SecurePass123']);
    }

    private function grant(User $user, string $module, string $scope = 'self', array $actions = ['view', 'create', 'edit', 'delete']): void
    {
        $user->permissions()->create(['module' => $module, 'scope' => $scope,
            'can_view' => in_array('view', $actions), 'can_create' => in_array('create', $actions),
            'can_edit' => in_array('edit', $actions), 'can_delete' => in_array('delete', $actions),
            'can_invoice' => in_array('invoice', $actions)]);
        $user->unsetRelation('permissions');
    }

    public function test_staff_created_expense_requires_approval_then_becomes_read_only(): void
    {
        $admin = $this->person('admin', '9999999999');
        $staff = $this->person('staff', '9876543210');
        $other = $this->person('staff', '9876543211');
        $this->grant($staff, 'expenses');
        $this->grant($other, 'expenses');
        $this->actingAs($staff)->post('/expenses', [
            'expense_date' => today()->toDateString(), 'title' => 'Bottle repair',
            'category_name' => 'Maintenance', 'amount_rupees' => '250',
        ])->assertRedirect('/expenses');
        $expense = Expense::firstOrFail();
        $this->assertSame('pending', $expense->approval_status);
        $this->actingAs($other)->get('/expenses')->assertOk()->assertDontSee('Bottle repair');
        $this->actingAs($other)->get('/expenses/'.$expense->id.'/edit')->assertForbidden();
        $other->permissions()->where('module', 'expenses')->update(['scope' => 'all']);
        $other->unsetRelation('permissions');
        $this->actingAs($other)->get('/expenses')->assertOk()->assertSee('Bottle repair');
        $this->actingAs($staff)->get('/expenses')->assertSee('Bottle repair');
        $this->actingAs($staff)->get('/expenses/'.$expense->id.'/edit')->assertOk();
        $this->actingAs($admin)->post('/approvals/expenses/'.$expense->id)->assertRedirect();
        $this->actingAs($admin)->get('/approvals')->assertOk();
        $this->actingAs($staff)->get('/expenses/'.$expense->id.'/edit')->assertForbidden();
        $this->actingAs($staff)->delete('/expenses/'.$expense->id)->assertForbidden();
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'approval_status' => 'approved', 'deleted_at' => null]);
    }

    public function test_staff_pending_stock_does_not_increase_available_balance_until_approved(): void
    {
        $admin = $this->person('admin', '9999999999');
        $staff = $this->person('staff', '9876543210');
        $this->grant($staff, 'stock-entries');
        $this->actingAs($admin);
        $product = Product::create(['name' => 'Water 20L', 'status' => 'active']);
        $this->actingAs($staff)->post('/stock-entries', [
            'entry_date' => today()->toDateString(),
            'items' => [['product_id' => $product->id, 'cartons' => 15, 'type' => 'manufacture']],
        ])->assertRedirect('/stock-entries');
        $entry = StockEntry::firstOrFail();
        $this->assertSame('pending', $entry->approval_status);
        $this->assertSame(0, StockBalance::available($product->id));
        $this->actingAs($admin)->post('/approvals/stock-entries/'.$entry->id)->assertRedirect();
        $this->assertSame(15, StockBalance::available($product->id));
        $this->actingAs($staff)->delete('/stock-entries/'.$entry->id)->assertForbidden();
    }

    public function test_staff_attendance_is_once_per_day_and_salary_uses_approved_hours(): void
    {
        $admin = $this->person('admin', '9999999999');
        $staff = $this->person('staff', '9876543210', 12000);
        $this->grant($staff, 'attendance');
        $this->grant($staff, 'salaries', 'self', ['view']);
        $previousMonth = now()->startOfMonth()->subMonth();
        $fullDate = $previousMonth->copy()->startOfMonth()->toDateString();
        $halfDate = $previousMonth->copy()->startOfMonth()->addDay()->toDateString();
        $this->actingAs($admin)->post('/attendance', [
            'employee_id' => $staff->id, 'work_date' => $fullDate, 'type' => 'full',
        ])->assertRedirect('/attendance');
        $this->actingAs($admin)->post('/attendance', [
            'employee_id' => $staff->id, 'work_date' => $halfDate, 'type' => 'custom', 'hours' => '6',
        ])->assertRedirect('/attendance');
        $this->actingAs($admin)->post('/attendance', [
            'employee_id' => $staff->id, 'work_date' => $fullDate, 'type' => 'full',
        ])->assertSessionHasErrors('work_date');
        $this->actingAs($admin)->post('/attendance', [
            'employee_id' => $staff->id, 'work_date' => $previousMonth->copy()->addDays(2)->toDateString(),
            'type' => 'custom', 'hours' => '13',
        ])->assertSessionHasErrors('hours');
        $this->actingAs($staff)->post('/attendance', [
            'work_date' => $fullDate,
            'type' => 'leave',
        ])->assertRedirect('/attendance');
        $this->assertTrue(Attendance::where('employee_id', $staff->id)->whereDate('work_date', now()->toDateString())->where('approval_status', 'pending')->exists());
        $this->actingAs($staff)->post('/attendance', ['type' => 'full'])->assertSessionHasErrors('work_date');

        $this->actingAs($admin)->get('/salaries/create')->assertOk();
        $this->actingAs($admin)->get('/attendance?month='.$previousMonth->format('Y-m'))->assertOk()->assertSeeText($staff->name);
        $this->actingAs($admin)->get('/salaries/preview?employee_id='.$staff->id.'&month='.$previousMonth->format('Y-m'))
            ->assertOk()->assertSeeText('6.00');
        $this->actingAs($admin)->post('/salaries', [
            'employee_id' => $staff->id, 'month' => $previousMonth->format('Y-m'),
        ])->assertRedirect();
        $salary = Salary::firstOrFail();
        $monthly = SalaryMath::paise((string) $staff->salary);
        $expected = SalaryMath::earned($monthly, 720, $previousMonth)
            + SalaryMath::earned($monthly, 360, $previousMonth);
        $this->assertSame($expected, (int) $salary->earned_paise);
        $this->actingAs($staff)->get('/salaries/'.$salary->id)->assertOk();
        $this->actingAs($staff)->get('/salaries')->assertOk()->assertSeeText($staff->name);
        $this->actingAs($staff)->get('/dashboard')->assertOk()->assertDontSee('Configured monthly salaries');
        $this->actingAs($staff)->post('/salaries', [
            'employee_id' => $staff->id, 'month' => $previousMonth->format('Y-m'),
        ])->assertForbidden();
        $this->actingAs($admin)->post('/attendance', [
            'employee_id' => $staff->id, 'work_date' => $previousMonth->copy()->addDays(3)->toDateString(), 'type' => 'full',
        ])->assertSessionHasErrors('work_date');
    }

    public function test_advance_partial_payment_and_password_change(): void
    {
        $admin = $this->person('admin', '9999999999');
        $staff = $this->person('staff', '9876543210');
        $this->grant($staff, 'salaries', 'self', ['view']);
        $month = now()->startOfMonth()->subMonth()->format('Y-m');
        $this->actingAs($admin);
        $run = Salary::create(['employee_id' => $staff->id, 'month' => $month.'-01',
            'monthly_salary_paise' => 1200000, 'earned_paise' => 300000]);
        $this->actingAs($admin)->post('/salaries/advances', [
            'employee_id' => $staff->id, 'month' => $month, 'paid_on' => today()->toDateString(),
            'amount_rupees' => '500', 'method' => 'cash',
        ])->assertRedirect();
        $this->assertSame(250000, SalaryMath::balance($run));
        $this->actingAs($admin)->post('/salaries/'.$run->id.'/payments', [
            'paid_on' => today()->toDateString(), 'amount_rupees' => '1000', 'method' => 'upi',
        ])->assertRedirect('/salaries/'.$run->id);
        $this->assertSame(150000, SalaryMath::balance($run));
        $this->actingAs($admin)->post('/salaries/'.$run->id.'/payments', [
            'paid_on' => today()->toDateString(), 'amount_rupees' => '2000', 'method' => 'cash',
        ])->assertSessionHasErrors('amount_rupees');
        $this->actingAs($staff)->get('/account/password')->assertOk();
        $this->actingAs($staff)->put('/account/password', [
            'current_password' => 'wrong', 'password' => 'NewSecurePass123',
            'password_confirmation' => 'NewSecurePass123',
        ])->assertSessionHasErrors('current_password');
        $this->actingAs($staff)->put('/account/password', [
            'current_password' => 'SecurePass123', 'password' => 'NewSecurePass123',
            'password_confirmation' => 'NewSecurePass123',
        ])->assertRedirect('/account/password');
        $this->assertTrue(Hash::check('NewSecurePass123', $staff->fresh()->password));
    }

    public function test_pending_sale_and_payment_are_not_effective_until_approved(): void
    {
        $admin = $this->person('admin', '9999999999');
        $staff = $this->person('staff', '9876543210');
        $this->grant($staff, 'sales', 'self', ['view', 'create', 'edit', 'delete', 'invoice']);
        $this->grant($staff, 'payments');
        $this->actingAs($admin);
        $product = Product::create(['name' => 'Bottle', 'status' => 'active']);
        $this->post('/stock-entries', ['entry_date' => today()->toDateString(),
            'items' => [['product_id' => $product->id, 'cartons' => 5, 'type' => 'purchase']]])->assertRedirect();
        $sale = ['party_name' => 'Customer', 'mobile' => '9123456789',
            'sale_date' => today()->toDateString(),
            'items' => [['product_id' => $product->id, 'cartons' => 2, 'rate_rupees' => 100]],
            'discount_rupees' => 0, 'vehicle_charge_rupees' => 0,
            'initial_paid_rupees' => 50, 'payment_method' => 'cash',
            'due_date' => today()->addDays(5)->toDateString(),
        ];
        $this->actingAs($staff)->post('/sales', $sale)->assertRedirect();
        $invoice = Sale::firstOrFail();
        $this->assertSame('pending', $invoice->approval_status);
        $this->assertSame(5, StockBalance::available($product->id));
        $this->assertSame('pending', $invoice->payments()->firstOrFail()->approval_status);
        $this->actingAs($staff)->get('/sales/'.$invoice->id)->assertOk()->assertSee('Pending approval');
        $this->actingAs($admin)->post('/approvals/sales/'.$invoice->id)->assertRedirect();
        $this->assertSame(3, StockBalance::available($product->id));
        $this->assertSame('approved', $invoice->payments()->firstOrFail()->approval_status);
        $this->actingAs($staff)->get('/sales/'.$invoice->id.'/edit')->assertForbidden();
        $this->actingAs($staff)->delete('/sales/'.$invoice->id)->assertForbidden();
        $this->actingAs($staff)->post('/payments', [
            'sale_id' => $invoice->id, 'payment_date' => today()->toDateString(),
            'amount_rupees' => 50, 'method' => 'upi',
        ])->assertRedirect();
        $newPayment = $invoice->payments()->orderByDesc('id')->firstOrFail();
        $this->assertSame('pending', $newPayment->approval_status);
        $this->assertSame(50, (int) $invoice->payments()->where('approval_status', 'approved')->sum('amount_rupees'));
        $this->actingAs($staff)->get('/payments/'.$newPayment->id.'/edit')->assertOk();
        $this->actingAs($admin)->post('/approvals/payments/'.$newPayment->id)->assertRedirect();
        $this->actingAs($staff)->delete('/payments/'.$newPayment->id)->assertForbidden();
        $this->assertSame(100, (int) $invoice->payments()->where('approval_status', 'approved')->sum('amount_rupees'));
    }

    public function test_staff_created_user_cannot_sign_in_until_review_and_cannot_grant_access(): void
    {
        $admin = $this->person('admin', '9999999999');
        $staff = $this->person('staff', '9876543210');
        $this->grant($staff, 'users', 'self', ['view', 'create']);
        $this->actingAs($staff)->post('/users', [
            'name' => 'New Employee', 'mobile' => '9876543212', 'role' => 'staff',
            'salary' => '12000', 'password' => 'WelcomePass123', 'password_confirmation' => 'WelcomePass123',
            'permissions' => ['sales' => ['view' => 1, 'create' => 1, 'scope' => 'all']],
        ])->assertRedirect('/users');
        $new = User::where('mobile', '9876543212')->firstOrFail();
        $this->assertSame('pending', $new->approval_status);
        $this->assertSame(0, $new->permissions()->count());
        $this->postJson('/login', ['mobile' => $new->mobile, 'password' => 'WelcomePass123'])->assertStatus(422);
        $this->actingAs($admin)->post('/approvals/users/'.$new->id)->assertRedirect();
        $this->assertSame('approved', $new->fresh()->approval_status);
        $this->postJson('/login', ['mobile' => $new->mobile, 'password' => 'WelcomePass123'])->assertOk();
    }

    public function test_previous_salary_arrears_are_carried_and_paid_oldest_first(): void
    {
        $admin = $this->person('admin', '9999999999');
        $staff = $this->person('staff', '9876543210');
        $this->actingAs($admin);
        $olderMonth = now()->startOfMonth()->subMonths(2)->format('Y-m');
        $laterMonth = now()->startOfMonth()->subMonth()->format('Y-m');
        $older = Salary::create(['employee_id' => $staff->id, 'month' => $olderMonth.'-01',
            'monthly_salary_paise' => 1200000, 'earned_paise' => 300000]);
        $later = Salary::create(['employee_id' => $staff->id, 'month' => $laterMonth.'-01',
            'monthly_salary_paise' => 1200000, 'earned_paise' => 200000]);
        $this->post('/salaries/advances', [
            'employee_id' => $staff->id, 'month' => $olderMonth, 'paid_on' => today()->toDateString(),
            'amount_rupees' => '500', 'method' => 'cash',
        ])->assertRedirect();
        $this->assertSame(250000, SalaryMath::before($staff->id, $laterMonth.'-01'));
        $this->post('/salaries/'.$later->id.'/payments', [
            'paid_on' => today()->toDateString(), 'amount_rupees' => '3000', 'method' => 'bank',
        ])->assertRedirect('/salaries/'.$later->id);
        $this->assertSame(0, SalaryMath::balance($older));
        $this->assertSame(150000, SalaryMath::balance($later));
        $this->assertSame(0, SalaryMath::before($staff->id, $laterMonth.'-01'));
    }

    public function test_leave_and_salary_have_separate_pages_and_all_staff_leave_keeps_existing_entries(): void
    {
        $admin = $this->person('admin', '9999999999');
        $first = $this->person('staff', '9876543210');
        $second = $this->person('staff', '9876543211');
        $third = $this->person('staff', '9876543212');
        $day = now()->startOfMonth()->subMonth()->toDateString();
        $this->actingAs($admin)->post('/attendance', [
            'employee_id' => $first->id, 'work_date' => $day, 'type' => 'full',
        ])->assertRedirect();
        Salary::create(['employee_id' => $second->id, 'month' => substr($day, 0, 7).'-01',
            'monthly_salary_paise' => 1200000, 'earned_paise' => 0]);

        $this->get('/attendance')->assertOk()->assertSeeText('Mark Leave')->assertSeeText('Add Salary')
            ->assertDontSeeText('Salary & wallet');
        $this->get('/attendance/mark-leave')->assertOk()->assertSeeText('All approved staff');
        $this->post('/attendance/leave', [
            'scope' => 'all', 'work_date' => $day, 'note' => 'Office closed',
        ])->assertRedirect('/attendance?month='.substr($day, 0, 7));
        $this->assertDatabaseHas('attendances', ['employee_id' => $first->id, 'work_date' => $day, 'type' => 'full']);
        $this->assertDatabaseMissing('attendances', ['employee_id' => $second->id, 'work_date' => $day]);
        $this->assertDatabaseHas('attendances', ['employee_id' => $third->id, 'work_date' => $day,
            'type' => 'leave', 'note' => 'Office closed']);
        $this->post('/attendance/leave', [
            'scope' => 'one', 'employee_id' => $first->id, 'work_date' => $day, 'note' => 'Do not replace',
        ])->assertSessionHasErrors('work_date');
        $this->grant($first, 'attendance');
        $this->actingAs($first)->get('/attendance/mark-leave')->assertForbidden();
        $this->post('/attendance/leave', [
            'scope' => 'all', 'work_date' => $day, 'note' => 'Unauthorized',
        ])->assertForbidden();
    }

    public function test_salary_balance_in_users_matches_generation_credit_debit_and_payments(): void
    {
        $admin = $this->person('admin', '9999999999');
        $staff = $this->person('staff', '9876543210');
        $month = now()->startOfMonth()->subMonth()->format('Y-m');
        $date = $month.'-01';
        $this->actingAs($admin);
        $this->post('/attendance', ['employee_id' => $staff->id, 'work_date' => $date, 'type' => 'full'])
            ->assertRedirect();
        $this->get('/salaries/create?employee_id='.$staff->id.'&month='.$month)
            ->assertOk()->assertSeeText('Salary & wallet')->assertSeeText('Payment history');

        $entry = ['employee_id' => $staff->id, 'month' => $month, 'paid_on' => today()->toDateString(),
            'method' => 'cash', 'note' => 'Salary cash'];
        $this->post('/salaries/advances', $entry + ['amount_rupees' => '100.25', 'entry_type' => 'credit'])
            ->assertRedirect();
        $this->assertSame(-10025, $staff->fresh()->salary_balance_paise);
        $this->get('/salaries/create?employee_id='.$staff->id.'&month='.$month)
            ->assertSeeText('Salary cash')->assertSeeText('Added by '.$admin->name);
        $this->post('/salaries/advances', $entry + ['amount_rupees' => '25.10', 'entry_type' => 'debit'])
            ->assertRedirect();
        $this->assertSame(-7515, $staff->fresh()->salary_balance_paise);
        $this->post('/salaries', ['employee_id' => $staff->id, 'month' => $month])
            ->assertRedirect('/salaries/create?month='.$month.'&employee_id='.$staff->id);
        $salary = Salary::firstOrFail();
        $this->assertSame(SalaryMath::wallet($staff->id)['balance'], $staff->fresh()->salary_balance_paise);
        $this->post('/salaries/'.$salary->id.'/payments', [
            'paid_on' => today()->toDateString(), 'amount_rupees' => '50.50', 'method' => 'upi',
        ])->assertRedirect();
        $balance = SalaryMath::wallet($staff->id)['balance'];
        $this->assertSame($balance, $staff->fresh()->salary_balance_paise);
        $this->assertSame($balance, SalaryMath::wallets([$staff->id])[$staff->id]['balance']);

        // A new ungenerated month must not change the wallet amount on the calendar.
        $this->post('/attendance', ['employee_id' => $staff->id,
            'work_date' => today()->toDateString(), 'type' => 'full'])->assertRedirect();
        $display = '₹'.SalaryMath::format(abs($balance));
        $this->get('/attendance')->assertOk()->assertSeeText('Wallet balance · all months')
            ->assertSeeText($display)->assertDontSeeText('Estimated balance');
        $this->get('/salaries/create?employee_id='.$staff->id.'&month='.$month)
            ->assertOk()->assertSeeText('Wallet balance · all months')->assertSeeText($display);
        $this->get('/users')->assertOk()->assertSeeText('Wallet balance')->assertSeeText($display);
        $this->get('/salaries')->assertOk()->assertSeeText('Wallet balance')->assertSeeText($display);
    }
}
