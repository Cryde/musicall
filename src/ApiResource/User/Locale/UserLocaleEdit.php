<?php

declare(strict_types=1);

namespace App\ApiResource\User\Locale;

use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\Locale\UserLocaleEditProcessor;
use App\State\Provider\User\Locale\UserLocaleEditProvider;
use App\Validator\User\SupportedLocale;

#[Patch(
    uriTemplate: '/users/self/locale',
    openapi: new Operation(tags: ['Users']),
    security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
    name: 'api_users_self_locale_patch',
    // The URI has no variable, so API Platform would skip the provider on a PATCH and merge into an empty object.
    read: true,
    provider: UserLocaleEditProvider::class,
    processor: UserLocaleEditProcessor::class,
)]
class UserLocaleEdit
{
    #[SupportedLocale]
    public string $locale;
}
