<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class ChatMessagePinTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged_cannot_pin(): void
    {
        $space = BandSpaceFactory::new()->create();
        $channel = $this->channelOf($space);
        $message = $this->message($channel, UserFactory::new()->asBaseUser()->create());

        $this->client->request('POST', $this->pinUrl($space, $message));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_not_logged_cannot_unpin(): void
    {
        $space = BandSpaceFactory::new()->create();
        $channel = $this->channelOf($space);
        $message = $this->message($channel, UserFactory::new()->asBaseUser()->create());

        $this->client->request('DELETE', $this->pinUrl($space, $message));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_a_member_pins_a_message(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->message($this->channelOf($space), $member, 'code de la porte 4512');

        $this->client->loginUser($member);
        $this->client->request('POST', $this->pinUrl($space, $message));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $pinned = self::getContainer()->get(MessageRepository::class)->find($message->id);
        $this->assertNotNull($pinned->pinnedDatetime);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/chat_messages/id=' . $message->id . ';bandSpaceId=' . $space->id,
            '@type' => 'ChatMessage',
            'id' => (string) $message->id,
            'band_space_id' => (string) $space->id,
            'author_id' => (string) $member->id,
            'author_username' => 'batteur',
            'author_profile_picture_url' => null,
            'content' => 'code de la porte 4512',
            'creation_datetime' => '2026-09-10T20:00:00+00:00',
            'is_deleted' => false,
            'update_datetime' => null,
            'editable_content' => 'code de la porte 4512',
            'reactions' => [],
            'attachments' => [],
            'is_pinned' => true,
            // Stamped by the server, so read back rather than pinned to a literal.
            'pinned_datetime' => $pinned->pinnedDatetime->format('c'),
            'pinned_by_username' => 'batteur',
        ]);
    }

    public function test_pinning_an_already_pinned_message_changes_nothing(): void
    {
        // Two members reaching for the same message both meant « keep this at the top », so the second
        // one must not re-stamp the pin under their own name and move it to the front of the bar.
        $pinner = UserFactory::new()->asBaseUser()->create(['username' => 'chanteuse', 'email' => 'chanteuse@test.com']);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $pinner])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->message(
            $this->channelOf($space),
            $pinner,
            'adresse de la salle',
            new \DateTimeImmutable('2026-09-11 09:00:00'),
            $pinner,
        );

        $this->client->loginUser($member);
        $this->client->request('POST', $this->pinUrl($space, $message));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/chat_messages/id=' . $message->id . ';bandSpaceId=' . $space->id,
            '@type' => 'ChatMessage',
            'id' => (string) $message->id,
            'band_space_id' => (string) $space->id,
            'author_id' => (string) $pinner->id,
            'author_username' => 'chanteuse',
            'author_profile_picture_url' => null,
            'content' => 'adresse de la salle',
            'creation_datetime' => '2026-09-10T20:00:00+00:00',
            'is_deleted' => false,
            'update_datetime' => null,
            'editable_content' => null,
            'reactions' => [],
            'attachments' => [],
            'is_pinned' => true,
            'pinned_datetime' => '2026-09-11T09:00:00+00:00',
            'pinned_by_username' => 'chanteuse',
        ]);
    }

    public function test_a_member_unpins_a_message(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->message(
            $this->channelOf($space),
            $member,
            'ancien code de la porte',
            new \DateTimeImmutable('2026-09-11 09:00:00'),
            $member,
        );

        $this->client->loginUser($member);
        $this->client->request('DELETE', $this->pinUrl($space, $message));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/chat_messages/id=' . $message->id . ';bandSpaceId=' . $space->id,
            '@type' => 'ChatMessage',
            'id' => (string) $message->id,
            'band_space_id' => (string) $space->id,
            'author_id' => (string) $member->id,
            'author_username' => 'batteur',
            'author_profile_picture_url' => null,
            'content' => 'ancien code de la porte',
            'creation_datetime' => '2026-09-10T20:00:00+00:00',
            'is_deleted' => false,
            'update_datetime' => null,
            'editable_content' => 'ancien code de la porte',
            'reactions' => [],
            'attachments' => [],
            'is_pinned' => false,
            'pinned_datetime' => null,
            'pinned_by_username' => null,
        ]);
        $unpinned = self::getContainer()->get(MessageRepository::class)->find($message->id);
        $this->assertNull($unpinned->pinnedDatetime);
        $this->assertNull($unpinned->pinnedBy);
    }

    public function test_a_member_unpins_somebody_else_s_pin(): void
    {
        // The bar belongs to the band, so a door code that has changed can be taken down by whoever
        // notices rather than only by whoever put it up.
        $pinner = UserFactory::new()->asBaseUser()->create(['username' => 'chanteuse', 'email' => 'chanteuse@test.com']);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $pinner])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->message(
            $this->channelOf($space),
            $pinner,
            'ancienne adresse',
            new \DateTimeImmutable('2026-09-11 09:00:00'),
            $pinner,
        );

        $this->client->loginUser($member);
        $this->client->request('DELETE', $this->pinUrl($space, $message));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/chat_messages/id=' . $message->id . ';bandSpaceId=' . $space->id,
            '@type' => 'ChatMessage',
            'id' => (string) $message->id,
            'band_space_id' => (string) $space->id,
            'author_id' => (string) $pinner->id,
            'author_username' => 'chanteuse',
            'author_profile_picture_url' => null,
            'content' => 'ancienne adresse',
            'creation_datetime' => '2026-09-10T20:00:00+00:00',
            'is_deleted' => false,
            'update_datetime' => null,
            'editable_content' => null,
            'reactions' => [],
            'attachments' => [],
            'is_pinned' => false,
            'pinned_datetime' => null,
            'pinned_by_username' => null,
        ]);
    }

    public function test_unpinning_a_message_that_is_not_pinned_is_a_404(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->message($this->channelOf($space), $member);

        $this->client->loginUser($member);
        $this->client->request('DELETE', $this->pinUrl($space, $message));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ce message n\'est pas épinglé',
            'description' => 'Ce message n\'est pas épinglé',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_the_eleventh_pin_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);
        foreach (range(1, 10) as $index) {
            $this->message(
                $channel,
                $member,
                'info ' . $index,
                new \DateTimeImmutable(sprintf('2026-09-11 09:%02d:00', $index)),
                $member,
            );
        }
        $eleventh = $this->message($channel, $member, 'une info de trop');

        $this->client->loginUser($member);
        $this->client->request('POST', $this->pinUrl($space, $eleventh));

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cette conversation a déjà 10 messages épinglés. Détachez-en un avant d\'en épingler un autre.',
            'description' => 'Cette conversation a déjà 10 messages épinglés. Détachez-en un avant d\'en épingler un autre.',
            'status' => 409,
            'type' => '/errors/409',
        ]);
        $this->assertNull(
            self::getContainer()->get(MessageRepository::class)->find($eleventh->id)->pinnedDatetime,
        );
    }

    public function test_a_non_member_cannot_pin(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create(['username' => 'inconnu', 'email' => 'inconnu@test.com']);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->message($this->channelOf($space), $member);

        $this->client->loginUser($stranger);
        $this->client->request('POST', $this->pinUrl($space, $message));

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

    public function test_a_deleted_message_cannot_be_pinned(): void
    {
        // Nothing left to keep at the top, and the bar would carry an entry reading « Message
        // supprimé » (#967).
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = MessageFactory::new([
            'thread' => $this->channelOf($space),
            'author' => $member,
            'content' => '',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
            'deletionDatetime' => new \DateTimeImmutable('2026-09-11 09:00:00'),
        ])->create();

        $this->client->loginUser($member);
        $this->client->request('POST', $this->pinUrl($space, $message));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Message introuvable',
            'description' => 'Message introuvable',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_an_unknown_message_is_a_404(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $this->channelOf($space);

        $this->client->loginUser($member);
        $this->client->request(
            'POST',
            '/api/band_spaces/' . $space->id . '/chat/messages/3f2504e0-4f89-11d3-9a0c-0305e82c3301/pin',
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Message introuvable',
            'description' => 'Message introuvable',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_a_message_from_another_band_channel_is_a_404(): void
    {
        // The lookup is scoped to the space in the URI, so a message id borrowed from a band the
        // caller also belongs to cannot be pinned into this one's bar.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create(['name' => 'Les Trois Accords']);
        $otherSpace = BandSpaceFactory::new()->create(['name' => 'Autre groupe']);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $otherSpace, 'user' => $member])->create();
        $this->channelOf($space);
        $elsewhere = $this->message($this->channelOf($otherSpace), $member);

        $this->client->loginUser($member);
        $this->client->request('POST', $this->pinUrl($space, $elsewhere));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Message introuvable',
            'description' => 'Message introuvable',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_a_non_member_cannot_unpin(): void
    {
        // Its own test rather than a variation of the pin one: unpinning runs through a separate
        // processor class, so a member check dropped from that one alone would go unnoticed.
        $stranger = UserFactory::new()->asBaseUser()->create(['username' => 'inconnu', 'email' => 'inconnu@test.com']);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->message(
            $this->channelOf($space),
            $member,
            'code de la porte',
            new \DateTimeImmutable('2026-09-11 09:00:00'),
            $member,
        );

        $this->client->loginUser($stranger);
        $this->client->request('DELETE', $this->pinUrl($space, $message));

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
        $this->assertNotNull(
            self::getContainer()->get(MessageRepository::class)->find($message->id)->pinnedDatetime,
        );
    }

    public function test_unpinning_an_unknown_message_is_a_404(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $this->channelOf($space);

        $this->client->loginUser($member);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $space->id . '/chat/messages/3f2504e0-4f89-11d3-9a0c-0305e82c3301/pin',
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Message introuvable',
            'description' => 'Message introuvable',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_unpinning_a_message_from_another_band_channel_is_a_404(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create(['name' => 'Les Trois Accords']);
        $otherSpace = BandSpaceFactory::new()->create(['name' => 'Autre groupe']);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $otherSpace, 'user' => $member])->create();
        $this->channelOf($space);
        $elsewhere = $this->message(
            $this->channelOf($otherSpace),
            $member,
            'leur code',
            new \DateTimeImmutable('2026-09-11 09:00:00'),
            $member,
        );

        $this->client->loginUser($member);
        $this->client->request('DELETE', $this->pinUrl($space, $elsewhere));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Message introuvable',
            'description' => 'Message introuvable',
            'status' => 404,
            'type' => '/errors/404',
        ]);
        $this->assertNotNull(
            self::getContainer()->get(MessageRepository::class)->find($elsewhere->id)->pinnedDatetime,
        );
    }

    public function test_a_pin_by_a_deleted_account_is_not_named_on_the_message_itself(): void
    {
        // The sibling of the collection's deleted-pinner test, on the other builder entry point:
        // buildItem() reads the relation off the entity where buildFromProjection() reads scalars, so
        // the substitution has to be proven on both and not inferred from one.
        $gone = UserFactory::new()->asBaseUser()->create([
            'username' => 'deleted_3f2504e0',
            'email' => 'gone@test.com',
            'deletionDatetime' => new \DateTimeImmutable('2026-09-01 10:00:00'),
        ]);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->message(
            $this->channelOf($space),
            $member,
            'code de la porte 4512',
            new \DateTimeImmutable('2026-09-11 09:00:00'),
            $gone,
        );

        // Pinning it again is the no-op branch, which is the one that answers from the stored pin.
        $this->client->loginUser($member);
        $this->client->request('POST', $this->pinUrl($space, $message));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/chat_messages/id=' . $message->id . ';bandSpaceId=' . $space->id,
            '@type' => 'ChatMessage',
            'id' => (string) $message->id,
            'band_space_id' => (string) $space->id,
            'author_id' => (string) $member->id,
            'author_username' => 'batteur',
            'author_profile_picture_url' => null,
            'content' => 'code de la porte 4512',
            'creation_datetime' => '2026-09-10T20:00:00+00:00',
            'is_deleted' => false,
            'update_datetime' => null,
            'editable_content' => 'code de la porte 4512',
            'reactions' => [],
            'attachments' => [],
            'is_pinned' => true,
            'pinned_datetime' => '2026-09-11T09:00:00+00:00',
            'pinned_by_username' => 'Utilisateur supprimé',
        ]);
    }

    public function test_a_space_pending_deletion_refuses_the_pin(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create(['deletionScheduledDatetime' => new \DateTimeImmutable('+30 days')]);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->message($this->channelOf($space), $member);

        $this->client->loginUser($member);
        $this->client->request('POST', $this->pinUrl($space, $message));

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
            'description' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
        ]);
    }

    public function test_a_space_pending_deletion_refuses_the_unpin(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create(['deletionScheduledDatetime' => new \DateTimeImmutable('+30 days')]);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->message(
            $this->channelOf($space),
            $member,
            'code de la porte',
            new \DateTimeImmutable('2026-09-11 09:00:00'),
            $member,
        );

        $this->client->loginUser($member);
        $this->client->request('DELETE', $this->pinUrl($space, $message));

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
            'description' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
        ]);
        $this->assertNotNull(
            self::getContainer()->get(MessageRepository::class)->find($message->id)->pinnedDatetime,
        );
    }

    private function channelOf(BandSpace $bandSpace): MessageThread
    {
        return MessageThreadFactory::new()->forBandSpace($bandSpace)->create();
    }

    private function message(
        MessageThread $channel,
        User $author,
        string $content = 'une info',
        ?\DateTimeImmutable $pinnedDatetime = null,
        ?User $pinnedBy = null,
    ): Message {
        return MessageFactory::new([
            'thread' => $channel,
            'author' => $author,
            'content' => $content,
            // Pinned rather than left to faker: the response body is asserted whole.
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
            'pinnedDatetime' => $pinnedDatetime,
            'pinnedBy' => $pinnedBy,
        ])->create();
    }

    private function pinUrl(BandSpace $bandSpace, Message $message): string
    {
        return '/api/band_spaces/' . $bandSpace->id . '/chat/messages/' . $message->id . '/pin';
    }
}
