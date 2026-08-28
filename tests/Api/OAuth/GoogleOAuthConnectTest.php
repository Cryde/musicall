<?php

declare(strict_types=1);

namespace App\Tests\Api\OAuth;

use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class GoogleOAuthConnectTest extends ApiTestCase
{
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
