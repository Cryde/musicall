<?php

declare(strict_types=1);

namespace App\ApiResource\User\Profile;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\User\Profile\PublicProfileProvider;

#[Get(
    uriTemplate: '/user/profile/{username}',
    requirements: ['username' => '(?!social-links$|cover-picture$)[^/]+'],
    openapi: new Operation(tags: ['Profile']),
    name: 'api_public_profile_get',
    provider: PublicProfileProvider::class,
)]
class PublicProfile
{
    #[ApiProperty(identifier: true)]
    public string $username;

    public string $userId;

    public ?string $displayName = null;

    public ?string $bio = null;

    public ?string $location = null;

    public ?string $profilePictureUrl = null;

    public ?string $profilePictureLargeUrl = null;

    public ?string $coverPictureUrl = null;

    public \DateTimeInterface $memberSince;

    /** Always true for a visitor; false only for its owner, who still sees their private profile (#1317). */
    public bool $isPublic = true;

    /** Only for its owner, whose checklist asks for a first post (#1317); left out for anyone else. */
    public ?bool $hasPosts = null;

    /** @var PublicProfileSocialLink[] */
    #[ApiProperty(genId: false)]
    public array $socialLinks = [];

    /** @var PublicProfileAnnounce[] */
    #[ApiProperty(genId: false)]
    public array $musicianAnnounces = [];

    public bool $hasMusicianProfile = false;

    public bool $hasTeacherProfile = false;

    #[ApiProperty(genId: false)]
    public ?PublicProfileMusician $musician = null;

    #[ApiProperty(genId: false)]
    public ?PublicProfileTeacher $teacher = null;
}
