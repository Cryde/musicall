<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Mail\Brevo\User;

use App\Service\Mail\Brevo\User\UsernameChangedEmail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\ParameterizedHeader;
use Symfony\Component\Mime\RawMessage;

class UsernameChangedEmailTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function changeDates(): iterable
    {
        yield 'French' => ['fr', '17/10/2026 à 21:05'];
        yield 'English' => ['en', '17/10/2026 at 21:05'];
        yield 'a locale without its own format reads the French one' => ['de', '17/10/2026 à 21:05'];
    }

    #[DataProvider('changeDates')]
    public function test_the_change_date_reads_in_the_recipient_locale(string $locale, string $changedAt): void
    {
        $mailer = new class implements MailerInterface {
            public ?RawMessage $sent = null;

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->sent = $message;
            }
        };

        new UsernameChangedEmail($mailer)
            ->send('to@example.com', 'jane', 'jane_doe', new \DateTimeImmutable('2026-10-17 21:05'), $locale);

        self::assertInstanceOf(Email::class, $mailer->sent);
        $params = $mailer->sent->getHeaders()->get('params');
        self::assertInstanceOf(ParameterizedHeader::class, $params);
        self::assertSame(
            ['old_username' => 'jane', 'new_username' => 'jane_doe', 'changed_at' => $changedAt],
            $params->getParameters(),
        );
    }
}
