<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use App\Enum\SocialPlatform;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

trait SocialLinkTrait
{
    #[ORM\Column(type: Types::STRING, length: 20, enumType: SocialPlatform::class)]
    private SocialPlatform $platform;

    #[Assert\NotBlank]
    #[Assert\Url(requireTld: true)]
    #[Assert\Length(max: 500)]
    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $url;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $creationDatetime;

    public function getPlatform(): SocialPlatform
    {
        return $this->platform;
    }

    public function setPlatform(SocialPlatform $platform): self
    {
        $this->platform = $platform;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getCreationDatetime(): DateTimeImmutable
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(DateTimeImmutable $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    protected function initializeSocialLinkTrait(): void
    {
        $this->creationDatetime = new DateTimeImmutable();
    }
}
