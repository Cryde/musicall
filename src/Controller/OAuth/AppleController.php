<?php

declare(strict_types=1);

namespace App\Controller\OAuth;

use App\Entity\SocialAccount;
use App\Exception\OAuth\OAuthEmailExistsException;
use App\Exception\OAuth\OAuthEmailNotVerifiedException;
use App\Service\OAuth\Apple\AppleIdentityTokenVerifierInterface;
use App\Service\OAuth\Apple\AppleRefreshTokenRecorder;
use App\Service\OAuth\Apple\AppleSignInAttempt;
use App\Service\OAuth\Apple\AppleUserDataMapper;
use App\Service\OAuth\Apple\InvalidAppleIdentityTokenException;
use App\Service\OAuth\OAuthUserService;
use App\Service\OAuth\OAuthWebSignIn;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sign in with Apple on the site (#1271), without KnpU: Apple answers with a cross-site form POST, so
 * the session cookie KnpU keeps its state in never comes back. The attempt rides a cookie of its own
 * instead, SameSite=None so that POST carries it, and the identity token Apple posts is verified like
 * the app's, under the website's Services ID and against the attempt's nonce.
 *
 * `__Host-` rather than `__Secure-`: only `__Host-` forbids a `Domain`, so a sibling subdomain cannot
 * plant an attempt of its own and sign the visitor in to someone else's account.
 */
final class AppleController extends AbstractController
{
    public const string ATTEMPT_COOKIE = '__Host-apple_sign_in';
    private const string ATTEMPT_COOKIE_PATH = '/'; // what `__Host-` requires
    private const int ATTEMPT_LIFETIME = 600; // seconds, plenty for an Apple ID password and a 2FA code
    private const string AUTHORIZE_URL = 'https://appleid.apple.com/auth/authorize';
    // A browser silently drops a cookie over about 4 KB, which would fail the callback for no visible reason.
    private const int RETURN_URL_MAX_LENGTH = 2048;

    public function __construct(
        private readonly OAuthWebSignIn $webSignIn,
        private readonly AppleIdentityTokenVerifierInterface $verifier,
        private readonly OAuthUserService $oAuthUserService,
        private readonly AppleRefreshTokenRecorder $refreshTokenRecorder,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'app.apple.web_client_id')]
        private readonly string $clientId,
    ) {
    }

    #[Route('/oauth/apple', name: 'oauth_apple_start', methods: ['GET'], options: ['expose' => true])]
    public function start(Request $request): RedirectResponse
    {
        if ($this->clientId === '') {
            $this->logger->error('Sign in with Apple on the web is not configured: APPLE_WEB_CLIENT_ID is empty');

            return $this->webSignIn->fail('oauth_failed');
        }

        $returnUrl = self::stringOf($request->query->all(), 'return_url');
        $keepsReturnUrl = $returnUrl !== '' && strlen($returnUrl) <= self::RETURN_URL_MAX_LENGTH && $this->webSignIn->isSafeReturnUrl($returnUrl);
        $attempt = AppleSignInAttempt::start($keepsReturnUrl ? $returnUrl : null);

        $response = new RedirectResponse(self::AUTHORIZE_URL . '?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->generateUrl('oauth_apple_callback', [], UrlGeneratorInterface::ABSOLUTE_URL),
            // `id_token` so the callback carries a token bound to the nonce; Apple insists on a form
            // POST as soon as a name or an email is asked for.
            'response_type' => 'code id_token',
            'response_mode' => 'form_post',
            'scope' => 'name email',
            'state' => $attempt->state,
            'nonce' => $attempt->nonce,
        ], '', '&', PHP_QUERY_RFC3986));
        $response->headers->setCookie(
            Cookie::create(self::ATTEMPT_COOKIE)
                ->withValue($attempt->toCookie())
                ->withExpires(time() + self::ATTEMPT_LIFETIME)
                ->withPath(self::ATTEMPT_COOKIE_PATH)
                ->withSecure(true)
                ->withHttpOnly(true)
                ->withSameSite(Cookie::SAMESITE_NONE)
        );

        return $response;
    }

    #[Route('/oauth/apple/callback', name: 'oauth_apple_callback', methods: ['POST'])]
    public function callback(Request $request): RedirectResponse
    {
        $response = $this->signIn($request);
        // Single use, whatever the outcome.
        $response->headers->clearCookie(self::ATTEMPT_COOKIE, self::ATTEMPT_COOKIE_PATH, null, true, true, Cookie::SAMESITE_NONE);

        return $response;
    }

    private function signIn(Request $request): RedirectResponse
    {
        // Read off the whole bags: a field sent as an array is then just missing, where getString()
        // would answer a 400 instead of the redirect every other failure gets.
        $form = $request->request->all();
        $attempt = AppleSignInAttempt::fromCookie(self::stringOf($request->cookies->all(), self::ATTEMPT_COOKIE));
        if (!$attempt instanceof AppleSignInAttempt || !hash_equals($attempt->state, self::stringOf($form, 'state'))) {
            $this->logger->warning('A Sign in with Apple callback did not match an attempt started by this browser');

            return $this->webSignIn->fail('oauth_failed');
        }

        // Apple's own refusal, `user_cancelled_authorize` when the person closes its page.
        if (isset($form['error'])) {
            return $this->webSignIn->fail('oauth_failed');
        }

        try {
            $claims = $this->verifier->verify(self::stringOf($form, 'id_token'), $this->clientId);
            if (!is_string($claims['nonce'] ?? null) || !hash_equals($attempt->nonce, $claims['nonce'])) {
                throw new InvalidAppleIdentityTokenException('The identity token belongs to another attempt');
            }
            [$givenName, $familyName] = self::nameOf(self::stringOf($form, 'user'));
            $userData = AppleUserDataMapper::fromClaims($claims, $givenName, $familyName);
            $result = $this->oAuthUserService->findOrCreateUser($userData, SocialAccount::PROVIDER_APPLE);

            $authorizationCode = self::stringOf($form, 'code');
            if ($authorizationCode !== '') {
                $this->refreshTokenRecorder->record($userData->id, $authorizationCode, $this->clientId);
            }

            return $this->webSignIn->complete($result->user, $this->webSignIn->redirectUrlFor($attempt->returnUrl), $request);
        } catch (OAuthEmailExistsException) {
            return $this->webSignIn->fail('email_exists');
        } catch (OAuthEmailNotVerifiedException) {
            return $this->webSignIn->fail('email_not_verified');
        } catch (\Exception $exception) {
            // Apple's token refused or out of reach, and anything else, as the Google callback does:
            // a cross-site POST lands on the login page with a message, never on an error page.
            $this->logger->warning('Sign in with Apple failed', ['exception' => $exception]);

            return $this->webSignIn->fail('oauth_failed');
        }
    }

    /** @param array<mixed> $fields */
    private static function stringOf(array $fields, string $name): string
    {
        $value = $fields[$name] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * The `user` field Apple posts on the first sign-in only: `{"name": {"firstName", "lastName"}, "email"}`.
     * Unsigned, so only the name is read from it, and the mapper holds it to the profile name rules.
     *
     * @return array{?string, ?string}
     */
    private static function nameOf(string $user): array
    {
        $decoded = json_decode($user, true);
        $name = is_array($decoded) ? ($decoded['name'] ?? null) : null;
        if (!is_array($name)) {
            return [null, null];
        }
        $firstName = $name['firstName'] ?? null;
        $lastName = $name['lastName'] ?? null;

        return [is_string($firstName) ? $firstName : null, is_string($lastName) ? $lastName : null];
    }
}
