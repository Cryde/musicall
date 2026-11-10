<?php

declare(strict_types=1);

namespace App\Tests\Api\OAuth;

use App\Entity\SocialAccount;
use App\Entity\User;
use App\Tests\ApiTestCase;
use App\Tests\Double\FakeGoogleOAuthClient;
use App\Tests\Factory\User\SocialAccountFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class GoogleOAuthConnectTest extends ApiTestCase
{
    private const array CAMPAIGN = [
        'utm_source' => 'google',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'pmax-musiciens',
        'referrer_host' => 'www.google.com',
    ];

    public function test_connect_uses_an_unguessable_random_state_nonce(): void
    {
        $this->client->request('GET', '/oauth/google?return_url=' . urlencode('/band-space/abc'));

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $location = (string) $this->client->getResponse()->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $state = is_string($query['state'] ?? null) ? $query['state'] : '';

        // State is an unguessable 64-char hex nonce, NOT a base64-encoded JSON blob
        // embedding the return URL (which made the old state attacker-derivable).
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $state);
        $this->assertStringNotContainsString('return_url', (string) base64_decode($state, true));
    }

    /**
     * connect() only stashes a return URL it accepts, so the session is where the rule shows through.
     * App\Http\ReturnUrl decides what is accepted and tests/Unit/Http/ReturnUrlTest.php pins that;
     * what these two add is that the controller asks it at all, which no unit test can say.
     */
    public function test_connect_stashes_a_return_url_it_accepts(): void
    {
        $this->client->request('GET', '/oauth/google?return_url=' . urlencode('/band-space/abc'));

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertSame(['/band-space/abc'], $this->stashedReturnUrls());
    }

    public function test_connect_stashes_nothing_for_a_return_url_that_leaves_the_origin(): void
    {
        foreach (['//evil.example', '/\\evil.example', "/\t/evil.example", 'https://evil.example'] as $returnUrl) {
            $this->client->request('GET', '/oauth/google?return_url=' . urlencode($returnUrl));

            $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
            $this->assertSame([], $this->stashedReturnUrls(), sprintf('%s must not be stashed', json_encode($returnUrl)));
        }
    }

    /** The campaign of the visit (#1369) waits in the server session too, never in the browser. */
    public function test_connect_stashes_the_campaign_of_the_visit(): void
    {
        $this->client->request('GET', '/oauth/google?' . http_build_query(['return_url' => '/band-space/abc', ...self::CAMPAIGN]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertSame([self::CAMPAIGN], $this->stashedCampaigns());
        $this->assertSame(['/band-space/abc'], $this->stashedReturnUrls());
    }

    public function test_connect_stashes_only_the_campaign_values_it_accepts(): void
    {
        $this->client->request('GET', '/oauth/google?' . http_build_query([
            'utm_source' => '  google ',
            'utm_medium' => str_repeat('a', 101),
            'utm_campaign' => ['pmax'],
            'referrer_host' => 'https://www.google.com/search',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertSame([['utm_source' => 'google', 'utm_medium' => null, 'utm_campaign' => null, 'referrer_host' => null]], $this->stashedCampaigns());
    }

    public function test_connect_without_a_campaign_stashes_none(): void
    {
        $this->client->request('GET', '/oauth/google?' . http_build_query(['return_url' => '/band-space/abc', 'utm_source' => '<b>']));

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertSame([], $this->stashedCampaigns());
    }

    public function test_a_google_sign_up_records_the_campaign_of_its_visit(): void
    {
        $this->fakeGoogle(['sub' => 'google-id-new', 'email' => 'nouveau@example.com', 'email_verified' => true, 'name' => 'Nouveau']);

        $this->signInWithGoogle(self::CAMPAIGN);

        $this->assertResponseRedirects('http://localhost', Response::HTTP_FOUND);
        $this->assertSame(['google', 'cpc', 'pmax-musiciens', 'www.google.com'], $this->campaignOf('nouveau@example.com'));
    }

    /** A login is not a sign-up: the campaign of this visit says nothing about how the account came. */
    public function test_a_google_login_records_no_campaign(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['email' => 'alice@example.com']);
        SocialAccountFactory::new()->create(['user' => $user, 'provider' => SocialAccount::PROVIDER_GOOGLE, 'providerId' => 'google-id-alice']);
        $this->fakeGoogle(['sub' => 'google-id-alice', 'email' => 'alice@example.com', 'email_verified' => true, 'name' => 'Alice']);

        $this->signInWithGoogle(self::CAMPAIGN);

        $this->assertResponseRedirects('http://localhost', Response::HTTP_FOUND);
        $this->assertSame([null, null, null, null], $this->campaignOf('alice@example.com'));
    }

    /**
     * Google's client is replaced for the whole test, so the kernel must not reboot between the two requests.
     *
     * @param array<string, mixed> $claims
     */
    private function fakeGoogle(array $claims): void
    {
        $this->client->disableReboot();
        self::getContainer()->set('knpu.oauth2.client.google', new FakeGoogleOAuthClient($claims));
    }

    /** @param array<string, string> $campaign */
    private function signInWithGoogle(array $campaign): void
    {
        $this->client->request('GET', '/oauth/google?' . http_build_query($campaign));
        parse_str((string) parse_url((string) $this->client->getResponse()->headers->get('Location'), PHP_URL_QUERY), $query);
        $state = is_string($query['state'] ?? null) ? $query['state'] : '';

        $this->client->request('GET', '/oauth/google/callback?' . http_build_query(['state' => $state, 'code' => 'a-code']));
    }

    /** @return array{?string, ?string, ?string, ?string} */
    private function campaignOf(string $email): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertInstanceOf(User::class, $user);

        return [$user->registrationUtmSource, $user->registrationUtmMedium, $user->registrationUtmCampaign, $user->registrationReferrerHost];
    }

    /**
     * @return list<mixed> the campaigns connect() put in the session, in insertion order
     */
    private function stashedCampaigns(): array
    {
        $stashed = [];
        foreach ($this->client->getRequest()->getSession()->all() as $key => $value) {
            if (str_starts_with($key, 'oauth_campaign.')) {
                $stashed[] = $value;
            }
        }

        return $stashed;
    }

    /**
     * @return string[] the return URLs connect() put in the session, in insertion order
     */
    private function stashedReturnUrls(): array
    {
        $session = $this->client->getRequest()->getSession();

        $stashed = [];
        foreach ($session->all() as $key => $value) {
            if (str_starts_with($key, 'oauth_return_url.') && is_string($value)) {
                $stashed[] = $value;
            }
        }

        return $stashed;
    }

    /**
     * Google answers `?error=access_denied&state=...` when the person cancels, so no code: the sign-in
     * fails, and the login page it lands on still knows where the visitor started (#1302).
     */
    public function test_a_failed_callback_keeps_the_way_back(): void
    {
        $this->client->request('GET', '/oauth/google?return_url=' . urlencode('/band/1/agenda?view=month'));
        parse_str((string) parse_url((string) $this->client->getResponse()->headers->get('Location'), PHP_URL_QUERY), $query);
        $state = is_string($query['state'] ?? null) ? $query['state'] : '';

        $this->client->request('GET', '/oauth/google/callback?' . http_build_query(['error' => 'access_denied', 'state' => $state]));

        $this->assertResponseRedirects('http://localhost/login?oauth_error=oauth_failed&return_url=%2Fband%2F1%2Fagenda%3Fview%3Dmonth', Response::HTTP_FOUND);
    }

    public function test_a_failed_callback_without_a_return_url_lands_on_the_bare_login_page(): void
    {
        $this->client->request('GET', '/oauth/google/callback?' . http_build_query(['error' => 'access_denied', 'state' => 'not-ours']));

        $this->assertResponseRedirects('http://localhost/login?oauth_error=oauth_failed', Response::HTTP_FOUND);
    }

    public function test_connect_without_return_url_still_redirects(): void
    {
        $this->client->request('GET', '/oauth/google');

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertStringStartsWith(
            'https://accounts.google.com/',
            (string) $this->client->getResponse()->headers->get('Location'),
        );
    }
}
