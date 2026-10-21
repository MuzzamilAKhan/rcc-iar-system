<?php

namespace Tests\Feature;

use App\Livewire\Iar\CreateDraft;
use App\Models\ImpactAssessment;
use App\Models\Product;
use App\Models\User;
use App\Services\Iar\CreateIarDraft;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class IarDraftCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function actor(string $role = 'global-ra', bool $active = true): User
    {
        $user = User::factory()->create(['is_active' => $active]);
        $user->assignRole($role);
        return $user;
    }

    private function payload(array $extra = []): array
    {
        $product = Product::create(['sap_code' => 'SAP-' . uniqid(), 'product_name' => 'Demo Device']);
        return array_replace([
            'ccr_number' => 'CCR-2026-001',
            'current_version' => 'Original device configuration',
            'new_version' => 'Revised device configuration',
            'sap_udi_icc_impacted' => true,
            'labelling_impacted' => false,
            'is_urgent' => false,
            'estimated_implementation_date' => null,
            'due_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'product_ids' => [$product->id],
        ], $extra);
    }

    public function test_active_global_ra_can_create_draft_and_audit_it(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor)->get('/iars/create')->assertOk();

        $iar = app(CreateIarDraft::class)->create($actor, $this->payload());
        $this->assertSame('draft', $iar->status);
        $this->assertMatchesRegularExpression('/^IAR-\d{4}-\d{6}$/', $iar->iar_number);
        $this->assertSame($actor->id, $iar->creator_id);
        $this->assertSame(1, $iar->products()->count());
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $actor->id,
            'entity_id' => $iar->id,
            'action' => 'iar.draft.created',
        ]);
        $this->actingAs($actor)->get(route('iar.show', $iar))->assertOk()->assertSee($iar->iar_number);
    }

    public function test_local_ra_cannot_open_form_or_create_draft(): void
    {
        $actor = $this->actor('local-ra');
        $this->actingAs($actor)->get('/iars/create')->assertForbidden();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CreateIarDraft::class)->create($actor, $this->payload());
    }

    public function test_inactive_creator_cannot_create_draft(): void
    {
        $actor = $this->actor('global-ra', false);

        // Existing active middleware blocks inactive users via redirect.
        $this->actingAs($actor)
            ->get('/iars/create')
            ->assertRedirect();

        // Service-level authorization must independently block creation.
        $this->expectException(
            \Symfony\Component\HttpKernel\Exception\HttpException::class
        );

        app(CreateIarDraft::class)->create(
            $actor,
            $this->payload()
        );
    }

    public function test_draft_rejects_missing_products_and_past_due_date(): void
    {
        $actor = $this->actor();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CreateIarDraft::class)->create($actor, $this->payload([
            'product_ids' => [],
            'due_at' => now()->subDay()->toDateTimeString(),
        ]));
    }

    public function test_another_global_ra_cannot_view_someone_elses_draft(): void
    {
        $owner = $this->actor();
        $other = $this->actor();
        $iar = app(CreateIarDraft::class)->create($owner, $this->payload());
        $this->actingAs($other)->get(route('iar.show', $iar))->assertForbidden();
    }

    public function test_livewire_form_is_accessible_to_creator(): void
    {
        $actor = $this->actor();
        Livewire::actingAs($actor)->test(CreateDraft::class)->assertOk()->assertSee('New IAR Draft');
    }
}
