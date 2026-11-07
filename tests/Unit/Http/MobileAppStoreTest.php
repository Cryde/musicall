<?php declare(strict_types=1);

namespace App\Tests\Unit\Http;

use App\Http\MobileAppStore;
use PHPUnit\Framework\TestCase;

class MobileAppStoreTest extends TestCase
{
    public function test_an_iphone_and_an_ipad_go_to_the_app_store(): void
    {
        self::assertSame(MobileAppStore::APP_STORE_URL, MobileAppStore::urlFor('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile/15E148'));
        self::assertSame(MobileAppStore::APP_STORE_URL, MobileAppStore::urlFor('Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)'));
    }

    /** iPadOS asks for the desktop site with a Mac user agent. */
    public function test_a_mac_user_agent_goes_to_the_app_store(): void
    {
        self::assertSame(MobileAppStore::APP_STORE_URL, MobileAppStore::urlFor('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15'));
    }

    public function test_an_android_phone_goes_to_the_play_store(): void
    {
        self::assertSame(MobileAppStore::PLAY_STORE_URL, MobileAppStore::urlFor('Mozilla/5.0 (Linux; Android 15; Pixel 8) Chrome/130.0 Mobile Safari/537.36'));
    }

    public function test_anything_else_has_no_store(): void
    {
        self::assertNull(MobileAppStore::urlFor('Mozilla/5.0 (X11; Linux x86_64) Chrome/130.0 Safari/537.36'));
        self::assertNull(MobileAppStore::urlFor(''));
    }
}
