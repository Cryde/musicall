<?php

declare(strict_types=1);

namespace App\Security\Observability;

use App\Entity\User;
use Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The fields every auth event carries (#1021). Nothing here may hold a raw token, an email, a
 * username or an address: the account is an internal id, the credential a truncated hash.
 */
readonly class AuthContextBuilder
{
    /**
     * Enough of the digest to tell two tokens apart in a query, far too little to reverse.
     */
    private const int HASH_LENGTH = 16;

    private const string REQUEST_ID_ATTRIBUTE = '_auth_request_id';

    public function __construct(
        private RequestStack $requestStack,
        private ExtractorInterface $refreshTokenExtractor,
        private string $tokenParameterName,
        private ?string $release,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(?UserInterface $user = null): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request instanceof Request) {
            return ['release' => $this->release ?: null];
        }

        $presentedToken = $this->presentedRefreshToken($request);

        return [
            'client_kind' => $this->clientKind($request, $presentedToken),
            'cookies_present' => [
                'jwt_hp' => $request->cookies->has('jwt_hp'),
                'jwt_s' => $request->cookies->has('jwt_s'),
                $this->tokenParameterName => $request->cookies->has($this->tokenParameterName),
            ],
            'token_hash' => $presentedToken === null ? null : $this->hash($presentedToken),
            // isset(), because a typed uninitialised property throws on read and a User that never
            // came from Doctrine has no id yet.
            'user_ref' => $user instanceof User && isset($user->id) ? $user->id : null,
            'request_id' => $this->requestId($request),
            'user_agent' => $request->headers->get('User-Agent'),
            'release' => $this->release ?: null,
        ];
    }

    public function hash(string $token): string
    {
        return substr(hash('sha256', $token), 0, self::HASH_LENGTH);
    }

    /** The bundle's own extractor, so this agrees with what the authenticator read. */
    public function presentedRefreshToken(Request $request): ?string
    {
        $token = $this->refreshTokenExtractor->getRefreshToken($request, $this->tokenParameterName);

        return $token === '' ? null : $token;
    }

    /**
     * What separates web from native on any event carrying a credential. A login is `none`: it
     * presents a password, not a credential this can read.
     */
    private function clientKind(Request $request, ?string $presentedToken): string
    {
        if ($request->headers->has('Authorization')) {
            return 'authorization_header';
        }

        if ($presentedToken !== null && !$request->cookies->has($this->tokenParameterName)) {
            return 'body';
        }

        if ($request->cookies->has('jwt_hp') || $request->cookies->has('jwt_s') || $request->cookies->has($this->tokenParameterName)) {
            return 'cookie';
        }

        return 'none';
    }

    /**
     * Kept on the Request, not on this service: a FrankenPHP worker outlives the request, so a
     * property here would leak the previous caller's id into the next one.
     */
    private function requestId(Request $request): string
    {
        $existing = $request->attributes->get(self::REQUEST_ID_ATTRIBUTE);

        if (is_string($existing)) {
            return $existing;
        }

        $requestId = bin2hex(random_bytes(8));
        $request->attributes->set(self::REQUEST_ID_ATTRIBUTE, $requestId);

        return $requestId;
    }
}
