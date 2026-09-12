<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use App\Tests\Factory\User\UserProfilePictureFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class ChatMessageGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $space = BandSpaceFactory::new()->create();

        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_get_collection(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create(['name' => 'Les Trois Accords']);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);

        $older = MessageFactory::new([
            'thread' => $channel,
            'author' => $member,
            'content' => 'on répète mardi',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();
        $newer = MessageFactory::new([
            'thread' => $channel,
            'author' => $member,
            'content' => "j'apporte la basse",
            'creationDatetime' => new \DateTime('2026-09-10 20:05:00'),
        ])->create();

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/messages',
            '@type' => 'Collection',
            'totalItems' => 2,
            // Newest first, which is what a chat pane wants and what the composite index reads
            // backwards without sorting.
            'member' => [
                // The apostrophe comes back escaped: the content is sanitizer output, exactly like
                // the direct message thread, and the thread view renders it with v-html.
                $this->expectedMessage($newer->id, $space, $member, 'batteur', 'j&#039;apporte la basse', '2026-09-10T20:05:00+00:00'),
                $this->expectedMessage($older->id, $space, $member, 'batteur', 'on répète mardi', '2026-09-10T20:00:00+00:00'),
            ],
        ]);
    }

    public function test_the_second_page_holds_the_older_messages(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create(['name' => 'Les Trois Accords']);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);

        // 52 messages a minute apart, so page 1 holds 50 and page 2 the two oldest.
        $messages = [];
        foreach (range(1, 52) as $minute) {
            $messages[$minute] = MessageFactory::new([
                'thread' => $channel,
                'author' => $member,
                'content' => 'message ' . $minute,
                'creationDatetime' => new \DateTime(sprintf('2026-09-10 20:%02d:00', $minute)),
            ])->create();
        }

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages?page=2');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/messages',
            '@type' => 'Collection',
            'totalItems' => 52,
            'member' => [
                $this->expectedMessage($messages[2]->id, $space, $member, 'batteur', 'message 2', '2026-09-10T20:02:00+00:00'),
                $this->expectedMessage($messages[1]->id, $space, $member, 'batteur', 'message 1', '2026-09-10T20:01:00+00:00'),
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $space->id . '/chat/messages?page=2',
                '@type' => 'PartialCollectionView',
                'first' => '/api/band_spaces/' . $space->id . '/chat/messages?page=1',
                'last' => '/api/band_spaces/' . $space->id . '/chat/messages?page=2',
                'previous' => '/api/band_spaces/' . $space->id . '/chat/messages?page=1',
            ],
        ]);
    }

    public function test_a_non_member_cannot_read_the_channel(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create(['username' => 'inconnu', 'email' => 'inconnu@test.com']);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $this->channelOf($space);

        $this->client->loginUser($stranger);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous n\'êtes pas membre de ce Band Space',
            'description' => 'Vous n\'êtes pas membre de ce Band Space',
            'status' => 403,
            'type' => '/errors/403',
        ]);
    }

    public function test_a_band_space_that_does_not_exist_is_a_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/3f2504e0-4f89-11d3-9a0c-0305e82c3301/chat/messages');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Band Space introuvable',
            'description' => 'Band Space introuvable',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_a_deleted_author_is_not_named(): void
    {
        // DeleteAccountProcedure rewrites the handle to `deleted_<uuid>` and nulls the picture, so the
        // renderer must not print either.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $gone = UserFactory::new()->asBaseUser()->create([
            'username' => 'deleted_3f2504e0',
            'email' => 'gone@test.com',
            'deletionDatetime' => new \DateTimeImmutable('2026-09-01 10:00:00'),
        ]);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);

        $message = MessageFactory::new([
            'thread' => $channel,
            'author' => $gone,
            'content' => 'salut',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/messages',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                $this->expectedMessage($message->id, $space, $gone, 'Utilisateur supprimé', 'salut', '2026-09-10T20:00:00+00:00'),
            ],
        ]);
    }

    public function test_an_author_avatar_survives_the_projection(): void
    {
        // The projection reduces the picture to its imageName, and the URL is rebuilt from that alone
        // rather than from the entity. Nothing else in the suite covers a non-null avatar, so without
        // this the rebuild could return null for every real picture and no test would notice.
        $member = UserFactory::new()->asBaseUser()->create([
            'username' => 'batteur',
            'email' => 'batteur@test.com',
            'profilePicture' => UserProfilePictureFactory::new(['imageName' => 'batteur-avatar.jpg']),
        ]);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);

        MessageFactory::new([
            'thread' => $channel,
            'author' => $member,
            'content' => 'salut',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages');

        $this->assertResponseIsSuccessful();
        $url = $this->getResponseAsArray()['member'][0]['author_profile_picture_url'];
        $this->assertIsString($url);
        $this->assertStringContainsString('batteur-avatar.jpg', $url);
        $this->assertStringContainsString('user_profile_picture_small', $url);
    }

    public function test_the_list_never_loads_an_author_profile_table(): void
    {
        // The trap #960 names: User carries three inverse one-to-one profile associations Doctrine
        // cannot make lazy (#730), so hydrating one author costs four selects. Ten distinct authors on
        // one page is where that becomes forty. The projection is what stops it, and this is what fails
        // if anyone puts entity hydration back.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);

        foreach (range(1, 10) as $index) {
            $author = UserFactory::new()->asBaseUser()->create([
                'username' => 'auteur_' . $index,
                'email' => 'auteur' . $index . '@test.com',
            ]);
            MessageFactory::new([
                'thread' => $channel,
                'author' => $author,
                'content' => 'message ' . $index,
                'creationDatetime' => new \DateTime(sprintf('2026-09-10 20:%02d:00', $index)),
            ])->create();
        }

        $this->client->loginUser($member);
        $this->client->enableProfiler();
        // The factories above left every author managed, which would hide the very lazy loads this
        // test exists to count.
        self::getContainer()->get('doctrine')->getManager()->clear();
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages');

        $this->assertResponseIsSuccessful();
        foreach (['user_musician_profile', 'user_notification_preference', 'user_teacher_profile'] as $profileTable) {
            // At most one, and it is not an author: the firewall hydrates the authenticated viewer
            // before the provider runs. Ten authors hydrated the old way would be ten here, or thirty
            // across the three tables, so this still fails loudly if the projection is undone.
            $this->assertLessThanOrEqual(
                1,
                count($this->queriesMatching($profileTable)),
                sprintf('Listing a channel must not hydrate its authors, and %s says it did', $profileTable),
            );
        }

        // And the whole request does not grow with the number of distinct authors: measured at 8 for
        // this page of ten, which is the viewer, the space with its memberships, the channel, the
        // list and the count. The margin is there for a change to the firewall, not for a per-author
        // query: hydrating ten authors would put this near fifty.
        $this->assertLessThanOrEqual(
            10,
            $this->client->getProfile()->getCollector('db')->getQueryCount(),
            'The message list must cost a fixed number of queries whatever the author count',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedMessage(
        string $id,
        BandSpace $space,
        User $author,
        string $expectedUsername,
        string $content,
        string $creationDatetime,
    ): array {
        return [
            // Composite, because the resource declares two identifiers and has no item operation of
            // its own. Unique and stable, which is all the client merges pages on; a dereferenceable
            // item URL arrives with the edit and delete endpoints (#966, #967).
            '@id' => '/api/chat_messages/id=' . $id . ';bandSpaceId=' . $space->id,
            '@type' => 'ChatMessage',
            'id' => $id,
            'band_space_id' => (string) $space->id,
            'author_id' => (string) $author->id,
            'author_username' => $expectedUsername,
            'author_profile_picture_url' => null,
            'content' => $content,
            'creation_datetime' => $creationDatetime,
        ];
    }

    private function channelOf(BandSpace $bandSpace): MessageThread
    {
        return MessageThreadFactory::new()->forBandSpace($bandSpace)->create();
    }

    /**
     * @return list<string>
     */
    private function queriesMatching(string $needle): array
    {
        $profile = $this->client->getProfile();
        $this->assertNotFalse($profile, 'The profiler must be enabled to inspect the queries.');

        $matching = [];
        foreach ($profile->getCollector('db')->getQueries()['default'] ?? [] as $query) {
            $sql = (string) $query['sql'];
            if (str_contains($sql, $needle)) {
                $matching[] = $sql;
            }
        }

        return $matching;
    }
}
