<?php

declare(strict_types=1);

namespace App\ApiResource\User\Profile;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\User\Profile\PublicProfileProvider;

#[Get(
    uriTemplate: '/user/profile/{username}',
    openapi: new Operation(tags: ['Profile']),
    name: 'api_public_profile_get',
    provider: PublicProfileProvider::class,
)]
class PublicProfile
{
    #[ApiProperty(identifier: true)]
    public string $username;

    public string $userId;

    public ?string $bio = null;

    public ?string $location = null;

    public ?string $profilePictureUrl = null;

    public ?string $coverPictureUrl = null;

    public \DateTimeInterface $memberSince;

    /** @var PublicProfileSocialLink[] */
    #[ApiProperty(genId: false)]
    public array $socialLinks = [];

    /** @var PublicProfileAnnounce[] */
    #[ApiProperty(genId: false)]
    public array $musicianAnnounces = [];
}
