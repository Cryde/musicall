<?php

declare(strict_types=1);

namespace App\Tests\Double;

use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Google's side of a web sign-in, standing in for the KnpU client: the redirect carries the state it was
 * given, and the callback answers with the person a test names, without any check of the code or the state.
 */
final readonly class FakeGoogleOAuthClient implements OAuth2ClientInterface
{
    /** @param array<string, mixed> $claims as Google's userinfo answers them */
    public function __construct(private array $claims)
    {
    }

    public function setAsStateless(): void
    {
    }

    /**
     * @param string[] $scopes
     * @param array<string, mixed> $options
     */
    public function redirect(array $scopes, array $options): RedirectResponse
    {
        return new RedirectResponse('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(['state' => $options['state'] ?? '']));
    }

    /** @param array<string, mixed> $options */
    public function getAccessToken(array $options = []): AccessToken
    {
        return new AccessToken(['access_token' => 'a-google-access-token']);
    }

    public function fetchUserFromToken(AccessToken $accessToken): GoogleUser
    {
        return new GoogleUser($this->claims);
    }

    public function fetchUser(): GoogleUser
    {
        return new GoogleUser($this->claims);
    }

    public function getOAuth2Provider(): AbstractProvider
    {
        throw new \LogicException('The fake has no provider');
    }
}
