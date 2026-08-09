<?php declare(strict_types=1);

namespace App\Privacy;

/**
 * The one door a stored band space activity payload goes through on its way into a response DTO.
 *
 * Invitation activities record the invitee's address, and the activity feed is readable by every
 * member while the pending-invitation list is admin only, so that address never leaves the server in
 * full. Masking on the way out rather than at the recording sites also repairs the rows written in
 * plaintext during the beta, with no migration.
 *
 * Every builder that renders a BandSpaceActivity calls this, including the ones whose module cannot
 * carry an address today. Which modules those are is decided by a filter inside a provider, far from
 * here, and a privacy guarantee that depends on remembering that filter is not a guarantee.
 * BandSpaceActivityPayloadMaskCoverageTest is what keeps a new builder from skipping the door.
 */
final class ActivityPayloadMask
{
    private const string EMAIL_KEY = 'email';

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>|null
     */
    public static function mask(?array $payload): ?array
    {
        if ($payload === null || !isset($payload[self::EMAIL_KEY]) || !is_string($payload[self::EMAIL_KEY])) {
            return $payload;
        }

        $payload[self::EMAIL_KEY] = EmailMask::mask($payload[self::EMAIL_KEY]);

        return $payload;
    }
}
