<?php

declare(strict_types=1);

namespace App\Tests\Api\Locale;

use App\Locale\RequestLocaleResolver;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * `fr` and `en` are enabled in the test environment. The default Symfony validator messages are what shows the
 * resolved locale: they go through the translator, and Symfony ships them in both languages.
 */
#[ResetDatabase]
class RequestLocaleTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_an_anonymous_request_asking_for_english_is_answered_in_english(): void
    {
        $this->client->request('GET', '/api/tags/popular?count=51', server: ['HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'count',
                    'message' => 'This value should be between 1 and 50.',
                    'code' => '04b91c99-a946-4221-afc5-e65ebac401eb',
                ],
            ],
            'detail' => 'count: This value should be between 1 and 50.',
            'description' => 'count: This value should be between 1 and 50.',
            'type' => '/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            'title' => 'An error occurred',
        ]);
        $this->assertContains('Accept-Language', $this->client->getResponse()->getVary());
    }

    public function test_an_anonymous_request_asking_for_a_locale_that_is_not_enabled_is_answered_in_french(): void
    {
        $this->client->getCookieJar()->set(new Cookie(RequestLocaleResolver::COOKIE, 'de'));
        $this->client->request('GET', '/api/tags/popular?count=51', server: [
            'HTTP_ACCEPT_LANGUAGE' => 'de-DE,de;q=0.9',
            'HTTP_X_LOCALE' => 'de',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'count',
                    'message' => 'Cette valeur doit être comprise entre 1 et 50.',
                    'code' => '04b91c99-a946-4221-afc5-e65ebac401eb',
                ],
            ],
            'detail' => 'count: Cette valeur doit être comprise entre 1 et 50.',
            'description' => 'count: Cette valeur doit être comprise entre 1 et 50.',
            'type' => '/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_user_whose_locale_is_not_enabled_is_answered_from_the_browser_language(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['locale' => 'de']);
        // A real token rather than loginUser(), which hands the user over before the firewall runs.
        $jwt = self::getContainer()->get(JWTTokenManagerInterface::class)->create($user);

        $this->client->request('GET', '/api/tags/popular?count=51', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $jwt,
            'HTTP_ACCEPT_LANGUAGE' => 'en',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'count',
                    'message' => 'This value should be between 1 and 50.',
                    'code' => '04b91c99-a946-4221-afc5-e65ebac401eb',
                ],
            ],
            'detail' => 'count: This value should be between 1 and 50.',
            'description' => 'count: This value should be between 1 and 50.',
            'type' => '/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            'title' => 'An error occurred',
        ]);
    }

    public function test_the_user_locale_wins_over_the_browser_language(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['locale' => 'en']);
        $jwt = self::getContainer()->get(JWTTokenManagerInterface::class)->create($user);

        $this->client->request('GET', '/api/tags/popular?count=51', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $jwt,
            'HTTP_ACCEPT_LANGUAGE' => 'fr',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'count',
                    'message' => 'This value should be between 1 and 50.',
                    'code' => '04b91c99-a946-4221-afc5-e65ebac401eb',
                ],
            ],
            'detail' => 'count: This value should be between 1 and 50.',
            'description' => 'count: This value should be between 1 and 50.',
            'type' => '/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            'title' => 'An error occurred',
        ]);
        $this->assertNotContains('Accept-Language', $this->client->getResponse()->getVary());
    }

    public function test_the_front_end_locale_wins_over_the_user_locale(): void
    {
        // An English page opened by a member whose account says French: the page decides.
        $user = UserFactory::new()->asBaseUser()->create(['locale' => 'fr']);
        $jwt = self::getContainer()->get(JWTTokenManagerInterface::class)->create($user);

        $this->client->request('GET', '/api/tags/popular?count=51', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $jwt,
            'HTTP_ACCEPT_LANGUAGE' => 'fr',
            'HTTP_X_LOCALE' => 'en',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'count',
                    'message' => 'This value should be between 1 and 50.',
                    'code' => '04b91c99-a946-4221-afc5-e65ebac401eb',
                ],
            ],
            'detail' => 'count: This value should be between 1 and 50.',
            'description' => 'count: This value should be between 1 and 50.',
            'type' => '/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            'title' => 'An error occurred',
        ]);
        $this->assertNotContains('Accept-Language', $this->client->getResponse()->getVary());
    }

    public function test_a_french_page_is_answered_in_french_whatever_the_browser_language(): void
    {
        $this->client->getCookieJar()->set(new Cookie(RequestLocaleResolver::COOKIE, 'en'));
        $this->client->request('GET', '/api/tags/popular?count=51', server: [
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
            'HTTP_X_LOCALE' => 'fr',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'count',
                    'message' => 'Cette valeur doit être comprise entre 1 et 50.',
                    'code' => '04b91c99-a946-4221-afc5-e65ebac401eb',
                ],
            ],
            'detail' => 'count: Cette valeur doit être comprise entre 1 et 50.',
            'description' => 'count: Cette valeur doit être comprise entre 1 et 50.',
            'type' => '/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            'title' => 'An error occurred',
        ]);
    }

    public function test_an_array_locale_cookie_does_not_break_the_request(): void
    {
        // PHP turns `Cookie: locale[]=en` into an array, which the browser client cannot send.
        $request = Request::create('/api/tags/popular?count=5', cookies: [RequestLocaleResolver::COOKIE => ['en']]);
        $request->headers->set('Accept', 'application/ld+json');

        $response = $this->client->getKernel()->handle($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame([
            '@context' => '/api/contexts/PopularTag',
            '@id' => '/api/tags/popular',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
            'view' => [
                '@id' => '/api/tags/popular?count=5',
                '@type' => 'PartialCollectionView',
            ],
        ], json_decode((string) $response->getContent(), true));
    }

    public function test_a_list_valued_front_end_locale_does_not_break_the_request(): void
    {
        // Not something PHP produces from a real header, which it joins into one string, but a header bag accepts it.
        $request = Request::create('/api/tags/popular?count=5', server: ['HTTP_X_LOCALE' => ['de', 'en']]);
        $request->headers->set('Accept', 'application/ld+json');

        $response = $this->client->getKernel()->handle($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame([
            '@context' => '/api/contexts/PopularTag',
            '@id' => '/api/tags/popular',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
            'view' => [
                '@id' => '/api/tags/popular?count=5',
                '@type' => 'PartialCollectionView',
            ],
        ], json_decode((string) $response->getContent(), true));
    }
}
