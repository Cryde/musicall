<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Service;

use App\Entity\Service\Service;
use App\Entity\Service\TeachingDetails;
use App\Enum\Service\ServiceKind;
use PHPUnit\Framework\TestCase;

class TeachingDetailsTest extends TestCase
{
    public function test_a_service_without_details_reads_the_defaults(): void
    {
        $details = TeachingDetails::of($this->teaching());

        $this->assertSame(
            ['student_levels' => [], 'age_groups' => [], 'level_goals' => [], 'songs' => [], 'offers_trial' => false, 'trial_price' => null],
            $details->toArray(),
        );
    }

    public function test_stored_details_read_back_as_they_were_written(): void
    {
        $stored = [
            'student_levels' => ['beginner', 'advanced'],
            'age_groups' => ['adults'],
            'level_goals' => ['beginner' => ['title' => 'Les bases', 'points' => ['Accords']]],
            'songs' => [['artist' => null, 'title' => 'Jeux interdits']],
            'offers_trial' => true,
            'trial_price' => 0,
        ];
        $service = $this->teaching();
        $service->details = $stored;

        $this->assertSame($stored, TeachingDetails::of($service)->toArray());
    }

    public function test_the_goals_of_a_level_no_longer_taught_are_left_out(): void
    {
        $service = $this->teaching();
        $service->details = [
            'student_levels' => ['advanced', 'beginner'],
            'level_goals' => [
                'advanced' => ['title' => 'Improviser', 'points' => []],
                'intermediate' => ['title' => 'Plus enseigné', 'points' => []],
                'beginner' => ['title' => null, 'points' => ['Accords']],
            ],
        ];

        $this->assertSame([
            ['level' => 'beginner', 'title' => null, 'points' => ['Accords']],
            ['level' => 'advanced', 'title' => 'Improviser', 'points' => []],
        ], TeachingDetails::of($service)->levelGoalList());
    }

    private function teaching(): Service
    {
        $service = new Service();
        $service->kind = ServiceKind::Teaching;

        return $service;
    }
}
