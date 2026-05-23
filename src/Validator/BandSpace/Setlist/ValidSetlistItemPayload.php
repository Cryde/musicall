<?php declare(strict_types=1);

namespace App\Validator\BandSpace\Setlist;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidSetlistItemPayload extends Constraint
{
    public const string ERROR_CODE = 'music_all_4e2d1b8a-9c7f-4a05-bd62-1a3e7f0c5b2d';

    public string $songIdRequiredMessage = "Un song_id est requis pour un item de type 'song'";
    public string $songIdForbiddenMessage = "Le champ song_id n'est autorisé que pour un item de type 'song'";
    public string $labelRequiredMessage = "Un libellé est requis pour ce type d'item";
    public string $labelForbiddenMessage = "Le champ label n'est pas autorisé pour un item de type 'song'";

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
