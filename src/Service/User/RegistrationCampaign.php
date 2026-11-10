<?php

declare(strict_types=1);

namespace App\Service\User;

/**
 * The campaign and the referring site of the visit a web account was created in (#1369). Client supplied and
 * only feeding statistics, so a value that is not what a browser sends is dropped, never refused. Lowercased,
 * so `Google` and `google` are one source.
 */
final readonly class RegistrationCampaign
{
    private const int UTM_MAX_LENGTH = 100;
    private const int HOST_MAX_LENGTH = 253;
    // Letters of any script, digits and the separators campaign names use. No parentheses: the dashboard's
    // « (direct) » can then never be a real source.
    private const string UTM_PATTERN = "/^[\\p{L}\\p{M}\\p{N} ._+\\-\\/:|']+\\z/u";
    private const string HOST_PATTERN = '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)*\z/';
    // Where a failed Google or Apple sign-in sends the visitor back from (their authorize pages), not a site that
    // brought them. The token and userinfo endpoints are called by the server, never a referrer. Same list in
    // assets/js/utils/landingAttribution.js.
    private const array SIGN_IN_HOSTS = ['accounts.google.com', 'appleid.apple.com'];

    private function __construct(
        public ?string $utmSource,
        public ?string $utmMedium,
        public ?string $utmCampaign,
        public ?string $referrerHost,
    ) {
    }

    /**
     * @param array<mixed> $fields named as the sign-up body and the sign-in start URL name them
     */
    public static function fromFields(array $fields): self
    {
        return new self(
            self::utm($fields['utm_source'] ?? null),
            self::utm($fields['utm_medium'] ?? null),
            self::utm($fields['utm_campaign'] ?? null),
            self::host($fields['referrer_host'] ?? null),
        );
    }

    /**
     * @return array{utm_source: ?string, utm_medium: ?string, utm_campaign: ?string, referrer_host: ?string}
     */
    public function toFields(): array
    {
        return [
            'utm_source' => $this->utmSource,
            'utm_medium' => $this->utmMedium,
            'utm_campaign' => $this->utmCampaign,
            'referrer_host' => $this->referrerHost,
        ];
    }

    public function isEmpty(): bool
    {
        return $this->utmSource === null && $this->utmMedium === null && $this->utmCampaign === null && $this->referrerHost === null;
    }

    private static function utm(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        // The pattern first: it also refuses invalid UTF-8, which mb_strtolower() would quietly rewrite.
        if (preg_match(self::UTM_PATTERN, $value) !== 1) {
            return null;
        }
        $value = mb_strtolower($value);

        return mb_strlen($value) <= self::UTM_MAX_LENGTH ? $value : null;
    }

    private static function host(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = strtolower(trim($value));

        return strlen($value) <= self::HOST_MAX_LENGTH && preg_match(self::HOST_PATTERN, $value) === 1 && !in_array($value, self::SIGN_IN_HOSTS, true)
            ? $value
            : null;
    }
}
