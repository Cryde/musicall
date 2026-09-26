<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Public;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Teacher\FeaturedTeacherCollectionProvider;

#[GetCollection(
    uriTemplate: '/teachers/featured',
    openapi: new Operation(tags: ['Teacher Profile']),
    paginationEnabled: false,
    name: 'api_teachers_featured',
    provider: FeaturedTeacherCollectionProvider::class,
)]
// Not served, only the address of each member. Declared so a username may hold a dot: the implicit
// route's placeholder stops at one, for its format suffix, and a single dotted username made the
// whole list a 500.
#[NotExposed(
    uriTemplate: '/featured_teachers/{username}',
    requirements: ['username' => '[^/]+'],
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
