<?php

namespace Tests\Feature;

use App\Models\{PartnerTransaction, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class PartnerAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    private array $originalFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalFiles = glob(public_path('uploads/partners/*.webp')) ?: [];
    }

    protected function tearDown(): void
    {
        foreach (array_diff(glob(public_path('uploads/partners/*.webp')) ?: [], $this->originalFiles) as $path) @unlink($path);
        parent::tearDown();
    }

    private function people(): array
    {
        $admin = User::create(['name' => 'Admin', 'role' => 'admin', 'mobile' => '9999999999', 'salary' => 0, 'password' => 'SecurePass123']);
        $staff = User::create(['name' => 'Staff', 'role' => 'staff', 'mobile' => '9876543210', 'salary' => 0, 'password' => 'SecurePass123']);
        $staff->permissions()->create(['module' => 'partner-ledger', 'scope' => 'self', 'can_view' => true, 'can_create' => true, 'can_edit' => true]);
        $staff->unsetRelation('permissions');
        return [$admin, $staff];
    }

    private function image(string $name = 'receipt.jpg'): UploadedFile
    {
        if (! function_exists('imagewebp')) $this->markTestSkipped('PHP GD WebP is required.');
        return UploadedFile::fake()->image($name, 100, 100);
    }

    private function data(): array
    {
        return ['partner_name' => 'Transporter', 'transaction_date' => today()->toDateString(), 'type' => 'send', 'amount_rupees' => '100'];
    }

    public function test_multiple_images_are_compressed_to_webp_in_one_json_column(): void
    {
        [$admin] = $this->people();
        $this->actingAs($admin)->post(route('partner-ledger.store'), [...$this->data(),
            'attachments' => [$this->image(), $this->image('bill.png')]])->assertRedirect();
        $entry = PartnerTransaction::firstOrFail();
        $this->assertCount(2, $entry->attachment_paths);
        $this->assertSame($entry->attachment_paths, json_decode($entry->getRawOriginal('attachment_paths'), true));
        $this->assertFalse(Schema::hasColumn('partner_transactions', 'attachment_path'));
        foreach ($entry->attachment_paths as $path) {
            $this->assertStringEndsWith('.webp', $path);
            $this->assertFileExists(public_path($path));
            $this->assertSame(IMAGETYPE_WEBP, getimagesize(public_path($path))[2]);
        }
        $this->get(route('partner-ledger.edit', $entry))->assertOk()->assertSee('attachments[]', false)->assertSee('remove_attachments[]', false);
        $this->get(route('partner-ledger.index'))->assertOk()->assertSee(asset($entry->attachment_paths[0]), false)->assertSee(asset($entry->attachment_paths[1]), false);
    }

    public function test_edit_preserves_unselected_images_and_can_remove_one_while_adding_another(): void
    {
        [$admin] = $this->people();
        $this->actingAs($admin)->post(route('partner-ledger.store'), [...$this->data(),
            'attachments' => [$this->image('first.jpg'), $this->image('second.jpg')]])->assertRedirect();
        $entry = PartnerTransaction::firstOrFail();
        [$first, $second] = $entry->attachment_paths;
        $this->put(route('partner-ledger.update', $entry), [...$this->data(), 'note' => 'Updated'])->assertRedirect();
        $this->assertSame([$first, $second], $entry->fresh()->attachment_paths);
        $this->put(route('partner-ledger.update', $entry), [...$this->data(),
            'remove_attachments' => [$first], 'attachments' => [$this->image('replacement.jpg')]])->assertRedirect();
        $paths = $entry->fresh()->attachment_paths;
        $this->assertCount(2, $paths);
        $this->assertSame($second, $paths[0]);
        $this->assertNotSame($first, $paths[1]);
        $this->assertFileDoesNotExist(public_path($first));
        $this->assertFileExists(public_path($second));
        $this->assertFileExists(public_path($paths[1]));
        $this->delete(route('partner-ledger.destroy', $entry))->assertRedirect();
        foreach ($paths as $path) $this->assertFileDoesNotExist(public_path($path));
    }

    public function test_staff_cannot_upload_or_remove_partner_images(): void
    {
        [$admin, $staff] = $this->people();
        $this->actingAs($staff)->post(route('partner-ledger.store'), [...$this->data(), 'attachments' => [$this->image()]])->assertForbidden();
        $this->post(route('partner-ledger.store'), $this->data())->assertRedirect();
        $entry = PartnerTransaction::firstOrFail();
        $this->actingAs($admin)->put(route('partner-ledger.update', $entry), [...$this->data(), 'attachments' => [$this->image()]])->assertRedirect();
        $path = $entry->fresh()->attachment_paths[0];
        $this->actingAs($staff)->get(route('partner-ledger.edit', $entry))->assertOk()->assertDontSee('name="attachments[]"', false);
        $this->put(route('partner-ledger.update', $entry), [...$this->data(), 'remove_attachments' => [$path]])->assertForbidden();
        $this->put(route('partner-ledger.update', $entry), $this->data())->assertRedirect();
        $this->assertSame([$path], $entry->fresh()->attachment_paths);
        $this->assertFileExists(public_path($path));
    }

    public function test_forged_removal_and_excess_images_do_not_change_existing_images_or_leave_files(): void
    {
        [$admin] = $this->people();
        $this->actingAs($admin)->post(route('partner-ledger.store'), [...$this->data(), 'attachments' => [$this->image()]])->assertRedirect();
        $entry = PartnerTransaction::firstOrFail();
        $paths = $entry->attachment_paths;
        $this->put(route('partner-ledger.update', $entry), [...$this->data(), 'remove_attachments' => ['uploads/partners/not-owned.webp']])
            ->assertSessionHasErrors('remove_attachments.0');
        $this->assertSame($paths, $entry->fresh()->attachment_paths);
        $before = glob(public_path('uploads/partners/*.webp'));
        $uploads = [];
        for ($i = 0; $i < 10; $i++) $uploads[] = $this->image("receipt$i.jpg");
        $this->put(route('partner-ledger.update', $entry), [...$this->data(), 'attachments' => $uploads])->assertSessionHasErrors('attachments');
        $this->assertSame($before, glob(public_path('uploads/partners/*.webp')));
        $this->assertSame($paths, $entry->fresh()->attachment_paths);
    }

    public function test_invalid_image_is_rejected_before_any_image_is_saved(): void
    {
        [$admin] = $this->people();
        $before = glob(public_path('uploads/partners/*.webp'));
        $this->actingAs($admin)->post(route('partner-ledger.store'), [...$this->data(),
            'attachments' => [$this->image(), UploadedFile::fake()->create('document.pdf', 2, 'application/pdf')]])
            ->assertSessionHasErrors('attachments.1');
        $this->assertDatabaseCount('partner_transactions', 0);
        $this->assertSame($before, glob(public_path('uploads/partners/*.webp')));
    }

    public function test_migration_preserves_legacy_images_including_deleted_transactions_and_can_be_retried(): void
    {
        [$admin] = $this->people();
        $this->actingAs($admin)->post(route('partner-ledger.store'), $this->data())->assertRedirect();
        $entry = PartnerTransaction::firstOrFail();
        $entry->delete();
        $migration = require database_path('migrations/2026_09_30_000004_partner_multiple_attachments.php');
        $migration->down();
        DB::table('partner_transactions')->where('id', $entry->id)->update([
            'attachment_path' => 'uploads/partners/legacy.webp', 'attachment_paths' => null,
        ]);
        $migration->up();
        $migration->up();
        $this->assertFalse(Schema::hasColumn('partner_transactions', 'attachment_path'));
        $this->assertSame(['uploads/partners/legacy.webp'], PartnerTransaction::withTrashed()->findOrFail($entry->id)->attachment_paths);
    }
}
