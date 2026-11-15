<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum\Teacher;

use App\Enum\Service\ServicePlaceType;
use App\Enum\Teacher\LocationType;
use App\Enum\Teacher\SessionDuration;
use PHPUnit\Framework\TestCase;

/** The teacher API keeps its own names over the service model (#1318): each one goes there and comes back. */
class TeacherServiceMappingTest extends TestCase
{
    public function test_each_lesson_duration_comes_back_from_its_minutes(): void
    {
        foreach (SessionDuration::cases() as $duration) {
            $this->assertSame($duration, SessionDuration::fromMinutes($duration->getMinutes()));
        }
    }

    public function test_a_length_no_lesson_has_is_refused(): void
    {
        $this->expectException(\ValueError::class);

        SessionDuration::fromMinutes(45);
    }

    public function test_each_lesson_place_comes_back_from_its_service_place(): void
    {
        $this->assertSame(
            [ServicePlaceType::AtMyPlace, ServicePlaceType::AtClientPlace, ServicePlaceType::Remote],
            array_map(static fn (LocationType $type): ServicePlaceType => $type->placeType(), [LocationType::TEACHER_PLACE, LocationType::STUDENT_PLACE, LocationType::ONLINE]),
        );
        foreach (LocationType::cases() as $type) {
            $this->assertSame($type, LocationType::fromPlaceType($type->placeType()));
        }
    }
}
