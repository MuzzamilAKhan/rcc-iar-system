<?php
namespace Tests\Feature;

use App\Services\Iar\ReminderEmailPreview;
use Tests\TestCase;

class IarReminderEmailPreviewTest extends TestCase
{
    public function test_creator_and_assessor_previews_are_scoped_and_do_not_send(): void
    {
        $rows = [[
            'iar_id'=>9, 'iar_number'=>'IAR-UAT-009', 'cycle'=>1,
            'recipients'=>[
                ['role'=>'creator','user_id'=>10,'countries'=>['UK','France']],
                ['role'=>'assessor','user_id'=>11,'countries'=>['UK']],
                ['role'=>'assessor','user_id'=>12,'countries'=>[]],
            ],
        ]];
        $messages = app(ReminderEmailPreview::class)->messages($rows);
        $this->assertCount(2,$messages);
        $this->assertStringContainsString('France',$messages[0]['body']);
        $this->assertStringContainsString('UK',$messages[1]['body']);
        $this->assertStringNotContainsString('France',$messages[1]['body']);
        $this->assertSame(11,$messages[1]['recipient_user_id']);
    }
}
