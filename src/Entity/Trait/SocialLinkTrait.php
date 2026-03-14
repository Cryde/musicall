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
    public SocialPlatform $platform;

    #[Assert\NotBlank]
    #[Assert\Url(requireTld: true)]
    #[Assert\Length(max: 500)]
    #[ORM\Column(type: Types::STRING, length: 500)]
    public string $url;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $creationDatetime;

    protected function initializeSocialLinkTrait(): void
    {
        $this->creationDatetime = new DateTimeImmutable();
    }
}
