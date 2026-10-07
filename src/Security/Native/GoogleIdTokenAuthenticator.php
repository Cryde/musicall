<?php

declare(strict_types=1);

namespace App\Security\Native;

use App\Entity\SocialAccount;
use App\Exception\OAuth\OAuthEmailExistsException;
use App\Exception\OAuth\OAuthEmailNotVerifiedException;
use App\Service\OAuth\Google\GoogleIdTokenUnverifiableException;
use App\Service\OAuth\Google\GoogleIdTokenVerifierInterface;
use App\Service\OAuth\Google\GoogleUserDataMapper;
use App\Service\OAuth\Google\InvalidGoogleIdTokenException;
use App\Service\OAuth\OAuthUserService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Google sign-in for the app (#1133): an ID token from Credential Manager in, the native session body
 * out, exactly as `/api/native/login_check` answers. The account is found or created the way the web
 * callback does it, and the firewall's UserChecker refuses a suspended one as on the password path.
 *
 * The answer is a machine key the app maps, as the web's OAuth redirects carry: `invalid_id_token`,
 * `email_not_verified`, `email_exists`, and a 503 `google_unavailable` when the token could not be
 * checked at all, so the app says to try again rather than blame the account.
 */
final class GoogleIdTokenAuthenticator extends AbstractAuthenticator
{
    public const string PATH = NativeSurface::PREFIX . 'oauth/google';

    public function __construct(
        private readonly GoogleIdTokenVerifierInterface $verifier,
        private readonly OAuthUserService $oAuthUserService,
        #[Autowire(service: 'app.security.native.authentication_success_handler')]
        private readonly AuthenticationSuccessHandlerInterface $successHandler,
        #[Autowire(service: 'lexik_jwt_authentication.handler.authentication_failure')]
        private readonly AuthenticationFailureHandlerInterface $failureHandler,
        #[Target('native_oauth')]
        private readonly RateLimiterFactoryInterface $nativeOAuthLimiter,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST') && $request->getPathInfo() === self::PATH;
    }

    public function authenticate(Request $request): Passport
    {
        // Every attempt, refused ones included: each costs a round trip to check against Google.
        $limit = $this->nativeOAuthLimiter->create((string) $request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            // In minutes, so the message reads as the password path's does.
            $wait = $limit->getRetryAfter()->getTimestamp() - time();
            throw new TooManyLoginAttemptsAuthenticationException(max(1, (int) ceil($wait / 60)));
        }

        try {
            $claims = $this->verifier->verify(self::idTokenOf($request));
            $result = $this->oAuthUserService->findOrCreateUser(GoogleUserDataMapper::fromClaims($claims), SocialAccount::PROVIDER_GOOGLE);
        } catch (InvalidGoogleIdTokenException) {
            throw new CustomUserMessageAuthenticationException('invalid_id_token');
        } catch (GoogleIdTokenUnverifiableException $exception) {
            throw new GoogleUnavailableException(previous: $exception);
        } catch (OAuthEmailNotVerifiedException) {
            throw new CustomUserMessageAuthenticationException('email_not_verified');
        } catch (OAuthEmailExistsException) {
            throw new CustomUserMessageAuthenticationException('email_exists');
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
        if ($exception instanceof GoogleUnavailableException) {
            return new JsonResponse(
                ['code' => Response::HTTP_SERVICE_UNAVAILABLE, 'message' => $exception->getMessageKey()],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return $this->failureHandler->onAuthenticationFailure($request, $exception);
    }

    private static function idTokenOf(Request $request): string
    {
        try {
            $body = $request->toArray();
        } catch (\Throwable) {
            throw new InvalidGoogleIdTokenException('The body is not JSON');
        }
        $idToken = $body['id_token'] ?? null;
        if (!is_string($idToken) || $idToken === '') {
            throw new InvalidGoogleIdTokenException('No id_token');
        }

        return $idToken;
    }
}
