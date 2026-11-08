<?php declare(strict_types=1);

namespace App\ApiResource\User;

use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Processor\User\RequestResetPasswordProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/users/request-reset-password',
    openapi: new Operation(tags: ['Users']),
    name: 'api_users_request_reset_password',
    processor: RequestResetPasswordProcessor::class,
)]
class RequestResetPassword
{
    // The same cap as the Apple sign-in: it travels in a link the member clicks.
    private const int RETURN_URL_MAX_LENGTH = 2048;

    #[Assert\NotBlank()]
    public string $login;

    /** Where the reset link brings the member back to after signing in. Dropped when it leaves the site. */
    #[Assert\Length(max: self::RETURN_URL_MAX_LENGTH)]
    public ?string $returnUrl = null;
}
