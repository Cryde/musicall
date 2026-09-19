<?php

declare(strict_types=1);

namespace App\Tests\Api\Security\Observability;

use App\Entity\RefreshToken;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\BrowserKit\Cookie as BrowserKitCookie;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * End to end proof that the auth channel carries what #1021 says it does.
 *
 * The unit tests pin the shape of each event; what these cannot be replaced by is the question the
 * brief calls out as the trap: whether an **info** level record actually leaves the application. The
 * Sentry handler's floor is info precisely because successes are the denominator, and a warning
 * floor would leave a failure count with nothing to divide it by. Every assertion here therefore
 * insists on Level::Info rather than merely on the record existing.
 */
#[ResetDatabase]
class AuthObservabilityTest extends ApiTestCase
{
    private TestHandler $authRecords;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authRecords = new TestHandler();
        $logger = static::getContainer()->get('monolog.logger.auth');
        self::assertInstanceOf(Logger::class, $logger);
        $logger->pushHandler($this->authRecords);
    }

    public function test_a_login_lands_on_the_auth_channel_at_info(): void
    {
        UserFactory::new()->asBaseUser()->create(['email' => 'member@musicall.test', 'plainPassword' => 'password']);

        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => 'member@musicall.test',
            'password' => 'password',
        ]);

        $this->assertResponseIsSuccessful();
        $context = $this->contextOf('auth.login.success');
        $this->assertSame('success', $context['outcome']);
        $this->assertNotNull($context['user_ref']);
        $this->assertNotNull($context['request_id']);
    }

    public function test_a_refresh_records_the_token_it_spent_and_the_one_it_issued(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $this->seedRefreshToken('a-refresh-token', $user);
        $this->client->getCookieJar()->set(
            new BrowserKitCookie('refresh_token', 'a-refresh-token', null, '/', self::HTTP_HOST)
        );

        $this->client->request('POST', '/api/token/refresh');

        $this->assertResponseIsSuccessful();
        $context = $this->contextOf('auth.refresh.success');
        $this->assertSame(substr(hash('sha256', 'a-refresh-token'), 0, 16), $context['token_hash']);
        $this->assertNotNull($context['replacement_hash']);
        $this->assertNotSame($context['token_hash'], $context['replacement_hash']);
        $this->assertSame('cookie', $context['client_kind']);
    }

    /**
     * A token nobody has a row for is the shape a refused refresh takes, and `previously_consumed`
     * is what separates a replay of something this server really issued from a token that was never
     * real. False here is the honest answer: nothing was spent in this test.
     */
    public function test_a_refused_refresh_carries_its_reason_and_its_trail(): void
    {
        $this->client->getCookieJar()->set(
            new BrowserKitCookie('refresh_token', 'a-token-nobody-issued', null, '/', self::HTTP_HOST)
        );

        $this->client->request('POST', '/api/token/refresh');

        $context = $this->contextOf('auth.refresh.failure');
        $this->assertSame('JWT Refresh Token Not Found', $context['reason']);
        $this->assertFalse($context['previously_consumed']);
        $this->assertSame('failure', $context['outcome']);
    }

    /**
     * Sending nothing is a different diagnosis from sending something that was refused: it is a
     * cookie that was never set or has been dropped, not a session that went wrong. The bundle
     * reports both through the same event, so the two are separated by exception type.
     */
    public function test_a_refresh_with_no_token_at_all_is_its_own_event(): void
    {
        $this->client->request('POST', '/api/token/refresh');

        $context = $this->contextOf('auth.refresh.not_found');
        $this->assertSame('Missing JWT Refresh Token', $context['reason']);
        $this->assertFalse($context['cookies_present']['refresh_token']);
    }

    /**
     * The half present pair, which is an ordinary returning visitor rather than an error: `jwt_hp`
     * follows the token TTL and `jwt_s` is a browser session cookie, so they do not die together.
     * SplitCookieExtractor needs both, so the server sees no token at all and answers
     * "JWT Token not found" rather than "Expired JWT Token". These booleans are what make that state
     * visible in a query instead of looking like an anonymous caller.
     */
    public function test_a_half_present_cookie_pair_is_reported_cookie_by_cookie(): void
    {
        $this->client->getCookieJar()->set(
            new BrowserKitCookie('jwt_s', 'a-signature', null, '/', self::HTTP_HOST)
        );

        $this->client->request('GET', '/api/users/self');

        $context = $this->contextOf('auth.jwt.rejected');
        $this->assertSame('JWT Token not found', $context['reason']);
        $this->assertSame(
            ['jwt_hp' => false, 'jwt_s' => true, 'refresh_token' => false],
            $context['cookies_present']
        );
    }

    /**
     * The deliberate end of a session, as opposed to the ones this is hunting. Worth an end to end
     * test rather than a unit one because this listener hangs off the `api` firewall's own event
     * dispatcher: LogoutEvent is not dispatched on the global one, so a listener registered the
     * ordinary way would never be called and nothing would say so.
     */
    public function test_a_logout_lands_on_the_auth_channel(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $this->seedRefreshToken('a-refresh-token', $user);
        $this->client->getCookieJar()->set(
            new BrowserKitCookie('refresh_token', 'a-refresh-token', null, '/', self::HTTP_HOST)
        );
        $this->client->loginUser($user);

        $this->client->request('POST', '/api/token/invalidate');

        $this->assertResponseIsSuccessful();
        $context = $this->contextOf('auth.logout');
        $this->assertSame('success', $context['outcome']);
        $this->assertSame((string) $user->id, $context['user_ref']);
    }

    /**
     * A login presents a password, not a credential this can read, so `none` is the honest answer.
     * Pinned because the field means something specific and a silent change of it would quietly
     * alter every query grouped on it.
     */
    public function test_a_login_reports_no_client_kind(): void
    {
        UserFactory::new()->asBaseUser()->create(['email' => 'other@musicall.test', 'plainPassword' => 'password']);

        $this->client->jsonRequest('POST', '/api/login_check', [
            'username' => 'other@musicall.test',
            'password' => 'password',
        ]);

        $this->assertSame('none', $this->contextOf('auth.login.success')['client_kind']);
    }

    /**
     * An anonymous caller reaching a protected endpoint is not a session coming apart, and at 5GB a
     * month the volume is worth not paying for.
     */
    public function test_an_anonymous_request_writes_nothing(): void
    {
        $this->client->request('GET', '/api/users/self');

        $this->assertSame([], $this->authRecords->getRecords());
    }

    /**
     * @return array<string, mixed>
     */
    private function contextOf(string $event): array
    {
        foreach ($this->authRecords->getRecords() as $record) {
            if ($record->message === $event) {
                self::assertSame(Level::Info, $record->level, sprintf('"%s" must be logged at info, or the Sentry handler floor drops it.', $event));

                return $record->context;
            }
        }

        self::fail(sprintf('No "%s" record on the auth channel. Got: %s', $event, implode(', ', array_map(
            static fn (\Monolog\LogRecord $record): string => $record->message,
            $this->authRecords->getRecords(),
        )) ?: '(nothing)'));
    }

    private function seedRefreshToken(string $value, object $user): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(RefreshToken::createForUserWithTtl($value, $user, 3600));
        $entityManager->flush();
    }
}
