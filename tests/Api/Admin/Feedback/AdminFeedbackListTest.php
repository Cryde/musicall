<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Feedback;

use App\Enum\Feedback\FeedbackModule;
use App\Enum\Feedback\FeedbackStatus;
use App\Enum\Feedback\FeedbackType;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Feedback\FeedbackFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class AdminFeedbackListTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $this->client->request('GET', '/api/admin/feedbacks');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_base_user_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/admin/feedbacks');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_admin_lists_feedback_newest_first(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'reporter', 'email' => 'reporter@email.com']);

        $older = FeedbackFactory::new()->create([
            'creationDatetime' => new \DateTime('2026-09-01 09:00:00'),
            'message' => 'Le plus ancien des deux retours.',
        ]);
        $newer = FeedbackFactory::new()->create([
            'creationDatetime' => new \DateTime('2026-09-02 09:00:00'),
            'type' => FeedbackType::Suggestion,
            'module' => FeedbackModule::Forum,
            'message' => 'Le plus récent des deux retours.',
            'pageUrl' => '/forum',
            'email' => 'reporter@email.com',
            'user' => $author,
        ]);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/feedbacks');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminFeedback',
            '@id' => '/api/admin/feedbacks',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/admin/feedbacks/' . $newer->id,
                    '@type' => 'AdminFeedback',
                    'id' => (string) $newer->id,
                    'type' => 'suggestion',
                    'type_label' => 'Suggestion',
                    'module' => 'forum',
                    'module_label' => 'Forum',
                    'message' => 'Le plus récent des deux retours.',
                    'email' => 'reporter@email.com',
                    'username' => 'reporter',
                    'page_url' => '/forum',
                    'status' => 'new',
                    'status_label' => 'Nouveau',
                    'creation_datetime' => '2026-09-02T09:00:00+00:00',
                ],
                [
                    '@id' => '/api/admin/feedbacks/' . $older->id,
                    '@type' => 'AdminFeedback',
                    'id' => (string) $older->id,
                    'type' => 'bug',
                    'type_label' => 'Bug',
                    'module' => 'file',
                    'module_label' => 'Fichiers',
                    'message' => 'Le plus ancien des deux retours.',
                    'page_url' => '/band/space-id/fichiers',
                    'status' => 'new',
                    'status_label' => 'Nouveau',
                    'creation_datetime' => '2026-09-01T09:00:00+00:00',
                ],
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/admin/feedbacks{?status,module,type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    ['@type' => 'IriTemplateMapping', 'variable' => 'status', 'property' => 'status', 'required' => false],
                    ['@type' => 'IriTemplateMapping', 'variable' => 'module', 'property' => 'module', 'required' => false],
                    ['@type' => 'IriTemplateMapping', 'variable' => 'type', 'property' => 'type', 'required' => false],
                ],
            ],
        ]);
    }

    public function test_admin_filters_by_status(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();

        FeedbackFactory::new()->create(['message' => 'Un retour encore à trier.']);
        $triaged = FeedbackFactory::new()->asTriaged()->create([
            'creationDatetime' => new \DateTime('2026-09-03 09:00:00'),
            'message' => 'Un retour déjà traité.',
        ]);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/feedbacks?status=done');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AdminFeedback',
            '@id' => '/api/admin/feedbacks',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/admin/feedbacks/' . $triaged->id,
                    '@type' => 'AdminFeedback',
                    'id' => (string) $triaged->id,
                    'type' => 'bug',
                    'type_label' => 'Bug',
                    'module' => 'file',
                    'module_label' => 'Fichiers',
                    'message' => 'Un retour déjà traité.',
                    'page_url' => '/band/space-id/fichiers',
                    'status' => 'done',
                    'status_label' => 'Traité',
                    'creation_datetime' => '2026-09-03T09:00:00+00:00',
                ],
            ],
            'view' => [
                '@id' => '/api/admin/feedbacks?status=done',
                '@type' => 'PartialCollectionView',
            ],
            'search' => [
                '@type' => 'IriTemplate',
                'template' => '/api/admin/feedbacks{?status,module,type}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping' => [
                    ['@type' => 'IriTemplateMapping', 'variable' => 'status', 'property' => 'status', 'required' => false],
                    ['@type' => 'IriTemplateMapping', 'variable' => 'module', 'property' => 'module', 'required' => false],
                    ['@type' => 'IriTemplateMapping', 'variable' => 'type', 'property' => 'type', 'required' => false],
                ],
            ],
        ]);
    }

    public function test_an_unknown_status_filter_is_refused(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/feedbacks?status=bogus');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_an_array_status_filter_is_refused_rather_than_a_500(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/feedbacks?status[]=new');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_the_untriaged_count_ignores_handled_feedback(): void
    {
        FeedbackFactory::new()->create();
        FeedbackFactory::new()->create();
        FeedbackFactory::new()->asTriaged()->create();

        $repository = static::getContainer()->get(\App\Repository\Feedback\FeedbackRepository::class);
        $this->assertSame(2, $repository->countNew());
        $this->assertSame(FeedbackStatus::New, FeedbackStatus::from('new'));
    }
}
