<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Mail\Brevo;

use App\Service\Mail\Brevo\BrevoTemplateId;
use PHPUnit\Framework\TestCase;

class BrevoTemplateIdTest extends TestCase
{
    public function test_a_french_recipient_gets_the_french_template(): void
    {
        self::assertSame('4', BrevoTemplateId::forLocale(['fr' => '4'], 'fr'));
    }

    public function test_a_locale_without_its_own_template_falls_back_to_the_french_one(): void
    {
        self::assertSame('4', BrevoTemplateId::forLocale(['fr' => '4'], 'en'));
    }

    public function test_a_locale_with_its_own_template_gets_it(): void
    {
        self::assertSame('15', BrevoTemplateId::forLocale(['fr' => '4', 'en' => '15'], 'en'));
    }
}
