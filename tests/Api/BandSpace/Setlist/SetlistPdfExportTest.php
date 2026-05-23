<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist;

use App\Enum\BandSpace\SetlistItemType;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\SetlistFactory;
use App\Tests\Factory\BandSpace\SetlistItemFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use App\Enum\BandSpace\SetlistPdfLayout;
use App\Repository\BandSpace\SetlistRepository;
use App\Service\Setlist\SetlistPdfOptions;
use App\Service\Setlist\SetlistPdfRenderer;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class SetlistPdfExportTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_pdf_export_happy_path(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Live 2026'])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Wonderwall', 'tempo' => 87, 'tonality' => 'Em', 'referenceDuration' => 258])->create();
        SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Song, 'song' => $song, 'label' => null, 'position' => 0])->create();
        SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'Band intro', 'position' => 1])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/pdf');

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'attachment',
            (string) $response->headers->get('Content-Disposition'),
        );
        $this->assertStringContainsString(
            'Live 2026.pdf',
            (string) $response->headers->get('Content-Disposition'),
        );

        $body = (string) $response->getContent();
        $this->assertNotEmpty($body);
        $this->assertStringStartsWith('%PDF-', $body, 'Response body must be a valid PDF binary');
    }

    public function test_pdf_export_compact_layout(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Compact set'])->create();
        SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'Hello', 'position' => 0])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/pdf?layout=compact');

        $this->assertResponseIsSuccessful();
        $body = (string) $this->client->getResponse()->getContent();
        $this->assertStringStartsWith('%PDF-', $body);
    }

    public function test_renderer_toggles_change_output(): void
    {
        // Tested via the renderer service directly (not the HTTP endpoint)
        // so we can render the same setlist twice in the same test without
        // hitting Symfony's one-request loginUser limitation.
        $bandSpace = BandSpaceFactory::new()->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Toggle set'])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'X', 'tempo' => 120, 'tonality' => 'C'])->create();
        SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Song,
            'song' => $song,
            'label' => null,
            'note' => 'rehearse the bridge',
            'transition' => 'segue into next',
            'position' => 0,
        ])->create();

        $renderer = self::getContainer()->get(SetlistPdfRenderer::class);
        $setlistRepository = self::getContainer()->get(SetlistRepository::class);
        $setlistEntity = $setlistRepository->find((string) $setlist->id);
        $totalDuration = $setlistRepository->totalDurationSeconds($setlistEntity);

        $minimal = $renderer->render($setlistEntity, new SetlistPdfOptions(
            layout: SetlistPdfLayout::Large,
            showTempo: false,
            showKey: false,
            showDurations: false,
            showNotes: false,
            showTransitions: false,
        ), $totalDuration);
        $rich = $renderer->render($setlistEntity, new SetlistPdfOptions(
            layout: SetlistPdfLayout::Large,
            showTempo: true,
            showKey: true,
            showDurations: true,
            showNotes: true,
            showTransitions: true,
        ), $totalDuration);

        $this->assertStringStartsWith('%PDF-', $minimal);
        $this->assertStringStartsWith('%PDF-', $rich);
        $this->assertGreaterThan(
            strlen($minimal),
            strlen($rich),
            'Enabling all display toggles must produce a larger PDF than disabling them all',
        );
    }

    public function test_pdf_export_works_on_archived_setlist(): void
    {
        // Matches the GET /setlists/{id} policy: archived setlists remain
        // readable for restore / audit / review flows.
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Archived live',
            'archiveDatetime' => new \DateTimeImmutable('2026-05-01T00:00:00+00:00'),
        ])->create();
        SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'Hello', 'position' => 0])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/pdf');

        $this->assertResponseIsSuccessful();
        $this->assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());
    }

    public function test_pdf_export_works_on_empty_setlist(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Empty set'])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/pdf');

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $body = (string) $response->getContent();
        $this->assertStringStartsWith('%PDF-', $body);
    }

    public function test_pdf_export_cross_band_returns_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $myBand = BandSpaceFactory::new()->create();
        $otherBand = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $myBand, 'user' => $user])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $otherBand, 'user' => $user])->create();

        $setlist = SetlistFactory::new(['bandSpace' => $otherBand])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $myBand->id . '/setlists/' . $setlist->id . '/pdf');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Setlist introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Setlist introuvable',
        ]);
    }

    public function test_pdf_export_not_member_returns_403(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'other_user', 'email' => 'other@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $owner])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($other);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/pdf');

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
    }
}
