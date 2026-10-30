<?php

declare(strict_types=1);

namespace App\Service\OAuth\Apple;

/**
 * One web sign-in with Apple, from the redirect to the callback (#1271), kept in a cookie because
 * the callback is a cross-site POST the session cookie does not come with.
 *
 * The state ties the callback to the browser that started it; the nonce ties Apple's identity token
 * to that same attempt. The return URL is checked again where it is used, so the cookie carries no
 * signature: it is a `__Host-` cookie, which neither another origin nor a sibling subdomain can set.
 */
readonly class AppleSignInAttempt
{
    public function __construct(
        public string $state,
        public string $nonce,
        public ?string $returnUrl,
    ) {
    }

    public static function start(?string $returnUrl): self
    {
        return new self(bin2hex(random_bytes(32)), bin2hex(random_bytes(32)), $returnUrl);
    }

    public static function fromCookie(string $value): ?self
    {
        $data = json_decode((string) base64_decode(strtr($value, '-_', '+/'), true), true);
        if (!is_array($data)) {
            return null;
        }
        $state = $data['state'] ?? null;
        $nonce = $data['nonce'] ?? null;
        $returnUrl = $data['return_url'] ?? null;
        if (!is_string($state) || $state === '' || !is_string($nonce) || $nonce === '' || !(is_string($returnUrl) || $returnUrl === null)) {
            return null;
        }

        return new self($state, $nonce, $returnUrl);
    }

    public function toCookie(): string
    {
        return rtrim(strtr(base64_encode((string) json_encode([
            'state' => $this->state,
            'nonce' => $this->nonce,
            'return_url' => $this->returnUrl,
        ])), '+/', '-_'), '=');
    }
}
