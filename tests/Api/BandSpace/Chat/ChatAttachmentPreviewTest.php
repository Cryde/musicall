<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Resolving a pasted Band Space URL into an attachment (#972). The composer asks this before it shows
 * a chip, and the answer has to be the send's own: a 404 wherever the message would be refused.
 */
#[ResetDatabase]
class ChatAttachmentPreviewTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $space = BandSpaceFactory::new()->create();

        $this->preview($space, 'task-3f2504e0-4f89-41d3-9a0c-0305e82c3301');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_a_member_previews_an_object_of_their_space(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $task = TaskFactory::new()->create(['bandSpace' => $space, 'title' => 'Réparer l\'ampli']);
        $identifier = 'task-' . $task->id;

        $this->client->loginUser($member);
        $this->preview($space, $identifier);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatAttachmentPreview',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/attachment_previews/' . $identifier,
            '@type' => 'ChatAttachmentPreview',
            'id' => $identifier,
            'band_space_id' => (string) $space->id,
            'type' => 'task',
            'resource_id' => (string) $task->id,
            'title' => 'Réparer l\'ampli',
        ]);
    }

    /** A URL copied from another band is refused here, not only filtered out by the composer. */
    public function test_an_object_of_another_space_is_not_found(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $elsewhere = TaskFactory::new()->create(['bandSpace' => BandSpaceFactory::new()->create(), 'title' => 'Secret']);

        $this->client->loginUser($member);
        $this->preview($space, 'task-' . $elsewhere->id);

        $this->assertNotFound();
    }

    public function test_another_member_s_personal_entry_is_not_found(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'bassiste', 'email' => 'bassiste@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $otherMembership = BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $other])->create();
        $personal = FinanceEntryFactory::new()->create([
            'category' => FinanceCategoryFactory::new()->create(['bandSpace' => $space]),
            'label' => 'Cordes de la bassiste',
            'scope' => FinanceEntryScope::Personal,
            'member' => $otherMembership,
        ]);

        $this->client->loginUser($member);
        $this->preview($space, 'finance-' . $personal->id);

        $this->assertNotFound();
    }

    public function test_a_malformed_identifier_is_not_found(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();

        $this->client->loginUser($member);
        $this->preview($space, 'concert-pas-un-uuid');

        $this->assertNotFound();
    }

    public function test_a_non_member_cannot_preview(): void
    {
        $outsider = UserFactory::new()->asBaseUser()->create(['username' => 'intrus', 'email' => 'intrus@test.com']);
        $space = BandSpaceFactory::new()->create();
        $task = TaskFactory::new()->create(['bandSpace' => $space, 'title' => 'Réparer l\'ampli']);

        $this->client->loginUser($outsider);
        $this->preview($space, 'task-' . $task->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous n\'êtes pas membre de ce Band Space',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous n\'êtes pas membre de ce Band Space',
        ]);
    }

    private function assertNotFound(): void
    {
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Élément introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Élément introuvable',
        ]);
    }

    private function preview(BandSpace $space, string $identifier): void
    {
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $space->id . '/chat/attachment_previews/' . $identifier,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );
    }
}
