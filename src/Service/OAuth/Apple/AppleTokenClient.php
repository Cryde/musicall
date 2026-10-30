<?php

declare(strict_types=1);

namespace App\Service\OAuth\Apple;

use Firebase\JWT\JWT;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class AppleTokenClient implements AppleTokenClientInterface
{
    private const string TOKEN_PATH = 'auth/token';
    private const string REVOKE_PATH = 'auth/revoke';

    public function __construct(
        #[Target('apple.client')]
        private HttpClientInterface $appleClient,
        private AppleClientSecret $clientSecret,
    ) {
    }

    public function exchangeAuthorizationCode(string $authorizationCode, string $clientId): AppleTokens
    {
        $answer = json_decode($this->post(self::TOKEN_PATH, [
            'client_id' => $clientId,
            'client_secret' => $this->clientSecret->for($clientId),
            'code' => $authorizationCode,
            'grant_type' => 'authorization_code',
        ]), true);
        if (!is_array($answer)) {
            throw new AppleTokenRequestException('Apple answered with something other than JSON');
        }

        $refreshToken = $answer['refresh_token'] ?? null;
        $idToken = $answer['id_token'] ?? null;
        if (!is_string($refreshToken) || $refreshToken === '' || !is_string($idToken)) {
            throw new AppleTokenRequestException('Apple answered without a refresh token');
        }

        return new AppleTokens($refreshToken, self::subjectOf($idToken));
    }

    public function revoke(string $refreshToken, string $clientId): void
    {
        // Apple answers a revocation with an empty 200: there is nothing to read, only a status to check.
        $this->post(self::REVOKE_PATH, [
            'client_id' => $clientId,
            'client_secret' => $this->clientSecret->for($clientId),
            'token' => $refreshToken,
            'token_type_hint' => 'refresh_token',
        ]);
    }

    /**
     * Read without checking the signature: this token came straight from Apple over TLS, in answer to
     * our own authenticated request, which is what vouches for it (OpenID Connect Core 3.1.3.7).
     */
    private static function subjectOf(string $idToken): string
    {
        $segments = explode('.', $idToken);
        try {
            $claims = JWT::jsonDecode(JWT::urlsafeB64Decode($segments[1] ?? ''));
        } catch (\Throwable $exception) {
            throw new AppleTokenRequestException('Apple answered with an unreadable id_token', 0, $exception);
        }
        $subject = is_object($claims) ? ($claims->sub ?? null) : null;
        if (!is_string($subject) || $subject === '') {
            throw new AppleTokenRequestException('Apple answered with an id_token without a subject');
        }

        return $subject;
    }

    /**
     * @param array<string, string> $body
     *
     * @return string the body of a successful answer
     */
    private function post(string $path, array $body): string
    {
        try {
            return $this->appleClient->request('POST', $path, ['body' => $body])->getContent();
        } catch (HttpExceptionInterface $exception) {
            throw new AppleTokenRequestException(sprintf('Apple refused the request: %s', self::reasonOf($exception)), 0, $exception);
        } catch (ExceptionInterface $exception) {
            throw new AppleTokenRequestException('Apple could not be reached', 0, $exception);
        }
    }

    /** Apple names the reason (`invalid_grant`, `invalid_client`), which is what a log reader needs. */
    private static function reasonOf(HttpExceptionInterface $exception): string
    {
        $response = $exception->getResponse();
        try {
            $error = $response->toArray(false)['error'] ?? null;
        } catch (ExceptionInterface) {
            $error = null;
        }

        return is_string($error) ? $error : (string) $response->getStatusCode();
    }
}
