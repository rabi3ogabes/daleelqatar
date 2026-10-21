<?php

namespace Tests\Unit;

use App\Enums\BusinessStatus;
use PHPUnit\Framework\TestCase;

class BusinessStatusTest extends TestCase
{
    public function test_moderation_workflow_transitions(): void
    {
        $this->assertTrue(BusinessStatus::Draft->canTransitionTo(BusinessStatus::Pending));
        $this->assertTrue(BusinessStatus::Pending->canTransitionTo(BusinessStatus::Approved));
        $this->assertTrue(BusinessStatus::Pending->canTransitionTo(BusinessStatus::Rejected));
        $this->assertTrue(BusinessStatus::Approved->canTransitionTo(BusinessStatus::Suspended));
        $this->assertTrue(BusinessStatus::Suspended->canTransitionTo(BusinessStatus::Approved));
        $this->assertTrue(BusinessStatus::Archived->canTransitionTo(BusinessStatus::Draft));
    }

    public function test_illegal_transitions_are_refused(): void
    {
        $this->assertFalse(BusinessStatus::Draft->canTransitionTo(BusinessStatus::Rejected));
        $this->assertFalse(BusinessStatus::Rejected->canTransitionTo(BusinessStatus::Approved));
        $this->assertFalse(BusinessStatus::Suspended->canTransitionTo(BusinessStatus::Pending));
        $this->assertFalse(BusinessStatus::Archived->canTransitionTo(BusinessStatus::Approved));
    }

    public function test_every_status_has_a_badge_tone(): void
    {
        foreach (BusinessStatus::cases() as $status) {
            $this->assertContains($status->tone(), ['neutral', 'warning', 'success', 'danger', 'muted']);
        }
    }
}
