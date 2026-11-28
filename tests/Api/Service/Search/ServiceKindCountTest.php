<?php

declare(strict_types=1);

namespace App\Tests\Api\Service\Search;

use App\Entity\Service\Service;
use App\Enum\Service\ServiceKind;
use App\Enum\Service\ServiceStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** The « Annuaire » counts what each kind's search lists without a city (#1414). */
#[ResetDatabase]
class ServiceKindCountTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_every_kind_is_counted_in_its_order_with_zeros(): void
    {
        $this->service(ServiceKind::Teaching, 'teacher_1');
        $this->service(ServiceKind::Teaching, 'teacher_2');
        $this->service(ServiceKind::SoundEngineering, 'engineer');
        $this->service(ServiceKind::Photography, 'photographer');
        $this->service(ServiceKind::Production, 'producer');
        // A member offering a second kind is counted under each.
        $videographer = $this->service(ServiceKind::Video, 'videographer');
        ServiceFactory::new()->production()->create(['user' => $videographer->user]);

        $this->client->request('GET', '/api/services/kinds');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ServiceKindCount',
            '@id' => '/api/services/kinds',
            '@type' => 'Collection',
            'totalItems' => 7,
            'member' => [
                ['@id' => '/api/services/kinds/teaching', '@type' => 'ServiceKindCount', 'kind' => 'teaching', 'count' => 2],
                ['@id' => '/api/services/kinds/sound_engineering', '@type' => 'ServiceKindCount', 'kind' => 'sound_engineering', 'count' => 1],
                ['@id' => '/api/services/kinds/photography', '@type' => 'ServiceKindCount', 'kind' => 'photography', 'count' => 1],
                ['@id' => '/api/services/kinds/video', '@type' => 'ServiceKindCount', 'kind' => 'video', 'count' => 1],
                ['@id' => '/api/services/kinds/graphic_design', '@type' => 'ServiceKindCount', 'kind' => 'graphic_design', 'count' => 0],
                ['@id' => '/api/services/kinds/web', '@type' => 'ServiceKindCount', 'kind' => 'web', 'count' => 0],
                ['@id' => '/api/services/kinds/production', '@type' => 'ServiceKindCount', 'kind' => 'production', 'count' => 2],
            ],
        ]);
    }

    public function test_drafts_private_suspended_and_closed_members_are_not_counted(): void
    {
        $this->service(ServiceKind::Photography, 'shown');
        $this->service(ServiceKind::Photography, 'draft')->status = ServiceStatus::Draft;
        $this->service(ServiceKind::Photography, 'private')->user->profile->isPublic = false;
        $this->service(ServiceKind::Photography, 'suspended')->user->suspensionDatetime = new DateTimeImmutable('2026-01-01');
        $this->service(ServiceKind::Photography, 'closed')->user->deletionDatetime = new DateTimeImmutable('2026-01-01');
        // The only web service is a draft: the kind still shows, at zero.
        $this->service(ServiceKind::Web, 'web_draft')->status = ServiceStatus::Draft;
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->client->request('GET', '/api/services/kinds');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ServiceKindCount',
            '@id' => '/api/services/kinds',
            '@type' => 'Collection',
            'totalItems' => 7,
            'member' => [
                ['@id' => '/api/services/kinds/teaching', '@type' => 'ServiceKindCount', 'kind' => 'teaching', 'count' => 0],
                ['@id' => '/api/services/kinds/sound_engineering', '@type' => 'ServiceKindCount', 'kind' => 'sound_engineering', 'count' => 0],
                ['@id' => '/api/services/kinds/photography', '@type' => 'ServiceKindCount', 'kind' => 'photography', 'count' => 1],
                ['@id' => '/api/services/kinds/video', '@type' => 'ServiceKindCount', 'kind' => 'video', 'count' => 0],
                ['@id' => '/api/services/kinds/graphic_design', '@type' => 'ServiceKindCount', 'kind' => 'graphic_design', 'count' => 0],
                ['@id' => '/api/services/kinds/web', '@type' => 'ServiceKindCount', 'kind' => 'web', 'count' => 0],
                ['@id' => '/api/services/kinds/production', '@type' => 'ServiceKindCount', 'kind' => 'production', 'count' => 0],
            ],
        ]);
    }

    public function test_a_member_blocked_either_way_is_not_counted(): void
    {
        $viewer = UserFactory::new()->create(['username' => 'viewer']);
        $this->service(ServiceKind::SoundEngineering, 'shown');
        $blockedByViewer = $this->service(ServiceKind::SoundEngineering, 'blocked_by_viewer');
        $blockingViewer = $this->service(ServiceKind::SoundEngineering, 'blocking_viewer');
        UserBlockFactory::new()->create(['blocker' => $viewer, 'blocked' => $blockedByViewer->user]);
        UserBlockFactory::new()->create(['blocker' => $blockingViewer->user, 'blocked' => $viewer]);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/services/kinds');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ServiceKindCount',
            '@id' => '/api/services/kinds',
            '@type' => 'Collection',
            'totalItems' => 7,
            'member' => [
                ['@id' => '/api/services/kinds/teaching', '@type' => 'ServiceKindCount', 'kind' => 'teaching', 'count' => 0],
                ['@id' => '/api/services/kinds/sound_engineering', '@type' => 'ServiceKindCount', 'kind' => 'sound_engineering', 'count' => 1],
                ['@id' => '/api/services/kinds/photography', '@type' => 'ServiceKindCount', 'kind' => 'photography', 'count' => 0],
                ['@id' => '/api/services/kinds/video', '@type' => 'ServiceKindCount', 'kind' => 'video', 'count' => 0],
                ['@id' => '/api/services/kinds/graphic_design', '@type' => 'ServiceKindCount', 'kind' => 'graphic_design', 'count' => 0],
                ['@id' => '/api/services/kinds/web', '@type' => 'ServiceKindCount', 'kind' => 'web', 'count' => 0],
                ['@id' => '/api/services/kinds/production', '@type' => 'ServiceKindCount', 'kind' => 'production', 'count' => 0],
            ],
        ]);
    }

    private function service(ServiceKind $kind, string $username): Service
    {
        return ServiceFactory::new()->ofKind($kind)->create(['user' => UserFactory::new()->create(['username' => $username])]);
    }
}
