<?php declare(strict_types=1);

namespace App\Validator\BandSpace\Setlist;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidSetlistItemPayload extends Constraint
{
    public const string ERROR_CODE = 'music_all_4e2d1b8a-9c7f-4a05-bd62-1a3e7f0c5b2d';

    public string $songIdRequiredMessage = 'band_space.setlist.item.song_id_required';
    public string $songIdForbiddenMessage = 'band_space.setlist.item.song_id_forbidden';
    public string $labelRequiredMessage = 'band_space.setlist.item.label_required';
    public string $labelForbiddenMessage = 'band_space.setlist.item.label_forbidden';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
