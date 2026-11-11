<?php declare(strict_types=1);

namespace App\Http;

use App\Enum\User\ClientPlatform;
use Symfony\Component\HttpFoundation\Request;

/**
 * What a request says about the client that sent it. The apps name themselves in two headers (#1355);
 * both are client supplied and only feed statistics, so an unexpected value is ignored, never refused,
 * and never used for authorization.
 */
final readonly class ClientHeaders
{
    private const string PLATFORM_HEADER = 'X-Client-Platform';
    private const string VERSION_HEADER = 'X-Client-Version';
    // `1.13.0`, optionally with the build number, `1.13.0+42`: at most 30 characters, within the column.
    private const string VERSION_PATTERN = '/^\d{1,4}(?:\.\d{1,4}){0,3}(?:\+\d{1,10})?\z/';

    private function __construct(
        private ?ClientPlatform $namedApp,
        private ?string $version,
        private bool $hasBearerToken,
    ) {
    }

    public static function of(?Request $request): self
    {
        $headers = $request?->headers;
        $namedApp = match ($headers?->get(self::PLATFORM_HEADER)) {
            'ios' => ClientPlatform::Ios,
            'android' => ClientPlatform::Android,
            default => null,
        };
        $version = $headers?->get(self::VERSION_HEADER);

        return new self(
            $namedApp,
            $version !== null && preg_match(self::VERSION_PATTERN, $version) === 1 ? $version : null,
            str_starts_with(strtolower($headers?->get('Authorization') ?? ''), 'bearer '),
        );
    }

    /** The app the request names, or `$default` when it names none. */
    public function namedAppOr(ClientPlatform $default): ClientPlatform
    {
        return $this->namedApp ?? $default;
    }

    /**
     * The platform of a signed-in request. Only the apps hold their token in the Authorization header:
     * the web signs in with cookies and can never read the whole token, so a bearer token without the
     * platform header is an app that did not name itself, a build older than the headers or one of its
     * image requests.
     */
    public function signedInPlatform(): ClientPlatform
    {
        return $this->namedApp ?? ($this->hasBearerToken ? ClientPlatform::UnknownApp : ClientPlatform::Web);
    }

    /** The web sends no version, and any it claims is dropped. */
    public function versionFor(ClientPlatform $platform): ?string
    {
        return $platform === ClientPlatform::Web ? null : $this->version;
    }
}
