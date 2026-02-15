<?php

declare(strict_types=1);

namespace App\ApiResource\Publication\PublicationListItem;

class SubCategory
{
    public int $id;
    public string $title;
    public string $slug;
    public string $typeLabel;
    public bool $isCourse;
}
