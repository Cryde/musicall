<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Mail\Brevo;

use App\Service\Mail\Brevo\BandSpace\BandSpaceInvitationExistingUserEmail;
use App\Service\Mail\Brevo\BandSpace\BandSpaceInvitationNewUserEmail;
use App\Service\Mail\Brevo\Message\MessageReceivedEmail;
use App\Service\Mail\Brevo\User\EmailVerificationCodeEmail;
use App\Service\Mail\Brevo\User\EmailVerificationReminderEmail;
use App\Service\Mail\Brevo\User\InactivityReminderEmail;
use App\Service\Mail\Brevo\User\ResetPasswordEmail;
use App\Service\Mail\Brevo\User\UsernameChangedEmail;
use App\Service\Mail\Brevo\User\WelcomeEmail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/** The Brevo template each email picks for its recipient's locale: the ids are the ones in the Brevo account. */
class BrevoTemplateLocaleTest extends TestCase
{
    /**
     * @return iterable<string, array{\Closure(MailerInterface, string): void, string, string}>
     */
    public static function emails(): iterable
    {
        yield 'message received' => [
            static fn (MailerInterface $mailer, string $locale) => new MessageReceivedEmail($mailer)
                ->send('to@example.com', 'jane', 'john', 'https://www.musicall.com/messages', $locale),
            '4', '16',
        ];
        yield 'username changed' => [
            static fn (MailerInterface $mailer, string $locale) => new UsernameChangedEmail($mailer)
                ->send('to@example.com', 'jane', 'jane_doe', new \DateTimeImmutable('2026-10-17 21:05'), $locale),
            '7', '18',
        ];
        yield 'verification code' => [
            static fn (MailerInterface $mailer, string $locale) => new EmailVerificationCodeEmail($mailer)
                ->send('to@example.com', 'jane', '123456', $locale),
            '10', '19',
        ];
        yield 'verification reminder' => [
            static fn (MailerInterface $mailer, string $locale) => new EmailVerificationReminderEmail($mailer)
                ->send('to@example.com', 'jane', '123456', 'https://www.musicall.com/verify-email', $locale),
            '11', '21',
        ];
        yield 'inactivity reminder' => [
            static fn (MailerInterface $mailer, string $locale) => new InactivityReminderEmail($mailer)
                ->send('to@example.com', 'jane', '2026-08-01', $locale),
            '8', '20',
        ];
        yield 'reset password' => [
            static fn (MailerInterface $mailer, string $locale) => new ResetPasswordEmail($mailer)
                ->send('to@example.com', 'jane', 'https://www.musicall.com/lost-password/abc', $locale),
            '2', '17',
        ];
        yield 'band space invitation, new user' => [
            static fn (MailerInterface $mailer, string $locale) => new BandSpaceInvitationNewUserEmail($mailer)
                ->send('to@example.com', 'The Rockers', 'https://www.musicall.com/register', $locale),
            '12', '22',
        ];
        yield 'band space invitation, existing user' => [
            static fn (MailerInterface $mailer, string $locale) => new BandSpaceInvitationExistingUserEmail($mailer)
                ->send('to@example.com', 'jane', 'The Rockers', 'https://www.musicall.com/invitations', $locale),
            '13', '23',
        ];
        yield 'welcome' => [
            static fn (MailerInterface $mailer, string $locale) => new WelcomeEmail($mailer)
                ->send('to@example.com', 'jane', $locale),
            '6', '24',
        ];
    }

    /**
     * @param \Closure(MailerInterface, string): void $send
     */
    #[DataProvider('emails')]
    public function test_french_and_english_get_their_own_template_another_locale_the_french_one(\Closure $send, string $frenchId, string $englishId): void
    {
        self::assertSame(['fr' => $frenchId, 'en' => $englishId, 'de' => $frenchId], [
            'fr' => $this->templateIdSentFor($send, 'fr'),
            'en' => $this->templateIdSentFor($send, 'en'),
            'de' => $this->templateIdSentFor($send, 'de'),
        ]);
    }

    /**
     * @param \Closure(MailerInterface, string): void $send
     */
    private function templateIdSentFor(\Closure $send, string $locale): string
    {
        $mailer = new class implements MailerInterface {
            public ?RawMessage $sent = null;

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->sent = $message;
            }
        };
        $send($mailer, $locale);
        self::assertInstanceOf(Email::class, $mailer->sent);

        return $mailer->sent->getHeaders()->get('templateId')?->getBodyAsString() ?? '';
    }
}
