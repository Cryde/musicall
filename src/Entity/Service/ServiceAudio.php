<?php

declare(strict_types=1);

namespace App\Entity\Service;

use App\Enum\Service\BeforeAfterSide;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

/**
 * One half of a service's « Avant, après » (#1410), converted to AAC by ServiceAudioConverter and streamed through
 * the service's own route, so a draft's sound stays its owner's.
 */
#[ORM\Entity]
#[ORM\Table(name: 'service_audio')]
#[ORM\UniqueConstraint(name: 'unique_service_audio_side', columns: ['service_id', 'side'])]
#[Vich\Uploadable]
class ServiceAudio
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'audio')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Service $service;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: BeforeAfterSide::class)]
    public BeforeAfterSide $side;

    /** « Prise de répète », « Mixé et masterisé ». */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    public ?string $title = null;

    /** « Mona Lisa Overdrive, Satellites ». */
    #[ORM\Column(type: Types::STRING, length: 150, nullable: true)]
    public ?string $credit = null;

    #[ORM\Column(type: Types::INTEGER)]
    public int $durationSeconds;

    /**
     * The waveform the player draws: AudioTranscoder::PEAK_COUNT loudness values, 0 to 255.
     *
     * @var list<int>
     */
    #[ORM\Column(type: Types::JSON)]
    public array $peaks = [];

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $size = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $storagePath = null;

    #[Vich\UploadableField(mapping: 'service_audio', fileNameProperty: 'storagePath', size: 'size')]
    public ?File $uploadedFile = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    public function __construct()
    {
        $this->creationDatetime = new DateTimeImmutable();
    }
}
