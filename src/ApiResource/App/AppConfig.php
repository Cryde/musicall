<?php

declare(strict_types=1);

namespace App\ApiResource\App;

use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\App\AppConfigProvider;

/**
 * What the mobile app checks on launch (#1132): below `min_supported_build` it asks to update and
 * goes no further, below `latest_build` it only suggests it.
 *
 * Public, and outside every firewall (see `app_config` in security.yaml), so a stale token cannot
 * turn it into a 401.
 *
 * **The shape is a contract with every build already on a phone.** Add fields, never rename or remove
 * one. Plain JSON on purpose: the app reads exactly this body, and JSON-LD would wrap it in keys a
 * strict reader could stumble on. An `ios` block goes next to `android` once there is an iOS build.
 */
#[Get(
    uriTemplate: '/app/config',
    // The app's client asks for JSON-LD on every request, so that type is accepted too and still
    // answered with the plain body: refusing it with a 406 would silently disable the check.
    formats: ['json' => ['application/json', 'application/ld+json']],
    cacheHeaders: ['max_age' => 300, 'shared_max_age' => 300, 'public' => true],
    openapi: new Operation(tags: ['App']),
    name: 'api_app_config',
    provider: AppConfigProvider::class,
)]
final class AppConfig
{
    public function __construct(
        public PlatformBuilds $android,
    ) {
    }
}
