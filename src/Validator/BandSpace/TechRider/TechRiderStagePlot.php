<?php declare(strict_types=1);

namespace App\Validator\BandSpace\TechRider;

use Symfony\Component\Validator\Constraint;

/**
 * Whole-document check on a stage plot.
 *
 * The plot is raw JSON authored by a canvas editor, so this is a real boundary: every number is
 * something a dragging bug could put out of range, and the document is read back by an export
 * that has no opportunity to ask questions. Unknown keys are refused rather than ignored, so a
 * client sending `posX` gets a 422 instead of a plot that silently lost its positions.
 */
#[\Attribute]
class TechRiderStagePlot extends Constraint
{
    public const string ERROR_CODE = 'music_all_4c1e7a92-3b58-4d0f-9e14-2a6f8b35d7c0';

    /**
     * Present from the first document written. A later schema change is then a migration over
     * known shapes rather than a guess about what an old plot meant.
     */
    public const int SCHEMA_VERSION = 1;

    public const int MAX_ELEMENTS = 120;
    public const int MAX_LEGEND_ENTRIES = 20;
    public const int MAX_LABEL_LENGTH = 60;

    /**
     * The element id is a short client generated key, not content. Bounded because this constraint
     * is the only one on the plot: unlike a generic content write there is no byte ceiling behind
     * it, so 120 unbounded strings would be 120 unbounded strings.
     */
    public const int MAX_ELEMENT_ID_LENGTH = 64;

    public const float MIN_SCALE = 0.25;
    public const float MAX_SCALE = 4.0;

    public const float MIN_ASPECT_RATIO = 0.5;
    public const float MAX_ASPECT_RATIO = 3.0;

    /**
     * Quarter turns only. The reference rider has sideways labels so rotation is a real need, but
     * arbitrary angles are a trap: dompdf has no CSS transform support at all, and even a
     * Chromium renderer needs care. These four are implementable on any engine #741 might pick.
     */
    public const array ALLOWED_ROTATIONS = [0, 90, 180, 270];

    public string $invalidDocumentMessage = 'Le plan de scène doit être un objet';
    public string $unknownKeyMessage = 'Champ inconnu dans le plan de scène';
    public string $wrongVersionMessage = 'Version de plan de scène non prise en charge';
    public string $invalidListMessage = 'Ce champ doit être une liste';
    public string $tooManyElementsMessage = 'Un plan de scène ne peut pas dépasser {{ limit }} éléments';
    public string $tooManyLegendEntriesMessage = 'La légende ne peut pas dépasser {{ limit }} entrées';
    public string $invalidEntryMessage = 'Cette entrée est mal formée';
    public string $tooLongIdMessage = 'L\'identifiant ne peut pas dépasser {{ limit }} caractères';
    public string $duplicateIdMessage = 'Chaque élément doit avoir un identifiant unique';
    public string $coordinateOutOfRangeMessage = 'La position doit être comprise entre 0 et 1';
    public string $scaleOutOfRangeMessage = 'La taille doit être comprise entre {{ min }} et {{ max }}';
    public string $invalidRotationMessage = 'La rotation doit valoir 0, 90, 180 ou 270';
    public string $tooLongLabelMessage = 'Le libellé ne peut pas dépasser {{ limit }} caractères';
    public string $unknownIconMessage = 'Icône inconnue';
    public string $unknownColourMessage = 'Couleur inconnue';
    public string $aspectRatioOutOfRangeMessage = 'Le format de scène doit être compris entre {{ min }} et {{ max }}';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
