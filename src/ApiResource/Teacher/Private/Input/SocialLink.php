<?php

declare(strict_types=1);

namespace App\ApiResource\Teacher\Private\Input;

use Symfony\Component\Validator\Constraints as Assert;

class SocialLink
{
    #[Assert\NotBlank(message: 'La plateforme est obligatoire')]
    #[Assert\Choice(
        choices: ['youtube', 'soundcloud', 'instagram', 'facebook', 'twitter', 'tiktok', 'spotify', 'bandcamp', 'website'],
        message: 'Plateforme invalide : {{ value }}',
    )]
    public ?string $platform = null;

    #[Assert\NotBlank(message: 'L\'URL est obligatoire')]
    #[Assert\Url(message: 'L\'URL n\'est pas valide', requireTld: true)]
    #[Assert\Length(max: 500, maxMessage: 'L\'URL ne doit pas dépasser {{ limit }} caractères')]
    public ?string $url = null;
}
