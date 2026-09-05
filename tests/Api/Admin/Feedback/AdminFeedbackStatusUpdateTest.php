<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Feedback;

use App\Entity\Feedback\Feedback;
use App\Enum\Feedback\FeedbackStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Feedback\FeedbackFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class AdminFeedbackStatusUpdateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_not_logged(): void
    {
        $feedback = FeedbackFactory::new()->create();

        $this->client->jsonRequest('POST', '/api/admin/feedbacks/' . $feedback->id . '/status', ['status' => 'done'], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_base_user_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $feedback = FeedbackFactory::new()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/admin/feedbacks/' . $feedback->id . '/status', ['status' => 'done'], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_admin_moves_a_report_to_done(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $feedback = FeedbackFactory::new()->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/feedbacks/' . $feedback->id . '/status', ['status' => 'done'], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());

        $this->assertSame(FeedbackStatus::Done, $this->reload((string) $feedback->id)->status);
    }

    public function test_setting_the_status_it_already_has_is_a_no_op(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $feedback = FeedbackFactory::new()->asTriaged()->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/feedbacks/' . $feedback->id . '/status', ['status' => 'done'], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->assertSame(FeedbackStatus::Done, $this->reload((string) $feedback->id)->status);
    }

    public function test_an_unknown_status_is_refused(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $feedback = FeedbackFactory::new()->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/feedbacks/' . $feedback->id . '/status', ['status' => 'archived'], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'status',
                    'message' => 'Statut inconnu',
                    'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7',
                ],
            ],
            'detail' => 'status: Statut inconnu',
            'type' => '/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            'title' => 'An error occurred',
            'description' => 'status: Statut inconnu',
        ]);

        $this->assertSame(FeedbackStatus::New, $this->reload((string) $feedback->id)->status);
    }

    /**
     * The id is a URI path segment, so nothing validates it before the processor. find() would coerce
     * it through the uuid field type and throw, which is a 500 rather than a 404.
     */
    public function test_a_malformed_id_is_a_404_rather_than_a_500(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/feedbacks/not-a-uuid/status', ['status' => 'done'], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_an_unknown_id_is_a_404(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();

        $this->client->loginUser($admin);
        $this->client->jsonRequest('POST', '/api/admin/feedbacks/1b9d6bcd-bbfd-4b2d-9b5d-ab8dfbbd4bed/status', ['status' => 'done'], self::HEADERS);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function reload(string $id): Feedback
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $feedback = $entityManager->getRepository(Feedback::class)->find($id);
        $this->assertInstanceOf(Feedback::class, $feedback);

        return $feedback;
    }
}
