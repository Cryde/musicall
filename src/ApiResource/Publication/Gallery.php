<?php

namespace App\ApiResource\Publication;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\ApiResource\Publication\Publication\Author;
use App\ApiResource\Publication\Publication\Category;
use App\ApiResource\Publication\Publication\Cover;
use App\ApiResource\Publication\Publication\Thread;
use App\ApiResource\Publication\Publication\Type;
use App\State\Provider\Publication\GalleryProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/galleries/{slug}',
            priority: 10,
            name: 'api_gallery_get_item',
            provider: GalleryProvider::class,
        ),
    ]
)]
class Gallery
{
    public string $title;
    #[ApiProperty(genId: false)]
    public Category $category;
    #[ApiProperty(genId: false)]
    public Author $author;
    public string $slug;
    public string $description;
    public string $content;
    public \DateTimeInterface $publicationDatetime;
    #[ApiProperty(genId: false)]
    public Cover $cover;
    #[ApiProperty(genId: false)]
    public ?Thread $thread = null;
    #[ApiProperty(genId: false)]
    public ?Type $type = null;
}