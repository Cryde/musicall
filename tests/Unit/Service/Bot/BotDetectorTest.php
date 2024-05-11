<?php

namespace App\Tests\Unit\Service\Bot;

use App\Service\Bot\BotDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BotDetectorTest extends TestCase
{
    public function test_is_bot()
    {
        $detector = new BotDetector();
        $this->assertFalse($detector->isBot(''));
        $this->assertTrue($detector->isBot('Twitterbot/1.0'));
        $this->assertTrue($detector->isBot('facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'));
        $this->assertTrue($detector->isBot('facebookexternalhit/1.1'));
        $this->assertTrue($detector->isBot('Mozilla/5.0 (compatible) facebookexternalhit/1.1 (+https://faviconkit.com/robots)'));
        $this->assertTrue($detector->isBot('Mozilla/5.0 (compatible) facebookexternalhit/1.1 (+https://faviconkit.com/robots)'));
        $this->assertTrue($detector->isBot('facebookexternalhit/1.1; kakaotalk-scrap/1.0; +https://devtalk.kakao.com/t/scrap/33984'));
    }

    #[DataProvider('mobileAgentProvider')]
    public function test_is_bot_on_mobile_agent($userAgent): void
    {
        $detector = new BotDetector();
        $this->assertFalse($detector->isBot($userAgent));
    }

    public static function mobileAgentProvider(): array
    {
        $result = json_decode(file_get_contents(__DIR__ . '/fixtures/navigator_agent_strings.json'), true);

        return array_map(fn($a) => [$a], array_column($result, 'ua'));
    }
}