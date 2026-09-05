<?php

declare(strict_types=1);

namespace App\Tests\Api\Feedback;

use App\Entity\Feedback\Feedback;
use App\Enum\Feedback\FeedbackModule;
use App\Enum\Feedback\FeedbackStatus;
use App\Enum\Feedback\FeedbackType;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class FeedbackPostTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_anonymous_can_send_feedback(): void
    {
        $this->client->jsonRequest('POST', '/api/feedbacks', [
            'type' => 'bug',
            'module' => 'file',
            'message' => "Le téléversement échoue sans message d'erreur.",
            'page_url' => '/band/abc/fichiers',
        ], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Feedback',
            '@id' => '/api/feedback',
            '@type' => 'Feedback',
            'type' => 'bug',
            'module' => 'file',
            'message' => "Le téléversement échoue sans message d'erreur.",
            'page_url' => '/band/abc/fichiers',
        ]);

        $feedback = $this->latestFeedback();
        $this->assertSame(FeedbackType::Bug, $feedback->type);
        $this->assertSame(FeedbackModule::File, $feedback->module);
        $this->assertSame(FeedbackStatus::New, $feedback->status);
        $this->assertNull($feedback->user);
        $this->assertNull($feedback->email);
        $this->assertNull($feedback->bandSpace);
    }

    public function test_anonymous_can_leave_an_email(): void
    {
        $this->client->jsonRequest('POST', '/api/feedbacks', [
            'type' => 'suggestion',
            'module' => 'forum',
            'message' => 'Il manque un filtre par tag sur le forum.',
            'page_url' => '/forum',
            'email' => 'visiteur@email.com',
        ], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Feedback',
            '@id' => '/api/feedback',
            '@type' => 'Feedback',
            'type' => 'suggestion',
            'module' => 'forum',
            'message' => 'Il manque un filtre par tag sur le forum.',
            'page_url' => '/forum',
            'email' => 'visiteur@email.com',
        ]);

        $this->assertSame('visiteur@email.com', $this->latestFeedback()->email);
    }

    public function test_logged_in_user_is_attached_with_their_band_space(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create(['name' => 'Les Cactus']);
        BandSpaceMembershipFactory::new()->create(['bandSpace' => $bandSpace, 'user' => $user]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/feedbacks', [
            'type' => 'bug',
            'module' => 'finance',
            'message' => 'Le total annuel ne correspond pas à la somme des lignes.',
            'page_url' => '/band/' . $bandSpace->id . '/finances',
            'band_space_id' => (string) $bandSpace->id,
        ], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Feedback',
            '@id' => '/api/feedback',
            '@type' => 'Feedback',
            'type' => 'bug',
            'module' => 'finance',
            'message' => 'Le total annuel ne correspond pas à la somme des lignes.',
            'page_url' => '/band/' . $bandSpace->id . '/finances',
            'band_space_id' => (string) $bandSpace->id,
        ]);

        $feedback = $this->latestFeedback();
        $this->assertNotNull($feedback->user);
        $this->assertSame($user->username, $feedback->user->username);
        $this->assertNotNull($feedback->bandSpace);
        $this->assertSame('Les Cactus', $feedback->bandSpace->name);
    }

    public function test_band_space_is_dropped_when_the_sender_is_not_a_member(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $someoneElsesSpace = BandSpaceFactory::new()->create(['name' => 'Pas la mienne']);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/feedbacks', [
            'type' => 'bug',
            'module' => 'agenda',
            'message' => 'Un message parfaitement valide envoyé par un non membre.',
            'page_url' => '/band/' . $someoneElsesSpace->id . '/agenda',
            'band_space_id' => (string) $someoneElsesSpace->id,
        ], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $feedback = $this->latestFeedback();
        $this->assertNotNull($feedback->user);
        $this->assertNull($feedback->bandSpace, 'A non member must not be able to pin a report onto a space');
    }

    public function test_band_space_is_dropped_for_an_anonymous_sender(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();

        $this->client->jsonRequest('POST', '/api/feedbacks', [
            'type' => 'bug',
            'module' => 'agenda',
            'message' => 'Un message parfaitement valide envoyé sans être connecté.',
            'page_url' => '/band/' . $bandSpace->id . '/agenda',
            'band_space_id' => (string) $bandSpace->id,
        ], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertNull($this->latestFeedback()->bandSpace);
    }

    public function test_a_short_message_is_refused(): void
    {
        $this->client->jsonRequest('POST', '/api/feedbacks', [
            'type' => 'bug',
            'module' => 'file',
            'message' => 'trop bref',
            'page_url' => '/band/abc/fichiers',
        ], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'message',
                    'message' => 'Votre message doit contenir au minimum 10 caractères',
                    'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ],
            ],
            'detail' => 'message: Votre message doit contenir au minimum 10 caractères',
            'type' => '/validation_errors/9ff3fdc4-b214-49db-8718-39c315e33d45',
            'title' => 'An error occurred',
            'description' => 'message: Votre message doit contenir au minimum 10 caractères',
        ]);

        $this->assertSame(0, $this->feedbackCount());
    }

    public function test_an_unknown_module_is_refused(): void
    {
        $this->client->jsonRequest('POST', '/api/feedbacks', [
            'type' => 'bug',
            'module' => 'trombone',
            'message' => 'Un message parfaitement valide sur une section inventée.',
            'page_url' => '/band/abc/fichiers',
        ], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'module',
                    'message' => 'Section inconnue',
                    'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7',
                ],
            ],
            'detail' => 'module: Section inconnue',
            'type' => '/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            'title' => 'An error occurred',
            'description' => 'module: Section inconnue',
        ]);

        $this->assertSame(0, $this->feedbackCount());
    }

    public function test_an_absolute_page_url_is_refused(): void
    {
        $this->client->jsonRequest('POST', '/api/feedbacks', [
            'type' => 'bug',
            'module' => 'file',
            'message' => 'Un message parfaitement valide avec une URL absolue.',
            'page_url' => 'https://evil.example.com/phishing',
        ], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'page_url',
                    'message' => "L'adresse de la page est invalide",
                    'code' => 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
                ],
            ],
            'detail' => "page_url: L'adresse de la page est invalide",
            'type' => '/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            'title' => 'An error occurred',
            'description' => "page_url: L'adresse de la page est invalide",
        ]);

        $this->assertSame(0, $this->feedbackCount());
    }

    /**
     * A leading slash alone is not enough. These all resolve to another origin in a browser, and
     * vue-router hands any slash-prefixed string straight to the rendered href, so a middle click or
     * a hover in the admin triage table would leave the site. An anonymous caller can plant one.
     *
     * @return iterable<string, array{string}>
     */
    public static function offSitePageUrlProvider(): iterable
    {
        yield 'protocol relative' => ['//evil.example.com/phishing'];
        yield 'protocol relative, extra slash' => ['///evil.example.com'];
        yield 'backslash after the leading slash' => ['/\\evil.example.com'];
        yield 'backslash further in' => ['/band/\\evil.example.com'];
    }

    #[DataProvider('offSitePageUrlProvider')]
    public function test_an_off_site_page_url_is_refused(string $pageUrl): void
    {
        $this->client->jsonRequest('POST', '/api/feedbacks', [
            'type' => 'bug',
            'module' => 'file',
            'message' => 'Un message parfaitement valide avec une URL hors du site.',
            'page_url' => $pageUrl,
        ], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'page_url',
                    'message' => "L'adresse de la page est invalide",
                    'code' => 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
                ],
            ],
            'detail' => "page_url: L'adresse de la page est invalide",
            'type' => '/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            'title' => 'An error occurred',
            'description' => "page_url: L'adresse de la page est invalide",
        ]);

        $this->assertSame(0, $this->feedbackCount());
    }

    public function test_the_submission_is_rate_limited(): void
    {
        // Burn the whole 5/hour budget for this IP up front; the test client is always 127.0.0.1.
        /** @var RateLimiterFactoryInterface $limiter */
        $limiter = self::getContainer()->get('limiter.feedback_submit');
        $limiter->create('127.0.0.1')->consume(5);

        $this->client->jsonRequest('POST', '/api/feedbacks', [
            'type' => 'bug',
            'module' => 'file',
            'message' => 'Un message parfaitement valide, mais un de trop.',
            'page_url' => '/band/abc/fichiers',
        ], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/429',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Rate Limit Exceeded',
            'status' => 429,
            'type' => '/errors/429',
            'description' => 'Rate Limit Exceeded',
        ]);

        $this->assertSame(0, $this->feedbackCount());
    }

    public function test_a_malformed_band_space_id_is_refused(): void
    {
        $this->client->jsonRequest('POST', '/api/feedbacks', [
            'type' => 'bug',
            'module' => 'file',
            'message' => 'Un message parfaitement valide avec un identifiant cassé.',
            'page_url' => '/band/abc/fichiers',
            'band_space_id' => 'not-a-uuid',
        ], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/51120b12-a2bc-41bf-aa53-cd73daf330d0',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'band_space_id',
                    'message' => 'Identifiant de Band Space invalide',
                    'code' => '51120b12-a2bc-41bf-aa53-cd73daf330d0',
                ],
            ],
            'detail' => 'band_space_id: Identifiant de Band Space invalide',
            'type' => '/validation_errors/51120b12-a2bc-41bf-aa53-cd73daf330d0',
            'title' => 'An error occurred',
            'description' => 'band_space_id: Identifiant de Band Space invalide',
        ]);

        $this->assertSame(0, $this->feedbackCount());
    }

    private function latestFeedback(): Feedback
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $feedback = $entityManager->getRepository(Feedback::class)->findOneBy([], ['creationDatetime' => 'DESC']);
        $this->assertInstanceOf(Feedback::class, $feedback);

        return $feedback;
    }

    private function feedbackCount(): int
    {
        return static::getContainer()->get(EntityManagerInterface::class)->getRepository(Feedback::class)->count([]);
    }
}
