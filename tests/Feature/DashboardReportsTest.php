<?php

namespace Tests\Feature;

use App\Models\{Expense, ExpenseCategory, Product, Salary, SalaryAdvance, StockEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardReportsTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $name, string $mobile, string $role = 'staff'): User
    {
        return User::create([
            'name' => $name, 'mobile' => $mobile, 'role' => $role,
            'salary' => 12000, 'password' => 'SecurePass123',
        ]);
    }

    private function expense(string $title, ExpenseCategory $category, int $amount): Expense
    {
        return Expense::create([
            'expense_date' => today()->toDateString(), 'title' => $title,
            'expense_category_id' => $category->id, 'amount_rupees' => $amount,
        ]);
    }

    public function test_dashboard_and_downloads_respect_self_scope_and_module_permission(): void
    {
        $admin = $this->person('Admin', '9999999999', 'admin');
        $staff = $this->person('Staff', '9876543210');
        $this->actingAs($admin);
        $other = $this->person('Other', '9876543211');
        $category = ExpenseCategory::create(['name' => 'Supplies', 'key' => 'supplies']);
        $adminExpense = $this->expense('Private supplier charge', $category, 2000);
        $staff->permissions()->create(['module' => 'expenses', 'scope' => 'self', 'can_view' => true]);
        $staff->unsetRelation('permissions');
        $this->actingAs($staff);
        $own = $this->expense('Staff fuel charge', $category, 300);
        $this->assertSame('pending', $own->approval_status);

        $this->get('/dashboard')->assertOk()->assertSee('Expenses')->assertDontSee('Private supplier charge')
            ->assertDontSee('Sales')->assertDontSee('Users');
        $this->get('/reports?module=expenses')->assertOk()->assertSee('Staff fuel charge')
            ->assertDontSee('Private supplier charge')->assertSee('Approved expenses:');
        $this->get('/reports/export/sales/xlsx')->assertForbidden();
        $this->get('/reports/export/stock/pdf')->assertForbidden();
        $xlsx = $this->get('/reports/export/expenses/xlsx')->assertOk();
        $this->assertStringStartsWith('PK', $xlsx->getContent());
        $this->assertStringContainsString('Staff fuel charge', $xlsx->getContent());
        $this->assertStringNotContainsString('Private supplier charge', $xlsx->getContent());
        $pdf = $this->get('/reports/export/expenses/pdf')->assertOk();
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
        $this->assertStringNotContainsString('Private supplier charge', $pdf->getContent());

        $staff->permissions()->where('module', 'expenses')->update(['scope' => 'all']);
        $staff->unsetRelation('permissions');
        $this->get('/reports?module=expenses')->assertSee('Private supplier charge');
        $this->assertDatabaseHas('expenses', ['id' => $adminExpense->id, 'approval_status' => 'approved']);
    }

    public function test_product_self_scope_and_report_filters_apply_before_export(): void
    {
        $admin = $this->person('Admin', '9999999999', 'admin');
        $staff = $this->person('Staff', '9876543210');
        $staff->permissions()->create(['module' => 'products', 'scope' => 'self', 'can_view' => true]);
        $staff->unsetRelation('permissions');
        $this->actingAs($admin);
        Product::create(['name' => 'Private bottle', 'status' => 'active']);
        $this->actingAs($staff);
        Product::create(['name' => 'My bottle', 'status' => 'active']);
        $this->get('/products')->assertOk()->assertSee('My bottle')->assertDontSee('Private bottle');
        $this->get('/reports?module=products')->assertOk()->assertSee('My bottle')->assertDontSee('Private bottle');
        $this->get('/reports/export/products/xlsx?search=Private')->assertOk()->assertDontSee('Private bottle');
        $this->get('/reports/export/products/pdf?search=My')->assertOk()->assertSee('My bottle');
    }

    public function test_admin_can_open_every_report_without_existing_records(): void
    {
        $admin = $this->person('Admin', '9999999999', 'admin');
        $this->actingAs($admin);
        foreach (array_keys(\App\Support\ReportCatalog::LABELS) as $module) {
            $this->get('/reports?module='.$module)->assertOk();
            $this->get('/reports/export/'.$module.'/xlsx')->assertOk()
                ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
        $manufactured = Product::create(['name' => 'Fresh 20L', 'status' => 'active']);
        $purchased = Product::create(['name' => 'Sealed 1L', 'status' => 'active']);
        $entry = StockEntry::create(['entry_date' => today()->toDateString()]);
        $entry->items()->createMany([
            ['product_id' => $manufactured->id, 'cartons' => 5, 'type' => 'manufacture'],
            ['product_id' => $purchased->id, 'cartons' => 7, 'type' => 'purchase'],
        ]);
        $this->get('/reports?module=stock-entries&type=purchase')->assertOk()
            ->assertSee('Sealed 1L')->assertDontSee('Fresh 20L')->assertSee('7 CTN');
        $this->get('/reports?module=stock-entries&type=purchase&search=Fresh')->assertOk()
            ->assertSee('No records match your filters.');

        $employee = $this->person('Employee', '9876543222');
        $salary = Salary::create([
            'employee_id' => $employee->id, 'month' => today()->startOfMonth()->subMonth()->toDateString(),
            'monthly_salary_paise' => 120000, 'earned_paise' => 120000,
        ]);
        SalaryAdvance::create([
            'employee_id' => $employee->id, 'month' => $salary->month->toDateString(),
            'paid_on' => today()->toDateString(), 'amount_paise' => 10000, 'method' => 'cash',
        ]);
        $salary->payments()->create([
            'paid_on' => today()->toDateString(), 'amount_paise' => 20000, 'method' => 'upi',
        ]);
        $this->get('/reports?module=salaries')->assertOk()->assertSee('Employee')
            ->assertSee('1,200.00')->assertSee('100.00')->assertSee('200.00')->assertSee('900.00');
    }
}
