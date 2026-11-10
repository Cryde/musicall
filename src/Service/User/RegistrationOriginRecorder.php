<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Enum\User\RegistrationMethod;
use App\Enum\User\RegistrationPlatform;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Stamps a new account with where and how it was created (#1355), and on the web with the campaign that
 * brought it (#1369). The app names itself in two headers; they are client supplied and only feed
 * statistics, so an unexpected value is ignored, never refused.
 */
readonly class RegistrationOriginRecorder
{
    public const string PLATFORM_HEADER = 'X-Client-Platform';
    public const string VERSION_HEADER = 'X-Client-Version';
    // `1.13.0`, optionally with the build number, `1.13.0+42`: at most 30 characters, within the column.
    private const string VERSION_PATTERN = '/^\d{1,4}(?:\.\d{1,4}){0,3}(?:\+\d{1,10})?\z/';

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function record(User $user, RegistrationMethod $method, RegistrationPlatform $platformWithoutHeader = RegistrationPlatform::Web, ?RegistrationCampaign $campaign = null): void
    {
        $headers = $this->requestStack->getCurrentRequest()?->headers;
        $platform = match ($headers?->get(self::PLATFORM_HEADER)) {
            'ios' => RegistrationPlatform::Ios,
            'android' => RegistrationPlatform::Android,
            default => $platformWithoutHeader,
        };
        $version = $headers?->get(self::VERSION_HEADER);

        $user->registrationMethod = $method;
        $user->registrationPlatform = $platform;
        $user->registrationAppVersion = $platform !== RegistrationPlatform::Web && $version !== null && preg_match(self::VERSION_PATTERN, $version) === 1
            ? $version
            : null;

        // Web only: the apps measure no campaign.
        $webCampaign = $platform === RegistrationPlatform::Web ? $campaign : null;
        $user->registrationUtmSource = $webCampaign?->utmSource;
        $user->registrationUtmMedium = $webCampaign?->utmMedium;
        $user->registrationUtmCampaign = $webCampaign?->utmCampaign;
        $user->registrationReferrerHost = $webCampaign?->referrerHost;
    }
}
