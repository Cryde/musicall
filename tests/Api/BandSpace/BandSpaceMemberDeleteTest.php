<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace;

use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\Role;
use App\Enum\BandSpace\TaskStatus;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\TaskCommentRepository;
use App\Repository\BandSpace\TaskRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TaskCommentFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceMemberDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_kick_member(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();

        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $memberMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($admin);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/members/' . $memberMembership->id
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $membershipRepository = self::getContainer()->get(BandSpaceMembershipRepository::class);
        $this->assertFalse($membershipRepository->isMember($bandSpace, $member));

        $activityRepo = self::getContainer()->get(BandSpaceActivityRepository::class);
        $activities = $activityRepo->findForResource($bandSpace, BandSpaceModule::Settings, $member->id);
        $this->assertCount(1, $activities);
        $this->assertSame('member_removed', $activities[0]->type);
        $this->assertSame(
            ['target_user_id' => $member->id, 'target_username' => 'member_user'],
            $activities[0]->payload,
        );
        $this->assertSame($admin->id, $activities[0]->actor?->id);
    }

    public function test_kick_revokes_the_member_task_assignments(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();

        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        $memberMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $other, 'role' => Role::User])->create();

        $sharedTask = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Réserver la salle',
            'assignees' => new ArrayCollection([$member, $other]),
        ])->create();
        $soleTask = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Envoyer le dossier',
            'assignees' => new ArrayCollection([$member]),
        ])->create();
        // Done and archived work is covered too: an assignment says who is on the hook now, and
        // somebody who is out of the band is on the hook for nothing.
        $archivedTask = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Imprimer les affiches',
            'status' => TaskStatus::Done,
            'archiveDatetime' => new \DateTimeImmutable('2026-01-05 10:00:00'),
            'assignees' => new ArrayCollection([$member]),
        ])->create();
        TaskCommentFactory::new([
            'task' => $sharedTask,
            'author' => $member,
            'content' => 'Je m\'y mets demain',
            'creationDatetime' => new \DateTime('2026-01-01 10:00:00'),
        ])->create();

        $sharedTaskId = (string) $sharedTask->id;
        $soleTaskId = (string) $soleTask->id;
        $archivedTaskId = (string) $archivedTask->id;

        $this->client->loginUser($admin);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/members/' . $memberMembership->id
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $taskRepository = self::getContainer()->get(TaskRepository::class);
        $this->assertCount(0, $taskRepository->findByBandSpaceAndAssignee($bandSpace, $member));
        // The member who stayed keeps the task they shared: only the departing one comes off.
        $this->assertCount(1, $taskRepository->findByBandSpaceAndAssignee($bandSpace, $other));

        $reloadedShared = $taskRepository->findOneByIdAndBandSpace($sharedTaskId, $bandSpace);
        $this->assertNotNull($reloadedShared);
        $this->assertSame(
            [(string) $other->id],
            array_map(static fn (User $user): string => (string) $user->id, $reloadedShared->assignees->toArray()),
        );

        // What happened is kept: the comment they wrote and its authorship survive the departure.
        $comments = self::getContainer()->get(TaskCommentRepository::class)->findByTask($reloadedShared);
        $this->assertCount(1, $comments);
        $this->assertSame((string) $member->id, (string) $comments[0]->author->id);

        // Every revocation lands in the task's own activity feed, so the band can see the work that
        // came free and when.
        $activityRepository = self::getContainer()->get(BandSpaceActivityRepository::class);
        foreach ([$sharedTaskId, $soleTaskId, $archivedTaskId] as $taskId) {
            $activities = $activityRepository->findForResource($bandSpace, BandSpaceModule::Task, $taskId);
            $this->assertCount(1, $activities);
            $this->assertSame('assignee_removed', $activities[0]->type);
            $this->assertSame(
                ['assignee_id' => $member->id, 'assignee_username' => 'member_user'],
                $activities[0]->payload,
            );
            $this->assertSame($admin->id, $activities[0]->actor?->id);
        }
    }

    public function test_cannot_kick_self(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();

        $adminMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();

        $this->client->loginUser($admin);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/members/' . $adminMembership->id
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous ne pouvez pas vous exclure vous-même. Utilisez la fonction "Quitter"',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Vous ne pouvez pas vous exclure vous-même. Utilisez la fonction "Quitter"',
        ]);
    }

    public function test_non_admin_cannot_kick(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();

        $adminMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $admin, 'role' => Role::Admin])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($member);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/members/' . $adminMembership->id
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous devez être administrateur pour effectuer cette action',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous devez être administrateur pour effectuer cette action',
        ]);
    }

    public function test_kick_member_from_other_band_space(): void
    {
        $admin = UserFactory::new()->asBaseUser()->create();
        $member = UserFactory::new()->create(['username' => 'member_user', 'email' => 'member@test.com']);
        $bandSpace1 = BandSpaceFactory::new()->create();
        $bandSpace2 = BandSpaceFactory::new()->create();

        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace1, 'user' => $admin, 'role' => Role::Admin])->create();
        $memberMembership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace2, 'user' => $member, 'role' => Role::User])->create();

        $this->client->loginUser($admin);
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace1->id . '/members/' . $memberMembership->id
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Membre introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Membre introuvable',
        ]);
    }
}
