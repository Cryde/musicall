<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Entity\User;
use App\Repository\BandSpace\SongFileFolderRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/**
 * A folder inside a song's files (#1451). Virtual: it groups the song's attachments and never moves a
 * file in the Files tree, so a file keeps the BandSpaceFolder its member put it in.
 */
#[ORM\Entity(repositoryClass: SongFileFolderRepository::class)]
#[ORM\Table(name: 'band_space_song_file_folder')]
#[ORM\Index(name: 'idx_song_file_folder_parent', columns: ['song_id', 'parent_id'])]
#[ORM\UniqueConstraint(name: 'unique_song_file_folder_sibling_name', columns: ['song_id', 'parent_id', 'name'])]
class SongFileFolder
{
    public const int NAME_MAX_LENGTH = 100;

    /** Levels below the song: `Stems › Batterie › Micros` is as deep as it goes. */
    public const int MAX_DEPTH = 3;

    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Song::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Song $song;

    /** Deleting a folder deletes its sub-folders with it; their files go back to the song's root. */
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    public ?self $parent = null;

    #[ORM\Column(type: Types::STRING, length: self::NAME_MAX_LENGTH)]
    public string $name;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $createdBy;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updateDatetime = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }

    /** 1 for a folder at the song's root, 2 inside it, and so on. */
    public function depth(): int
    {
        $depth = 1;
        for ($folder = $this->parent; $folder instanceof self; $folder = $folder->parent) {
            ++$depth;
        }

        return $depth;
    }
}
