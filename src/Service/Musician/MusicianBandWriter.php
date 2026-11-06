<?php

declare(strict_types=1);

namespace App\Service\Musician;

use App\ApiResource\Musician\MusicianProfileBand;
use App\Entity\Attribute\Style;
use App\Entity\Musician\MusicianProfile;
use App\Repository\Attribute\StyleRepository;

/** Stores the bands of a musician profile (#1313) as the create and the edit processor receive them. */
readonly class MusicianBandWriter
{
    public function __construct(
        private StyleRepository $styleRepository,
    ) {
    }

    /**
     * A null list, left out of the request, keeps what is stored.
     *
     * @param array<mixed>|null $bands
     */
    public function write(MusicianProfile $profile, ?array $bands): void
    {
        if ($bands === null) {
            return;
        }
        $stored = [];
        foreach ($bands as $band) {
            if (!$band instanceof MusicianProfileBand) {
                continue;
            }
            $name = trim((string) $band->name);
            if ($name === '') {
                continue;
            }
            $stored[] = [
                'name' => $name,
                'style_id' => $this->knownStyleId($band->styleId),
                'role' => $this->blankToNull($band->role),
                'current' => $band->current,
                'url' => $this->blankToNull($band->url),
            ];
        }
        $profile->bands = $stored;
    }

    private function knownStyleId(?string $styleId): ?string
    {
        if ($styleId === null || $styleId === '') {
            return null;
        }

        return $this->styleRepository->find($styleId) instanceof Style ? $styleId : null;
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
