<?php

declare(strict_types=1);

namespace App\ApiResource\App;

/** Build numbers of one platform: the Android `versionCode`, the `+N` of the app's `pubspec.yaml`. */
final class PlatformBuilds
{
    public function __construct(
        public int $minSupportedBuild,
        public int $latestBuild,
    ) {
    }
}
