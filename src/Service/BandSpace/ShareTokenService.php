<?php declare(strict_types=1);

namespace App\Service\BandSpace;

/**
 * Mints the secret half of a public Band Space URL, for the file share links and for the agenda
 * iCal feed.
 *
 * Only the sha256 is ever stored, so a database dump does not hand over working links. The plaintext
 * token exists once, in the response that created it, which is why both callers surface it to the
 * user immediately and never read it back.
 */
final class ShareTokenService
{
    private const int TOKEN_BYTES = 32;

    /**
     * @return array{token: string, hash: string}
     */
    public function generate(): array
    {
        $token = $this->base64UrlEncode(random_bytes(self::TOKEN_BYTES));

        return [
            'token' => $token,
            'hash' => $this->hashOf($token),
        ];
    }

    public function hashOf(string $token): string
    {
        return hash('sha256', $token);
    }

    public function verify(string $incoming, string $storedHash): bool
    {
        return hash_equals($storedHash, $this->hashOf($incoming));
    }

    private function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
