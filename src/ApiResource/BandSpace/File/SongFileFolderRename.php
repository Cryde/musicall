<?php declare(strict_types=1);

namespace App\ApiResource\BandSpace\File;

use App\Entity\BandSpace\SongFileFolder;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The body of a song folder PATCH. Nullable so a body that leaves the name out keeps it (#1327).
 */
class SongFileFolderRename
{
    #[Assert\NotBlank(message: 'band_space.folder.name_required', allowNull: true, normalizer: 'trim')]
    #[Assert\Length(max: SongFileFolder::NAME_MAX_LENGTH, maxMessage: 'common.name.too_long')]
    public ?string $name = null;
}
