<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\BandSpaceOpenedItem;
use App\Enum\BandSpace\BandSpaceSearchResultType;
use App\Enum\BandSpace\MembershipStatus;
use App\Repository\BandSpace\BandSpaceOpenedItemRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\BandSpaceOpenedItemFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Uuid;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A member's own history of opened items, behind the palette's « Ouverts par moi » order (#1047).
 */
#[ResetDatabase]
class BandSpaceOpenedItemRecordTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    private const string TASK_ID = '0b9f6f3e-6c1a-4c2e-9a51-2f4d8e7c1a01';

    public function test_an_open_is_recorded(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $bandSpace->id . '/opened_items', ['type' => 'task', 'resource_id' => self::TASK_ID], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertSame([['task', self::TASK_ID]], $this->historyOf($membership));
    }

    public function test_opening_an_item_again_moves_it_up_without_a_second_row(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        BandSpaceOpenedItemFactory::new(['membership' => $membership, 'resourceId' => self::TASK_ID, 'openedDatetime' => new \DateTimeImmutable('2026-01-01 10:00:00')])->create();
        BandSpaceOpenedItemFactory::new(['membership' => $membership, 'type' => BandSpaceSearchResultType::Note, 'resourceId' => '5d1c2b3a-0f9e-4d8c-b7a6-1e2f3a4b5c6d', 'openedDatetime' => new \DateTimeImmutable('2026-01-02 10:00:00')])->create();

        $this->client->loginUser($user);
        // Upper case, as a UUID may be written: it must land on the same row.
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $bandSpace->id . '/opened_items', ['type' => 'task', 'resource_id' => strtoupper(self::TASK_ID)], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame([['task', self::TASK_ID], ['note', '5d1c2b3a-0f9e-4d8c-b7a6-1e2f3a4b5c6d']], $this->historyOf($membership));
    }

    public function test_the_history_keeps_the_fifty_latest_opens(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $oldest = BandSpaceOpenedItemFactory::new(['membership' => $membership, 'openedDatetime' => new \DateTimeImmutable('2026-01-01 00:00:00')])->create();
        for ($minute = 1; $minute < 50; ++$minute) {
            BandSpaceOpenedItemFactory::new(['membership' => $membership, 'openedDatetime' => new \DateTimeImmutable('2026-02-01 00:00:00')->modify('+' . $minute . ' minutes')])->create();
        }
        // Another member's history is theirs to keep, whatever its size.
        BandSpaceOpenedItemFactory::new(['membership' => BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace]), 'openedDatetime' => new \DateTimeImmutable('2025-01-01 00:00:00')])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $bandSpace->id . '/opened_items', ['type' => 'task', 'resource_id' => self::TASK_ID], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $history = $this->historyOf($membership);
        $this->assertCount(50, $history);
        $this->assertSame(['task', self::TASK_ID], $history[0]);
        $this->assertNotContains(['task', $oldest->resourceId], $history);
        $this->assertCount(51, self::getContainer()->get(BandSpaceOpenedItemRepository::class)->findAll());
    }

    public function test_a_space_pending_deletion_still_records(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['deletionScheduledDatetime' => new \DateTimeImmutable('-1 day')])->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $bandSpace->id . '/opened_items', ['type' => 'file', 'resource_id' => self::TASK_ID], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame([['file', self::TASK_ID]], $this->historyOf($membership));
    }

    public function test_an_unknown_type_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $bandSpace->id . '/opened_items', ['type' => 'member', 'resource_id' => self::TASK_ID], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'type',
                    'message' => 'Type de résultat inconnu',
                    'code' => Choice::NO_SUCH_CHOICE_ERROR,
                ],
            ],
            'detail' => 'type: Type de résultat inconnu',
            'type' => '/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            'title' => 'An error occurred',
            'description' => 'type: Type de résultat inconnu',
        ]);
        $this->assertSame([], $this->historyOf($membership));
    }

    public function test_an_id_that_is_not_a_uuid_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $bandSpace->id . '/opened_items', ['type' => 'task', 'resource_id' => 'not-a-uuid'], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Uuid::INVALID_CHARACTERS_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'resource_id',
                    'message' => 'L\'identifiant est invalide',
                    'code' => Uuid::INVALID_CHARACTERS_ERROR,
                ],
            ],
            'detail' => 'resource_id: L\'identifiant est invalide',
            'type' => '/validation_errors/' . Uuid::INVALID_CHARACTERS_ERROR,
            'title' => 'An error occurred',
            'description' => 'resource_id: L\'identifiant est invalide',
        ]);
        $this->assertSame([], $this->historyOf($membership));
    }

    public function test_a_non_member_cannot_record(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $stranger = UserFactory::new()->create(['username' => 'stranger', 'email' => 'stranger@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();

        $this->client->loginUser($stranger);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $bandSpace->id . '/opened_items', ['type' => 'task', 'resource_id' => self::TASK_ID], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Vous n'êtes pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Vous n'êtes pas membre de ce Band Space",
        ]);
        $this->assertCount(0, self::getContainer()->get(BandSpaceOpenedItemRepository::class)->findAll());
    }

    public function test_a_member_who_left_cannot_record(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user, 'status' => MembershipStatus::Left])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $bandSpace->id . '/opened_items', ['type' => 'task', 'resource_id' => self::TASK_ID], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Vous n'êtes pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Vous n'êtes pas membre de ce Band Space",
        ]);
        $this->assertSame([], $this->historyOf($membership));
    }

    public function test_recording_needs_a_session(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();

        $this->client->jsonRequest('POST', '/api/band_spaces/' . $bandSpace->id . '/opened_items', ['type' => 'task', 'resource_id' => self::TASK_ID], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    /** @return list<array{string, string}> type and id, newest first */
    private function historyOf(BandSpaceMembership $membership): array
    {
        $rows = self::getContainer()->get(BandSpaceOpenedItemRepository::class)->findBy(['membership' => $membership->id], ['openedDatetime' => 'DESC', 'id' => 'DESC']);

        return array_map(static fn (BandSpaceOpenedItem $row): array => [$row->type->value, $row->resourceId], $rows);
    }
}
