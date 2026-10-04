<?php declare(strict_types=1);

namespace App\ApiResource\User\DeviceToken;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\User\DeviceToken;
use App\Enum\User\DevicePlatform;
use App\State\Processor\User\DeviceToken\DeviceTokenDeleteProcessor;
use App\State\Processor\User\DeviceToken\DeviceTokenRegisterProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Where the mobile app registers its FCM token after sign in, and takes it back on sign out (#1109).
 *
 * POST is an idempotent upsert answering 201 every time, like the chat reaction POST: the app
 * re-registers on each launch and must not have to tell a first time from a repeat.
 */
#[ApiResource(
    shortName: 'DeviceToken',
    operations: [
        new Post(
            uriTemplate: '/user/device-tokens',
            openapi: new Operation(tags: ['User']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            name: 'api_user_device_tokens_post',
            processor: DeviceTokenRegisterProcessor::class,
        ),
        new Delete(
            uriTemplate: '/user/device-tokens/{token}',
            requirements: ['token' => self::TOKEN_PATTERN],
            openapi: new Operation(tags: ['User']),
            security: 'is_granted("IS_AUTHENTICATED_REMEMBERED")',
            // The processor resolves the token against the caller, which no provider could do cheaper.
            read: false,
            name: 'api_user_device_tokens_delete',
            processor: DeviceTokenDeleteProcessor::class,
        ),
        // Not served, only the @id of a registered token, pinned to the same path as DELETE.
        new NotExposed(
            uriTemplate: '/user/device-tokens/{token}',
            requirements: ['token' => self::TOKEN_PATTERN],
        ),
    ],
)]
class DeviceTokenResource
{
    /** What FCM tokens are made of. The colon arrives URL encoded on DELETE and is decoded before matching. */
    final public const string TOKEN_PATTERN = '[A-Za-z0-9_:\-]{1,512}';

    #[ApiProperty(identifier: true)]
    #[Assert\NotBlank(message: 'Veuillez fournir un jeton')]
    #[Assert\Length(max: DeviceToken::TOKEN_MAX_LENGTH, maxMessage: 'Le jeton ne peut pas dépasser {{ limit }} caractères')]
    #[Assert\Regex(pattern: '/^[A-Za-z0-9_:\-]+\z/', message: 'Le jeton est invalide')]
    public string $token = '';

    #[Assert\Choice(callback: [DevicePlatform::class, 'values'], message: 'Plateforme inconnue')]
    public string $platform = '';
}
