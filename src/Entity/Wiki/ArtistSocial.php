<?php

namespace App\Entity\Wiki;

use App\Repository\Wiki\ArtistSocialRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ArtistSocialRepository::class)]
class ArtistSocial
{
    const SOCIAL_URL_WEBSITE = 0;
    const SOCIAL_URL_TWITTER = 1;
    const SOCIAL_URL_INSTAGRAM = 2;
    const SOCIAL_URL_FACEBOOK = 3;
    const SOCIAL_URL_YOUTUBE = 4;

    const AVAILABLE_SOCIAL_URL_TYPES = [
        self::SOCIAL_URL_WEBSITE,
        self::SOCIAL_URL_TWITTER,
        self::SOCIAL_URL_INSTAGRAM,
        self::SOCIAL_URL_FACEBOOK,
        self::SOCIAL_URL_YOUTUBE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private $id;

    #[ORM\Column(type: Types::SMALLINT)]
    private $type;

    #[Assert\Url]
    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING, length: 255)]
    private $url;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $creationDatetime;

    #[ORM\ManyToOne(targetEntity: Artist::class, inversedBy: "socials")]
    #[ORM\JoinColumn(nullable: false)]
    private $artist;

    public function __construct()
    {
        $this->creationDatetime = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getCreationDatetime(): ?\DateTimeInterface
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(\DateTimeInterface $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    public function getArtist(): ?Artist
    {
        return $this->artist;
    }

    public function setArtist(?Artist $artist): self
    {
        $this->artist = $artist;

        return $this;
    }
}
