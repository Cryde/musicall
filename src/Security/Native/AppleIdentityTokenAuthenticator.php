<?php

declare(strict_types=1);

namespace App\Security\Native;

use App\Entity\SocialAccount;
use App\Exception\OAuth\OAuthEmailExistsException;
use App\Exception\OAuth\OAuthEmailNotVerifiedException;
use App\Service\OAuth\Apple\AppleIdentityTokenUnverifiableException;
use App\Service\OAuth\Apple\AppleIdentityTokenVerifierInterface;
use App\Service\OAuth\Apple\AppleRefreshTokenRecorder;
use App\Service\OAuth\Apple\AppleUserDataMapper;
use App\Service\OAuth\Apple\InvalidAppleIdentityTokenException;
use App\Service\OAuth\OAuthUserService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Sign in with Apple for the iOS app (#1269), the Apple twin of GoogleIdTokenAuthenticator: an
 * identity token in, the native session body out, the account found or created the same way and
 * refused with the same keys, plus a 503 `apple_unavailable` when Apple's keys cannot be fetched.
 *
 * The authorization code beside the token is exchanged for the refresh token that has to be revoked
 * when the account is deleted. The name comes only on the first Apple sign-in, and only matters when
 * that sign-in creates the account.
 */
final class AppleIdentityTokenAuthenticator extends AbstractAuthenticator
{
    public const string PATH = NativeSurface::PREFIX . 'oauth/apple';

    public function __construct(
        private readonly AppleIdentityTokenVerifierInterface $verifier,
        private readonly OAuthUserService $oAuthUserService,
        private readonly AppleRefreshTokenRecorder $refreshTokenRecorder,
        #[Autowire(service: 'app.security.native.authentication_success_handler')]
        private readonly AuthenticationSuccessHandlerInterface $successHandler,
        #[Autowire(service: 'lexik_jwt_authentication.handler.authentication_failure')]
        private readonly AuthenticationFailureHandlerInterface $failureHandler,
        private readonly NativeOAuthAttemptLimiter $attemptLimiter,
        #[Autowire(param: 'app.apple.native_client_id')]
        private readonly string $clientId,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST') && $request->getPathInfo() === self::PATH;
    }

    public function authenticate(Request $request): Passport
    {
        $this->attemptLimiter->consume($request);

        try {
            $body = self::bodyOf($request);
            $identityToken = self::stringField($body, 'identity_token')
                ?? throw new InvalidAppleIdentityTokenException('No identity_token');
            $claims = $this->verifier->verify($identityToken, $this->clientId);
            $userData = AppleUserDataMapper::fromClaims($claims, self::stringField($body, 'given_name'), self::stringField($body, 'family_name'));
            $result = $this->oAuthUserService->findOrCreateUser($userData, SocialAccount::PROVIDER_APPLE);
        } catch (InvalidAppleIdentityTokenException) {
            throw new CustomUserMessageAuthenticationException('invalid_id_token');
        } catch (AppleIdentityTokenUnverifiableException $exception) {
            throw new AppleUnavailableException(previous: $exception);
        } catch (OAuthEmailNotVerifiedException) {
            throw new CustomUserMessageAuthenticationException('email_not_verified');
        } catch (OAuthEmailExistsException) {
            throw new CustomUserMessageAuthenticationException('email_exists');
        }

        $authorizationCode = self::stringField($body, 'authorization_code');
        if ($authorizationCode !== null) {
            $this->refreshTokenRecorder->record($userData->id, $authorizationCode, $this->clientId);
        }

        $user = $result->user;

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn () => $user));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->successHandler->onAuthenticationSuccess($request, $token);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof AppleUnavailableException) {
            return new JsonResponse(
                ['code' => Response::HTTP_SERVICE_UNAVAILABLE, 'message' => $exception->getMessageKey()],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return $this->failureHandler->onAuthenticationFailure($request, $exception);
    }

    /** @return array<mixed> */
    private static function bodyOf(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (\Throwable) {
            throw new InvalidAppleIdentityTokenException('The body is not JSON');
        }
    }

    /**
     * Anything but a non-blank string counts as absent: the optional fields are then simply ignored.
     *
     * @param array<mixed> $body
     */
    private static function stringField(array $body, string $field): ?string
    {
        $value = $body[$field] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
