<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CcrFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function payload(Product $product): array
    {
        return [
            'title' => 'Update product material configuration', 'change_type' => 'materials',
            'current_state' => 'Existing material configuration is approved.',
            'proposed_state' => 'Replace material with revised specification.',
            'reason_for_change' => 'Supplier availability and material improvement.',
            'affected_scope' => 'Device component and supplier',
            'change_duration' => 'permanent', 'product_ids' => [$product->id],
        ];
    }

    public function test_global_ra_can_create_draft_with_linked_device_and_audit(): void
    {
        $actor = User::factory()->create(['is_active' => true]);
        $actor->assignRole('global-ra');
        $product = Product::create(['sap_code' => 'CCR-TEST-DEVICE', 'product_name' => 'Test Device']);
        $this->actingAs($actor)->get(route('ccr.create'))->assertOk();
        $response = $this->actingAs($actor)->post(route('ccr.store'), $this->payload($product));
        $id = DB::table('change_control_records')->value('id');
        $this->assertNotNull($id);
        $response->assertRedirect(route('ccr.show', $id));
        $this->assertDatabaseHas('change_control_records', ['id' => $id, 'status' => 'draft', 'created_by' => $actor->id]);
        $this->assertMatchesRegularExpression('/^CCR-\d{4}-\d{6}$/', DB::table('change_control_records')->value('ccr_number'));
        $this->assertDatabaseHas('ccr_products', ['change_control_record_id' => $id, 'product_id' => $product->id]);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'change_control_record', 'entity_id' => $id, 'action' => 'ccr.draft.created']);
        $this->actingAs($actor)->get(route('ccr.show', $id))->assertOk()->assertSee('Test Device');
    }

    public function test_other_user_cannot_view_ccr_and_local_ra_cannot_create(): void
    {
        $owner = User::factory()->create(['is_active' => true]); $owner->assignRole('global-ra');
        $other = User::factory()->create(['is_active' => true]); $other->assignRole('global-ra');
        $local = User::factory()->create(['is_active' => true]); $local->assignRole('local-ra');
        $product = Product::create(['sap_code' => 'CCR-PRIVATE-DEV', 'product_name' => 'Private Device']);
        $this->actingAs($owner)->post(route('ccr.store'), $this->payload($product));
        $id = DB::table('change_control_records')->value('id');
        $this->actingAs($other)->get(route('ccr.show', $id))->assertForbidden();
        $this->actingAs($other)->get(route('ccr.index'))->assertDontSee('Private Device');
        $this->actingAs($local)->get(route('ccr.create'))->assertForbidden();
        $this->actingAs($local)->post(route('ccr.store'), $this->payload($product))->assertForbidden();
        $this->assertDatabaseCount('change_control_records', 1);
    }

    public function test_invalid_or_unknown_products_cannot_create_ccr(): void
    {
        $actor = User::factory()->create(['is_active' => true]); $actor->assignRole('global-ra');
        $product = Product::create(['sap_code' => 'CCR-VALID-DEV', 'product_name' => 'Valid Device']);
        $this->actingAs($actor)->post(route('ccr.store'), array_replace($this->payload($product), ['product_ids' => [99999999]]))
            ->assertSessionHasErrors('product_ids.0');
        $this->assertDatabaseCount('change_control_records', 0);
    }
}
