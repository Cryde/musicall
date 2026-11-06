<?php

declare(strict_types=1);

namespace App\Service\Teacher;

use App\ApiResource\Teacher\Private\Input\LevelGoal;
use App\ApiResource\Teacher\Private\Input\Song;
use App\ApiResource\Teacher\Private\TeacherProfileInput;
use App\Entity\Teacher\TeacherProfile;

/**
 * Writes « Ce que tu vas apprendre » and « Morceaux travaillés » (#1312) for both the create and the edit processor.
 * A field left out of the request keeps what is stored: the Android app saves the whole profile without them.
 */
readonly class TeacherLessonContentWriter
{
    public function write(TeacherProfile $profile, TeacherProfileInput $data): void
    {
        if (is_array($data->levelGoals)) {
            $profile->levelGoals = $this->levelGoals($data->levelGoals);
        }
        if (is_array($data->songs)) {
            $profile->songs = $this->songs($data->songs);
        }
    }

    /**
     * @param array<mixed> $goals
     *
     * @return array<string, array{title: string|null, points: list<string>}>
     */
    private function levelGoals(array $goals): array
    {
        $byLevel = [];
        foreach ($goals as $goal) {
            if (!$goal instanceof LevelGoal || $goal->level === null) {
                continue;
            }
            $title = trim((string) $goal->title);
            $points = array_values(array_filter(
                array_map(static fn (mixed $point): string => trim((string) $point), $goal->points),
                static fn (string $point): bool => $point !== '',
            ));
            // A level with neither a title nor a point is not worth keeping.
            if ($title === '' && $points === []) {
                continue;
            }
            $byLevel[$goal->level] = ['title' => $title === '' ? null : $title, 'points' => $points];
        }

        return $byLevel;
    }

    /**
     * @param array<mixed> $songs
     *
     * @return list<array{artist: string|null, title: string}>
     */
    private function songs(array $songs): array
    {
        $list = [];
        foreach ($songs as $song) {
            if (!$song instanceof Song) {
                continue;
            }
            $title = trim((string) $song->title);
            if ($title === '') {
                continue;
            }
            $artist = trim((string) $song->artist);
            $list[] = ['artist' => $artist === '' ? null : $artist, 'title' => $title];
        }

        return $list;
    }
}
