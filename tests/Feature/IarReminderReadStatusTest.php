<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IarReminderReadStatusTest extends TestCase
{
    use RefreshDatabase;

    private function createReminder(int $userId, string $type = 'iar.reminder.creator'): int
    {
        $iarId = DB::table('impact_assessments')->insertGetId([
            'iar_number' => 'IAR-READ-' . uniqid(), 'ccr_number' => 'CCR-READ',
            'creator_id' => $userId, 'current_version' => 'before', 'new_version' => 'after',
            'sap_udi_icc_impacted' => false, 'labelling_impacted' => false, 'is_urgent' => false,
            'due_at' => '2026-12-31 00:00:00', 'status' => 'submitted',
            'submitted_at' => '2026-10-01 09:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('notification_logs')->insertGetId([
            'recipient_user_id' => $userId, 'impact_assessment_id' => $iarId,
            'iar_assignment_id' => null, 'event_type' => $type,
            'idempotency_key' => 'read-test-' . uniqid(), 'status' => 'in_app',
            'sent_at' => null, 'read_at' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_owner_can_mark_unread_reminder_as_read_idempotently(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $id = $this->createReminder($user->id);
        $this->actingAs($user)->get(route('iar.reminders'))->assertOk()
            ->assertSee('1 unread')
            ->assertSee('Mark as read');
        $this->actingAs($user)->post(route('iar.reminders.read', $id))->assertRedirect(route('iar.reminders'));
        $first = DB::table('notification_logs')->where('id', $id)->value('read_at');
        $this->assertNotNull($first);
        $this->actingAs($user)->post(route('iar.reminders.read', $id))->assertRedirect(route('iar.reminders'));
        $this->assertSame($first, DB::table('notification_logs')->where('id', $id)->value('read_at'));
        $this->actingAs($user)->get(route('iar.reminders'))->assertOk()->assertSee('0 unread');
    }

    public function test_other_users_cannot_mark_reminders_as_read(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $other = User::factory()->create(['is_active' => true]);
        $id = $this->createReminder($owner->id);
        $this->actingAs($other)->post(route('iar.reminders.read', $id))->assertNotFound();
        $this->assertNull(DB::table('notification_logs')->where('id', $id)->value('read_at'));
    }

    public function test_unrelated_notification_types_are_not_markable(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $id = $this->createReminder($user->id, 'other.event');
        $this->actingAs($user)->post(route('iar.reminders.read', $id))->assertNotFound();
        $this->assertNull(DB::table('notification_logs')->where('id', $id)->value('read_at'));
    }
}
