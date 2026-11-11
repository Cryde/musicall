<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Enum\User\ClientPlatform;
use App\Enum\User\RegistrationMethod;
use App\Http\ClientHeaders;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Stamps a new account with where and how it was created (#1355), and on the web with the campaign that
 * brought it (#1369), from what the client says about itself (ClientHeaders).
 */
readonly class RegistrationOriginRecorder
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public function record(User $user, RegistrationMethod $method, ClientPlatform $platformWithoutHeader = ClientPlatform::Web, ?RegistrationCampaign $campaign = null): void
    {
        $client = ClientHeaders::of($this->requestStack->getCurrentRequest());
        $platform = $client->namedAppOr($platformWithoutHeader);

        $user->registrationMethod = $method;
        $user->registrationPlatform = $platform;
        $user->registrationAppVersion = $client->versionFor($platform);

        // Web only: the apps measure no campaign.
        $webCampaign = $platform === ClientPlatform::Web ? $campaign : null;
        $user->registrationUtmSource = $webCampaign?->utmSource;
        $user->registrationUtmMedium = $webCampaign?->utmMedium;
        $user->registrationUtmCampaign = $webCampaign?->utmCampaign;
        $user->registrationReferrerHost = $webCampaign?->referrerHost;
    }
}
