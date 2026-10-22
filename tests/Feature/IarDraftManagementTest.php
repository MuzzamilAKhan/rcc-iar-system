<?php

namespace Tests\Feature;

use App\Models\ImpactAssessment;
use App\Models\Product;
use App\Models\User;
use App\Services\Iar\CreateIarDraft;
use App\Services\Iar\UpdateIarDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class IarDraftManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function actor(string $role = 'global-ra'): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        return $user;
    }

    private function draft(User $creator): ImpactAssessment
    {
        $product = Product::create(['sap_code' => 'SAP-' . uniqid(), 'product_name' => 'Example Product']);
        return app(CreateIarDraft::class)->create($creator, [
            'ccr_number' => 'CCR-INITIAL',
            'current_version' => 'Current situation',
            'new_version' => 'Proposed change',
            'sap_udi_icc_impacted' => false,
            'labelling_impacted' => true,
            'is_urgent' => false,
            'estimated_implementation_date' => null,
            'due_at' => now()->addDays(30)->format('Y-m-d H:i:s'),
            'product_ids' => [$product->id],
        ]);
    }

    private function changes(ImpactAssessment $iar): array
    {
        return [
            'ccr_number' => 'CCR-UPDATED',
            'current_version' => 'Before editing',
            'new_version' => 'After editing',
            'sap_udi_icc_impacted' => true,
            'labelling_impacted' => false,
            'is_urgent' => true,
            'estimated_implementation_date' => null,
            'due_at' => now()->addDays(45)->format('Y-m-d H:i:s'),
            'product_ids' => $iar->products()->pluck('product_id')->all(),
        ];
    }

    public function test_creator_can_view_and_edit_draft_with_audit(): void
    {
        $creator = $this->actor();
        $iar = $this->draft($creator);
        $second = Product::create(['sap_code' => 'SECOND-SAP', 'product_name' => 'Second product']);
        $data = $this->changes($iar);
        $data['product_ids'][] = $second->id;

        $this->actingAs($creator)->get('/iars')->assertOk()->assertSee($iar->iar_number);
        $this->get('/iars/' . $iar->id . '/edit')->assertOk()->assertSee('Edit Draft');
        app(UpdateIarDraft::class)->update($creator, $iar->id, $data);

        $this->assertSame('CCR-UPDATED', $iar->fresh()->ccr_number);
        $this->assertCount(2, $iar->fresh()->products);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $iar->id, 'action' => 'iar.draft.updated']);
    }

    public function test_global_ra_cannot_edit_another_users_draft(): void
    {
        $owner = $this->actor();
        $other = $this->actor();
        $iar = $this->draft($owner);
        $this->actingAs($other)->get('/iars/' . $iar->id . '/edit')->assertForbidden();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(UpdateIarDraft::class)->update($other, $iar->id, $this->changes($iar));
    }

    public function test_non_draft_status_is_locked(): void
    {
        $owner = $this->actor();
        $iar = $this->draft($owner);
        $iar->update(['status' => 'submitted']);
        $this->actingAs($owner)->get('/iars/' . $iar->id . '/edit')->assertForbidden();
        $this->expectException(ValidationException::class);
        app(UpdateIarDraft::class)->update($owner, $iar->id, $this->changes($iar));
    }

    public function test_list_only_shows_authorized_iars(): void
    {
        $first = $this->actor();
        $second = $this->actor();
        $one = $this->draft($first);
        $two = $this->draft($second);
        $this->actingAs($first)->get('/iars')->assertOk()->assertSee($one->iar_number)->assertDontSee($two->iar_number);
        $admin = $this->actor('super-admin');
        $this->actingAs($admin)->get('/iars')->assertOk()->assertSee($one->iar_number)->assertSee($two->iar_number);
    }

    public function test_livewire_edit_form_renders_for_creator(): void
    {
        $creator = $this->actor();
        $iar = $this->draft($creator);
        Livewire::actingAs($creator)->test(\App\Livewire\Iar\EditDraft::class, ['impactAssessment' => $iar])
            ->assertSee('Edit Draft')->assertSet('ccrNumber', 'CCR-INITIAL');
    }
}
