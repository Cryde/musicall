<?php

declare(strict_types=1);

namespace App\ApiResource\Service\View;

use ApiPlatform\Metadata\ApiProperty;

/** One of « Réalisations » (#1407). */
class ServiceWorkView
{
    public string $id;

    public string $title = '';

    public ?string $credit = null;

    public ?int $year = null;

    public ?string $linkUrl = null;

    #[ApiProperty(writable: false, genId: false)]
    public ?ServiceWorkMedia $media = null;

    /** @var list<ServiceWorkImageView> in the owner's order */
    #[ApiProperty(writable: false, genId: false)]
    public array $images = [];
}
