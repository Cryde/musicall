<?php

declare(strict_types=1);

namespace App\Mercure;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Authorization;

/**
 * Hands the browser the token that lets it subscribe to its own Mercure topics.
 *
 * A subscription is an EventSource, which cannot set a header, so the token travels as a cookie on
 * the hub's own path. It is minted wherever a JWT is (login, token refresh, the OAuth callback) and
 * given the same life, so the refresh the frontend already performs renews both.
 *
 * The grant is a flat topic list, which the component normalises into a single subscribe grant: this
 * token can read one user's notifications and cannot publish anything at all.
 */
readonly class MercureSubscriberCookie
{
    public function __construct(
        private Authorization $authorization,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Never throws, deliberately, and `\Throwable` rather than `\Exception` because a misconfigured
     * hub surfaces as a `TypeError` as readily as an exception.
     *
     * Everything that can fail here is a deployment mistake found on the first login after a
     * release, `MERCURE_URL` or `MERCURE_JWT_SECRET` missing from the server's shared `.env` being
     * the likely one, since Deployer shares those files rather than deploying them. None of it is a
     * reason to stop somebody signing in. Letting it escape would be worse than it looks on the
     * OAuth path, where the call sits inside AbstractOAuthController::callback()'s own `catch`, so a
     * valid Google account would be bounced to /login?oauth_error=oauth_failed. Realtime silently
     * degrading to the existing poll is the cheaper failure, and the log line reaches Sentry.
     */
    public function attachTo(Response $response, User $user, Request $request): void
    {
        try {
            $response->headers->setCookie(
                $this->authorization->createCookie($request, [MercureTopic::userNotifications($user->id)])
            );
        } catch (\Throwable $throwable) {
            $this->logger->error('Could not issue a Mercure subscriber cookie, realtime will fall back to polling', [
                'exception' => $throwable,
                'user_id' => $user->id,
            ]);
        }
    }

    /**
     * Takes the cookie back out of the browser, on the same name, path and domain the component
     * derived when it put it there, rather than on three values retyped at the call site. Those are
     * not constants: the name follows the hub's protocol version, and `__Secure-mercure_access_token`
     * under 1.0 would not clear a `mercureAuthorization` written under 0.x.
     *
     * Nothing is revoked by this. There is no blocklist behind a subscriber token the way there is
     * behind the JWT, so a copy taken beforehand keeps working until it expires, an hour at most.
     * What it does is stop the *browser* holding one, which is the case that matters on a shared
     * machine.
     */
    public function clearFrom(Response $response, Request $request): void
    {
        try {
            $response->headers->setCookie($this->authorization->createClearCookie($request));
        } catch (\Throwable $throwable) {
            $this->logger->error('Could not clear the Mercure subscriber cookie', ['exception' => $throwable]);
        }
    }
}
