<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Enum\BandSpace\BandSpaceSearchResultType;
use App\Repository\BandSpace\BandSpaceOpenedItemRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

/**
 * One item a member opened, for the palette's « Ouverts par moi » order (#1047), read only through
 * their own membership. `resourceId` points at one of seven tables, so it is no foreign key: rows are
 * resolved against the live tables on read. The cascade is a safety net, since a membership that
 * leaves or is kicked is kept: those paths delete the history themselves.
 */
#[ORM\Entity(repositoryClass: BandSpaceOpenedItemRepository::class)]
#[ORM\Table(name: 'band_space_opened_item')]
#[ORM\UniqueConstraint(name: 'unq_band_space_opened_item', columns: ['membership_id', 'type', 'resource_id'])]
#[ORM\Index(name: 'idx_band_space_opened_item_latest', columns: ['membership_id', 'opened_datetime'])]
class BandSpaceOpenedItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public UuidInterface|string|null $id = null {
        get {
            return is_string($this->id) ? $this->id : $this->id?->toString();
        }
    }

    #[ORM\ManyToOne(targetEntity: BandSpaceMembership::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public BandSpaceMembership $membership;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: BandSpaceSearchResultType::class)]
    public BandSpaceSearchResultType $type;

    #[ORM\Column(type: Types::GUID)]
    public string $resourceId;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $openedDatetime;
}
