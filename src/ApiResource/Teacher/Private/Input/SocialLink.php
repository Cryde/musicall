<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Private\Input;

use Symfony\Component\Validator\Constraints as Assert;

class SocialLink
{
    #[Assert\NotBlank(message: 'profile.teacher.social_link.platform_required')]
    #[Assert\Choice(
        choices: ['youtube', 'soundcloud', 'instagram', 'facebook', 'twitter', 'tiktok', 'spotify', 'bandcamp', 'website'],
        message: 'profile.teacher.social_link.platform_invalid',
    )]
    public ?string $platform = null;

    #[Assert\NotBlank(message: 'profile.teacher.social_link.url_required')]
    #[Assert\Url(message: 'profile.teacher.social_link.url_invalid', requireTld: true)]
    #[Assert\Length(max: 500, maxMessage: 'profile.teacher.social_link.url_too_long')]
    public ?string $url = null;
}
