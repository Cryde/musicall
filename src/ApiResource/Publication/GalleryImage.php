<?php

namespace App\ApiResource\Publication;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\ApiResource\Publication\Gallery\Format;
use App\ApiResource\Publication\Publication\Author;
use App\ApiResource\Publication\Publication\Category;
use App\ApiResource\Publication\Publication\Cover;
use App\ApiResource\Publication\Publication\Thread;
use App\ApiResource\Publication\Publication\Type;
use App\State\Provider\Publication\GalleryImageProvider;
use App\State\Provider\Publication\GalleryProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/galleries/{slug}/images',
            uriVariables: ['slug'],
            paginationEnabled: false,
            name: 'api_gallery_get_images',
            provider: GalleryImageProvider::class,
        ),
    ]
)]
class GalleryImage
{
    public int $id;
    //#[ApiProperty(identifier: true)]
    //public string $slug = '';
    #[ApiProperty(genId: false)]
    public Format $format;
}