<?php

declare(strict_types=1);

namespace App\Tests\Unit\WellKnown;

use PHPUnit\Framework\TestCase;

/**
 * Nothing else parses this file before Apple's CDN does, and a broken one silently sends every iPhone
 * link to Safari (#1425). The paths are a contract with the installed iOS app.
 */
class AppleAppSiteAssociationTest extends TestCase
{
    public function test_it_is_valid_json_claiming_the_app_links_of_the_ios_app(): void
    {
        $content = file_get_contents(dirname(__DIR__, 3) . '/public/.well-known/apple-app-site-association');
        $this->assertIsString($content);

        $this->assertSame([
            'applinks' => [
                'details' => [
                    [
                        'appIDs' => ['6R99H2Q9DG.com.musicall.app'],
                        'components' => [
                            ['/' => '/band/invitation/*'],
                            ['/' => '/lost-password/*'],
                        ],
                    ],
                ],
            ],
        ], json_decode($content, true, flags: JSON_THROW_ON_ERROR));
    }
}
