<?php

namespace Tests\Unit;

use App\Enums\{AssignmentStrategy,ImpactType,IarStatus};
use PHPUnit\Framework\TestCase;

class DomainEnumTest extends TestCase
{
    public function test_regulatory_options_are_stable(): void
    {
        $this->assertSame('no_impact',ImpactType::NoImpact->value);
        $this->assertSame('re_registration_required',ImpactType::ReRegistrationRequired->value);
        $this->assertSame('notification_required',ImpactType::NotificationRequired->value);
        $this->assertSame('creator',AssignmentStrategy::Creator->value);
        $this->assertSame('needs_attention',IarStatus::NeedsAttention->value);
    }
}
