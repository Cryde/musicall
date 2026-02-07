<?php

namespace App\Tests\Api\Publication;

use App\Entity\Publication;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Metric\VoteCacheFactory;
use App\Tests\Factory\Metric\VoteFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PublicationVoteTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    private const array SERVER_PARAMS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_post_vote_upvote(): void
    {
        $this->createOnlinePublication();
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/publications/test-publication/vote',
            ['user_vote' => 1],
            self::SERVER_PARAMS
        );
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicationVoteSummary',
            '@id' => '/api/publication_vote_summaries/test-publication',
            '@type' => 'PublicationVoteSummary',
            'upvotes' => 1,
            'downvotes' => 0,
            'user_vote' => 1,
        ]);
    }

    public function test_post_vote_downvote(): void
    {
        $this->createOnlinePublication();
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/publications/test-publication/vote',
            ['user_vote' => -1],
            self::SERVER_PARAMS
        );
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicationVoteSummary',
            '@id' => '/api/publication_vote_summaries/test-publication',
            '@type' => 'PublicationVoteSummary',
            'upvotes' => 0,
            'downvotes' => 1,
            'user_vote' => -1,
        ]);
    }

    public function test_post_vote_change_direction(): void
    {
        $publication = $this->createOnlinePublication();
        $user = UserFactory::new()->asBaseUser()->create();
        $voteCache = VoteCacheFactory::new(['upvoteCount' => 1, 'downvoteCount' => 0])->create();
        $publication->_real()->setVoteCache($voteCache->_real());
        $publication->_save();

        VoteFactory::new([
            'voteCache' => $voteCache,
            'user' => $user,
            'value' => 1,
            'identifier' => 'test-identifier',
            'entityType' => 'app_publication',
            'entityId' => (string) $publication->_real()->getId(),
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/publications/test-publication/vote',
            ['user_vote' => -1],
            self::SERVER_PARAMS
        );
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicationVoteSummary',
            '@id' => '/api/publication_vote_summaries/test-publication',
            '@type' => 'PublicationVoteSummary',
            'upvotes' => 0,
            'downvotes' => 1,
            'user_vote' => -1,
        ]);
    }

    public function test_post_vote_toggle_off(): void
    {
        $publication = $this->createOnlinePublication();
        $user = UserFactory::new()->asBaseUser()->create();
        $voteCache = VoteCacheFactory::new(['upvoteCount' => 1, 'downvoteCount' => 0])->create();
        $publication->_real()->setVoteCache($voteCache->_real());
        $publication->_save();

        VoteFactory::new([
            'voteCache' => $voteCache,
            'user' => $user,
            'value' => 1,
            'identifier' => 'test-identifier',
            'entityType' => 'app_publication',
            'entityId' => (string) $publication->_real()->getId(),
        ])->create();

        // POST same value = toggle off
        $this->client->loginUser($user->_real());
        $this->client->jsonRequest(
            'POST',
            '/api/publications/test-publication/vote',
            ['user_vote' => 1],
            self::SERVER_PARAMS
        );
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicationVoteSummary',
            '@id' => '/api/publication_vote_summaries/test-publication',
            '@type' => 'PublicationVoteSummary',
            'upvotes' => 0,
            'downvotes' => 0,
            'user_vote' => null,
        ]);
    }

    public function test_post_vote_anonymous(): void
    {
        $this->createOnlinePublication();

        $this->client->jsonRequest(
            'POST',
            '/api/publications/test-publication/vote',
            ['user_vote' => 1],
            self::SERVER_PARAMS
        );
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicationVoteSummary',
            '@id' => '/api/publication_vote_summaries/test-publication',
            '@type' => 'PublicationVoteSummary',
            'upvotes' => 1,
            'downvotes' => 0,
            'user_vote' => 1,
        ]);
    }

    public function test_post_vote_anonymous_toggle_off(): void
    {
        $this->createOnlinePublication();

        // First vote
        $this->client->jsonRequest(
            'POST',
            '/api/publications/test-publication/vote',
            ['user_vote' => 1],
            self::SERVER_PARAMS
        );
        $this->assertResponseIsSuccessful();

        // Toggle off (same value)
        $this->client->jsonRequest(
            'POST',
            '/api/publications/test-publication/vote',
            ['user_vote' => 1],
            self::SERVER_PARAMS
        );
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicationVoteSummary',
            '@id' => '/api/publication_vote_summaries/test-publication',
            '@type' => 'PublicationVoteSummary',
            'upvotes' => 0,
            'downvotes' => 0,
            'user_vote' => null,
        ]);
    }

    public function test_post_vote_invalid_value(): void
    {
        $this->createOnlinePublication();
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/publications/test-publication/vote',
            ['user_vote' => 5],
            self::SERVER_PARAMS
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'user_vote',
                    'message' => 'La valeur du vote doit être 1 ou -1.',
                    'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7',
                ],
            ],
            'detail' => 'user_vote: La valeur du vote doit être 1 ou -1.',
            'description' => 'user_vote: La valeur du vote doit être 1 ou -1.',
            'type' => '/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            'title' => 'An error occurred',
        ]);
    }

    public function test_vote_on_nonexistent_publication(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/publications/nonexistent-slug/vote',
            ['user_vote' => 1],
            self::SERVER_PARAMS
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Publication inexistante',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Publication inexistante',
        ]);
    }

    private function createOnlinePublication(string $slug = 'test-publication'): object
    {
        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $author = UserFactory::new()->asAdminUser()->create();

        return PublicationFactory::new([
            'author' => $author,
            'slug' => $slug,
            'status' => Publication::STATUS_ONLINE,
            'subCategory' => $sub,
            'type' => Publication::TYPE_TEXT,
        ])->create();
    }
}
