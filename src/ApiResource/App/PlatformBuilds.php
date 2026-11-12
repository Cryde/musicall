<?php

declare(strict_types=1);

namespace App\ApiResource\App;

/** Build numbers of one platform: the `+N` of the app's `pubspec.yaml`, Android's `versionCode` and iOS's `CFBundleVersion`. */
final class PlatformBuilds
{
    public function __construct(
        public int $minSupportedBuild,
        public int $latestBuild,
    ) {
    }
}
