<?php

declare(strict_types=1);

namespace App\Entity\Service;

use App\Entity\Trait\SocialLinkTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** A link of the service's own, apart from the profile's: a portfolio or a professional account. */
#[ORM\Entity]
#[ORM\Table(name: 'service_link')]
#[ORM\UniqueConstraint(name: 'unique_service_link_platform', columns: ['service_id', 'platform'])]
class ServiceLink
{
    use SocialLinkTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'links')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Service $service;

    public function __construct()
    {
        $this->initializeSocialLinkTrait();
    }
}
