<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Public;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Teacher\FeaturedTeacherCollectionProvider;

#[GetCollection(
    uriTemplate: '/teachers/featured',
    openapi: new Operation(tags: ['Teacher Profile']),
    paginationEnabled: false,
    name: 'api_teachers_featured',
    provider: FeaturedTeacherCollectionProvider::class,
    //itemUriTemplate: '/user/featured_teachers/{username}'
)]
class FeaturedTeacher
{
    #[ApiProperty(identifier: true)]
    public string $username;

    public ?string $profilePictureUrl = null;

    /** @var TeacherProfileInstrument[] */
    #[ApiProperty(genId: false)]
    public array $instruments = [];

    public bool $offersTrial = false;

    public ?int $trialPrice = null;
}
