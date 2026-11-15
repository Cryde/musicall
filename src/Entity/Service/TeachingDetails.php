<?php

declare(strict_types=1);

namespace App\Entity\Service;

use App\Enum\Teacher\StudentLevel;

/**
 * What only a teaching service shows, stored in Service::$details (#1318). Reading tolerates a missing key, so a
 * field added later needs no migration of the rows already stored.
 */
final class TeachingDetails
{
    /** @var array<string> */
    public array $studentLevels = [];

    /** @var array<string> */
    public array $ageGroups = [];

    /**
     * « Ce que tu vas apprendre » (#1312): per accepted level, a title and up to 3 points, keyed by level.
     *
     * @var array<string, array{title: string|null, points: list<string>}>
     */
    public array $levelGoals = [];

    /**
     * « Morceaux travaillés » (#1312): up to 8 songs, in the teacher's order.
     *
     * @var list<array{artist: string|null, title: string}>
     */
    public array $songs = [];

    public bool $offersTrial = false;

    public ?int $trialPrice = null;

    public static function of(Service $service): self
    {
        $stored = $service->details;
        $details = new self();
        $details->studentLevels = $stored['student_levels'] ?? [];
        $details->ageGroups = $stored['age_groups'] ?? [];
        $details->levelGoals = $stored['level_goals'] ?? [];
        $details->songs = $stored['songs'] ?? [];
        $details->offersTrial = (bool) ($stored['offers_trial'] ?? false);
        $details->trialPrice = $stored['trial_price'] ?? null;

        return $details;
    }

    /** @return array<string, mixed> what Service::$details stores */
    public function toArray(): array
    {
        return [
            'student_levels' => $this->studentLevels,
            'age_groups' => $this->ageGroups,
            'level_goals' => $this->levelGoals,
            'songs' => $this->songs,
            'offers_trial' => $this->offersTrial,
            'trial_price' => $this->trialPrice,
        ];
    }

    /**
     * The goals of the levels still accepted, in teaching order: a level unticked by any client (the app does not edit
     * the goals) never leaves its text behind.
     *
     * @return list<array{level: string, title: string|null, points: list<string>}>
     */
    public function levelGoalList(): array
    {
        $list = [];
        foreach (StudentLevel::cases() as $level) {
            if (in_array($level->value, $this->studentLevels, true) && isset($this->levelGoals[$level->value])) {
                $list[] = ['level' => $level->value, ...$this->levelGoals[$level->value]];
            }
        }

        return $list;
    }
}
