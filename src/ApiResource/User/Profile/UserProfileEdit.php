<?php

declare(strict_types=1);

namespace App\ApiResource\User\Profile;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\Service\User\DisplayNameRules;
use App\State\Processor\User\Profile\UserProfileEditProcessor;
use App\State\Provider\User\Profile\UserProfileEditProvider;
use App\Validator\User\DisplayName;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/user/profile',
            openapi: new Operation(tags: ['Profile']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_profile_get',
            provider: UserProfileEditProvider::class,
        ),
        new Patch(
            uriTemplate: '/user/profile',
            openapi: new Operation(tags: ['Profile']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_profile_edit',
            // No URI variable, so API Platform would not read by default: every field left out of the
            // merge patch then fell back to its default, a private profile becoming public included.
            read: true,
            provider: UserProfileEditProvider::class,
            processor: UserProfileEditProcessor::class,
        ),
    ]
)]
class UserProfileEdit
{
    /** Normalized on the way in, so the rules check what will be stored (#1118). */
    #[Assert\Length(max: 100, maxMessage: 'profile.display_name.too_long')]
    #[DisplayName]
    public ?string $displayName = null {
        set(?string $value) {
            $this->displayName = DisplayNameRules::normalize($value);
        }
    }

    #[Assert\Length(max: 2000, maxMessage: 'profile.bio.too_long')]
    public ?string $bio = null;

    #[Assert\Length(max: 255, maxMessage: 'profile.location.too_long')]
    public ?string $location = null;

    #[Assert\Range(notInRangeMessage: 'profile.latitude.out_of_range', min: -90, max: 90)]
    public ?float $latitude = null;

    #[Assert\Range(notInRangeMessage: 'profile.longitude.out_of_range', min: -180, max: 180)]
    public ?float $longitude = null;

    public bool $isPublic = true;

    public ?string $profilePictureUrl = null;

    public ?string $coverPictureUrl = null;
}
