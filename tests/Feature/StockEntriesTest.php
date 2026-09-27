<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockEntriesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'mobile' => '9999999999', 'role' => 'admin',
            'salary' => 0, 'password' => 'StrongPass123!',
        ]);
    }

    public function test_multiple_products_save_once_with_creator_and_filtered_totals(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $jar = Product::create(['name' => '20 Litre Jar', 'status' => 'active']);
        $bottle = Product::create(['name' => 'Small Bottle', 'status' => 'active']);

        $this->post(route('stock-entries.store'), [
            'entry_date' => '2026-09-27', 'type' => 'manufacture',
            'items' => [
                ['product_id' => $jar->id, 'cartons' => 2, 'type' => 'manufacture'],
                ['product_id' => $bottle->id, 'cartons' => 3, 'type' => 'manufacture'],
            ],
            'user_id' => 99999,
        ])->assertRedirect(route('stock-entries.index'));

        $entry = StockEntry::with('items')->firstOrFail();
        $this->assertSame($admin->id, $entry->user_id);
        $this->assertCount(2, $entry->items);

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('stock-entries.index', [
                'search' => 'Jar', 'from' => '2026-09-27', 'to' => '2026-09-27',
            ]))->assertOk()->assertSeeText('2 CTN')->assertSeeText('Admin');

        $this->get(route('stock-entries.show', $entry))
            ->assertOk()->assertSeeText('20 Litre Jar')->assertSeeText('Small Bottle');

        $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('stock-entries.index', ['to' => '2026-09-26']))
            ->assertOk()->assertDontSeeText('5 CTN');
    }

    public function test_edit_replaces_items_atomically_and_keeps_original_creator(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $jar = Product::create(['name' => '20 Litre Jar', 'status' => 'active']);
        $bottle = Product::create(['name' => 'Small Bottle', 'status' => 'active']);
        $entry = StockEntry::create(['entry_date' => '2026-09-26']);
        $entry->items()->create(['product_id' => $jar->id, 'cartons' => 4]);

        $this->put(route('stock-entries.update', $entry), [
            'entry_date' => '2026-09-27', 'type' => 'manufacture',
            'items' => [['product_id' => $bottle->id, 'cartons' => 8, 'type' => 'manufacture']],
        ])->assertRedirect(route('stock-entries.show', $entry));

        $this->assertSame($admin->id, $entry->fresh()->user_id);
        $this->assertSame('2026-09-27', $entry->fresh()->entry_date->format('Y-m-d'));
        $this->assertDatabaseMissing('stock_entry_items', ['stock_entry_id' => $entry->id, 'product_id' => $jar->id]);
        $this->assertDatabaseHas('stock_entry_items', ['stock_entry_id' => $entry->id, 'product_id' => $bottle->id, 'cartons' => 8, 'type' => 'manufacture']);
    }

    public function test_duplicate_or_inactive_product_is_rejected_without_changing_entry(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $jar = Product::create(['name' => '20 Litre Jar', 'status' => 'active']);
        $inactive = Product::create(['name' => 'Old Jar', 'status' => 'inactive']);
        $entry = StockEntry::create(['entry_date' => '2026-09-26']);
        $entry->items()->create(['product_id' => $jar->id, 'cartons' => 4]);

        $this->post(route('stock-entries.store'), [
            'entry_date' => '2026-09-27', 'type' => 'manufacture',
            'items' => [
                ['product_id' => $jar->id, 'cartons' => 1, 'type' => 'manufacture'],
                ['product_id' => $jar->id, 'cartons' => 2, 'type' => 'manufacture'],
            ],
        ])->assertSessionHasErrors('items.1.product_id');

        $this->put(route('stock-entries.update', $entry), [
            'entry_date' => '2026-09-27', 'type' => 'manufacture',
            'items' => [['product_id' => $inactive->id, 'cartons' => 3, 'type' => 'manufacture']],
        ])->assertSessionHasErrors('items.0.product_id');

        $this->assertSame(4, $entry->items()->firstOrFail()->cartons);
        $this->assertSame('2026-09-26', $entry->fresh()->entry_date->format('Y-m-d'));
    }

    public function test_edit_can_keep_product_that_became_inactive(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $jar = Product::create(['name' => '20 Litre Jar', 'status' => 'active']);
        $entry = StockEntry::create(['entry_date' => '2026-09-26']);
        $entry->items()->create(['product_id' => $jar->id, 'cartons' => 4]);
        $jar->update(['status' => 'inactive']);

        $this->get(route('stock-entries.edit', $entry))->assertOk()->assertSee('20 Litre Jar');
        $this->put(route('stock-entries.update', $entry), [
            'entry_date' => '2026-09-27', 'type' => 'manufacture',
            'items' => [['product_id' => $jar->id, 'cartons' => 6, 'type' => 'manufacture']],
        ])->assertRedirect(route('stock-entries.show', $entry));

        $this->assertSame(6, $entry->items()->firstOrFail()->cartons);
    }

    public function test_staff_cannot_access_stock_management(): void
    {
        $staff = User::create([
            'name' => 'Staff', 'mobile' => '9876543210', 'role' => 'staff',
            'salary' => 10000, 'password' => 'SecurePass123',
        ]);

        $this->actingAs($staff)->get(route('stock-entries.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('stock-entries.store'), [])->assertForbidden();
    }
}
