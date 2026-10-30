<?php declare(strict_types=1);

namespace App\Entity\BandSpace;

use App\Repository\BandSpace\LyricsRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/**
 * A song's lyrics, apart from the song so a band can write them before the song exists (#1264).
 * The link is only mapped on this side: an inverse OneToOne on Song would load on every song hydrated.
 */
#[ORM\Entity(repositoryClass: LyricsRepository::class)]
#[ORM\Table(name: 'band_space_lyrics')]
class Lyrics
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: BandSpace::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public BandSpace $bandSpace;

    #[ORM\OneToOne(targetEntity: Song::class)]
    #[ORM\JoinColumn(unique: true, nullable: true, onDelete: 'SET NULL')]
    public ?Song $song = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    public string $title;

    /** ChordPro, with singers as `<span singer="@[userId]">` (#1055). See ChordProParser. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $content = null;

    /** Bumped on every real change, so a save from a stale copy is refused rather than lost. */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    public int $version = 1;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTimeInterface $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $updateDatetime = null;

    public function __construct()
    {
        $this->creationDatetime = new DateTime();
    }
}
