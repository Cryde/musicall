<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist;

use App\Enum\BandSpace\SetlistItemType;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingGotenbergClient;
use Sensiolabs\GotenbergBundle\Exception\ClientException;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\SetlistFactory;
use App\Tests\Factory\BandSpace\SetlistItemFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use App\Enum\BandSpace\SetlistPdfFont;
use App\Enum\BandSpace\SetlistPdfLayout;
use App\Repository\BandSpace\SetlistRepository;
use App\Service\Setlist\SetlistPdfOptions;
use App\Service\Setlist\SetlistPdfRenderer;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
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
        $this->assertSame(
            "attachment; filename=Live-2026.pdf; filename*=utf-8''Live%202026.pdf",
            (string) $response->headers->get('Content-Disposition'),
        );

        $body = (string) $response->getContent();
        $this->assertNotEmpty($body);
        $this->assertStringStartsWith('%PDF-', $body, 'Response body must be a valid PDF binary');
    }

    public function test_pdf_export_with_accented_name_succeeds(): void
    {
        // Regression (#731): makeDisposition() threw on a non-ASCII fallback
        // filename, so any setlist named with accents (é, è, à, ...) - i.e. most
        // French setlists - returned HTTP 500 instead of the rendered PDF.
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Répétition générale'])->create();
        SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'Intro', 'position' => 0])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/pdf');

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
        // Header stays pure ASCII: an ASCII slug fallback plus the real accented
        // name carried through the RFC 5987 filename* parameter.
        $this->assertSame(
            "attachment; filename=Repetition-generale.pdf; filename*=utf-8''R%C3%A9p%C3%A9tition%20g%C3%A9n%C3%A9rale.pdf",
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    public function test_pdf_export_with_slash_in_name_succeeds(): void
    {
        // Regression (#731): makeDisposition() also rejects "/" and "\" in the
        // filename, so a setlist named e.g. "Rock/Metal" previously returned 500.
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Rock/Metal'])->create();
        SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'Intro', 'position' => 0])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/pdf');

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
        // "/" is replaced by "-"; display and fallback collapse to the same ASCII
        // token, so no filename* is emitted.
        $this->assertSame(
            'attachment; filename=Rock-Metal.pdf',
            (string) $response->headers->get('Content-Disposition'),
        );
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
        // The compact template really was the one rendered, which the bytes alone cannot show.
        $this->assertStringContainsString(
            'stage-items',
            self::getContainer()->get(RecordingGotenbergClient::class)->sentHtml(),
        );
    }

    public function test_renderer_forwards_the_display_toggles_into_the_document(): void
    {
        // Tested via the renderer service directly (not the HTTP endpoint)
        // so we can render the same setlist twice in the same test without
        // hitting Symfony's one-request loginUser limitation.
        //
        // This used to compare the two PDFs by byte length, which was only ever a proxy for "the
        // toggles reached the document". Asserting on the HTML that actually went to Gotenberg says
        // it directly, and covers the one thing the template-only tests below cannot: that the
        // renderer forwards its options into what it uploads at all.
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

        $gotenberg = self::getContainer()->get(RecordingGotenbergClient::class);
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
        $minimalHtml = $gotenberg->sentHtml();

        $rich = $renderer->render($setlistEntity, new SetlistPdfOptions(
            layout: SetlistPdfLayout::Large,
            showTempo: true,
            showKey: true,
            showDurations: true,
            showNotes: true,
            showTransitions: true,
        ), $totalDuration);
        $richHtml = $gotenberg->sentHtml();

        $this->assertStringStartsWith('%PDF-', $minimal);
        $this->assertStringStartsWith('%PDF-', $rich);

        $this->assertStringNotContainsString('BPM', $minimalHtml);
        // The markup, not the class name: the .sub-line rules sit in the stylesheet either way.
        $this->assertStringNotContainsString('<span class="sub-line">', $minimalHtml);
        $this->assertStringNotContainsString('rehearse the bridge', $minimalHtml);

        $this->assertStringContainsString('BPM', $richHtml);
        $this->assertStringContainsString('<span class="sub-line">', $richHtml);
        $this->assertStringContainsString('rehearse the bridge', $richHtml);
        $this->assertStringContainsString('segue into next', $richHtml);

        // Two renders, two requests: neither asked to fit, so neither paid for a measurement pass.
        $this->assertCount(2, $gotenberg->calls());
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

    public function test_a_long_set_is_rendered_full_size_in_a_single_request(): void
    {
        // Fifty items cannot fit one page and nobody asked them to, so the render must go out at
        // full size in one request: no measurement pass, no scale. That the renderer does not
        // secretly shrink an unrequested export is ours to check; how Chromium then paginates it
        // is not, and that half now lives in SetlistPdfGotenbergRenderTest.
        //
        // The item count is scenario colour rather than load bearing: what is asserted below holds
        // for any number of items, since the trigger is the absent fit flag and not the length.
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Long set'])->create();
        for ($i = 0; $i < 50; $i++) {
            SetlistItemFactory::new([
                'setlist' => $setlist,
                'type' => SetlistItemType::Talk,
                'label' => 'Item ' . $i,
                'position' => $i,
            ])->create();
        }

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/pdf');

        $this->assertResponseIsSuccessful();
        $this->assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());

        $gotenberg = self::getContainer()->get(RecordingGotenbergClient::class);
        $this->assertCount(1, $gotenberg->calls());
        $this->assertArrayNotHasKey('scale', $gotenberg->lastCall()['fields']);
        $this->assertArrayNotHasKey('singlePage', $gotenberg->lastCall()['fields']);
    }

    public function test_pdf_export_invalid_font_falls_back_without_error(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'Hi', 'position' => 0])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/pdf?font=not-a-font');

        $this->assertResponseIsSuccessful();
        $this->assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());
        // The fallback is now observable: an unusable font name lands on the layout's default, and
        // that is the family whose files get uploaded.
        $this->assertSame(
            ['Inter-Regular.ttf', 'Inter-Bold.ttf'],
            array_keys(self::getContainer()->get(RecordingGotenbergClient::class)->lastCall()['assets']),
        );
    }

    public function test_fit_to_one_page_measures_the_content_then_scales_it(): void
    {
        // The fit path is two requests: ask Chromium how tall the document really is, then render it
        // scaled by exactly enough. Both halves are asserted here, because each has a detail that
        // fails silently if it drifts - a measurement taken at the wrong width, or a scale applied
        // to a document that is still in measure mode.
        $setlist = $this->createCompactSetlistWithSongs(15);
        $gotenberg = self::getContainer()->get(RecordingGotenbergClient::class);
        // Comfortably taller than an A4 text area, so a scale is genuinely required.
        $gotenberg->withContentHeightPt(1223.04);

        $renderer = self::getContainer()->get(SetlistPdfRenderer::class);
        $entity = self::getContainer()->get(SetlistRepository::class)->find((string) $setlist->id);

        $renderer->render($entity, new SetlistPdfOptions(layout: SetlistPdfLayout::Compact, fitToOnePage: true), 0, 0);

        $calls = $gotenberg->calls();
        $this->assertCount(2, $calls, 'A fit export measures once and renders once');

        // The measurement: one page as tall as the content, no margins eating into the number, and
        // the body pinned to the printable width. Unpinned, Chromium lays out at its own narrower
        // screen viewport and under-reports the height by about a fifth.
        $this->assertSame('true', $calls[0]['fields']['singlePage']);
        $this->assertSame('0mm', $calls[0]['fields']['marginTop']);
        $this->assertSame('0mm', $calls[0]['fields']['marginBottom']);
        $this->assertStringContainsString('width: 182mm', $calls[0]['documents']['index.html']);
        $this->assertArrayNotHasKey('scale', $calls[0]['fields']);

        // The render: real margins, scaled, and no longer in measure mode.
        $this->assertSame('18mm', $calls[1]['fields']['marginTop']);
        $this->assertSame('14mm', $calls[1]['fields']['marginBottom']);
        $this->assertArrayNotHasKey('singlePage', $calls[1]['fields']);
        $this->assertStringNotContainsString('width: 182mm', $calls[1]['documents']['index.html']);
        // 265mm of text area over 1223.04pt of content, less the 2% safety factor.
        $this->assertEqualsWithDelta(0.6019, (float) $calls[1]['fields']['scale'], 0.0005);
    }

    public function test_the_fit_scale_is_proportional_and_floored(): void
    {
        // The three branches of the arithmetic, driven by what the measurement pass reports. The
        // text area is 265mm, so 751.18pt: anything shorter needs no scaling, anything taller is
        // scaled in proportion, and something absurdly tall stops at the legibility floor and
        // accepts a second page rather than becoming unreadable.
        $cases = [
            'already fits, so no scale at all' => [400.0, null],
            'taller than the page, scaled in proportion' => [1223.04, 0.6019],
            'far too tall, so the floor takes over' => [4000.0, 0.42],
        ];

        $setlist = $this->createCompactSetlistWithSongs(10);
        $gotenberg = self::getContainer()->get(RecordingGotenbergClient::class);
        $renderer = self::getContainer()->get(SetlistPdfRenderer::class);
        $entity = self::getContainer()->get(SetlistRepository::class)->find((string) $setlist->id);

        foreach ($cases as $description => [$contentHeightPt, $expectedScale]) {
            $gotenberg->withContentHeightPt($contentHeightPt);
            $callsBefore = \count($gotenberg->calls());

            $renderer->render($entity, new SetlistPdfOptions(layout: SetlistPdfLayout::Compact, fitToOnePage: true), 0, 0);

            // Measurement, then render: the render is the second of the pair.
            $renderCall = $gotenberg->calls()[$callsBefore + 1];

            if ($expectedScale === null) {
                $this->assertArrayNotHasKey('scale', $renderCall['fields'], $description);

                continue;
            }

            $this->assertEqualsWithDelta($expectedScale, (float) $renderCall['fields']['scale'], 0.0005, $description);
        }
    }

    public function test_fit_to_one_page_is_ignored_above_the_item_cap(): void
    {
        // Beyond 15 items a single page would be illegible, so the flag is ignored (mirrors the
        // frontend cap and blocks a crafted URL forcing an unreadable fit). Asserting on the single
        // request is stronger than the old page count: it also proves the measurement is skipped,
        // so an ignored fit costs nothing.
        $setlist = $this->createCompactSetlistWithSongs(20);
        $gotenberg = self::getContainer()->get(RecordingGotenbergClient::class);
        $renderer = self::getContainer()->get(SetlistPdfRenderer::class);
        $entity = self::getContainer()->get(SetlistRepository::class)->find((string) $setlist->id);

        $renderer->render($entity, new SetlistPdfOptions(layout: SetlistPdfLayout::Compact, fitToOnePage: true), 0, 0);

        $this->assertCount(1, $gotenberg->calls());
        $this->assertArrayNotHasKey('scale', $gotenberg->lastCall()['fields']);
        $this->assertArrayNotHasKey('singlePage', $gotenberg->lastCall()['fields']);
    }

    public function test_pdf_export_returns_502_when_gotenberg_is_unreachable(): void
    {
        // New failure mode. dompdf ran in process and could not fail this way; a render is now a
        // network call, and a dependency being down is a 502, not the 500 an uncaught transport
        // error would produce.
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Panne'])->create();
        SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'Intro', 'position' => 0])->create();

        self::getContainer()->get(RecordingGotenbergClient::class)->failWith(
            new ClientException('Could not resolve host: gotenberg.musicall'),
        );

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/pdf');

        // The status is the contract; the detail is deliberately not. API Platform replaces the
        // message on any 5xx outside debug mode (ErrorProvider::provide) so an internal failure
        // cannot leak, which is why the French wording the renderer attaches only reaches the logs
        // and why the user-facing copy lives in the frontend toast instead.
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_GATEWAY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/502',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Internal Server Error',
            'status' => 502,
            'type' => '/errors/502',
            'description' => 'Internal Server Error',
        ]);
    }

    private function createCompactSetlistWithSongs(int $count): object
    {
        $bandSpace = BandSpaceFactory::new()->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Fit test'])->create();
        for ($i = 0; $i < $count; $i++) {
            $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Song ' . $i])->create();
            SetlistItemFactory::new([
                'setlist' => $setlist,
                'type' => SetlistItemType::Song,
                'song' => $song,
                'label' => null,
                'position' => $i,
            ])->create();
        }

        return $setlist;
    }

    public function test_pdf_export_with_atkinson_font_succeeds(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'Hi', 'position' => 0])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/' . $bandSpace->id . '/setlists/' . $setlist->id . '/pdf?font=atkinson_hyperlegible');

        $this->assertResponseIsSuccessful();
        $this->assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());
        // Two files, the chosen family only. dompdf had to register all three on every render.
        $this->assertSame(
            ['AtkinsonHyperlegible-Regular.ttf', 'AtkinsonHyperlegible-Bold.ttf'],
            array_keys(self::getContainer()->get(RecordingGotenbergClient::class)->lastCall()['assets']),
        );
    }

    public function test_large_template_renders_notes_and_transitions_as_sub_line(): void
    {
        // Twig is rendered directly here because a PDF's content streams are compressed, so the
        // binary does not grep cleanly for arbitrary text whatever produced it.
        $bandSpace = BandSpaceFactory::new()->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace, 'title' => 'Wonderwall'])->create();
        SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Song,
            'song' => $song,
            'note' => 'skip the second solo',
            'transition' => 'segue',
            'position' => 0,
        ])->create();

        $entity = self::getContainer()->get(SetlistRepository::class)->find((string) $setlist->id);
        $html = self::getContainer()->get(Environment::class)->render('pdf/setlist/setlist_large.html.twig', [
            'setlist' => $entity,
            'options' => new SetlistPdfOptions(
                layout: SetlistPdfLayout::Large,
                showNotes: true,
                showTransitions: true,
            ),
            'total_duration_seconds' => 0,
            'missing_duration_items' => 0,
            'font' => SetlistPdfFont::Inter,
        ]);

        $this->assertStringContainsString('sub-line', $html);
        $this->assertStringContainsString('skip the second solo', $html);
        $this->assertStringContainsString('segue', $html);
        $this->assertStringContainsString('↳', $html);
    }

    public function test_large_template_renders_dash_placeholder_for_empty_cells(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        $song = SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Untyped',
            'tempo' => null,
            'tonality' => null,
            'referenceDuration' => null,
        ])->create();
        SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Song,
            'song' => $song,
            'durationOverride' => null,
            'position' => 0,
        ])->create();

        $entity = self::getContainer()->get(SetlistRepository::class)->find((string) $setlist->id);
        $html = self::getContainer()->get(Environment::class)->render('pdf/setlist/setlist_large.html.twig', [
            'setlist' => $entity,
            'options' => new SetlistPdfOptions(
                layout: SetlistPdfLayout::Large,
                showTempo: true,
                showKey: true,
                showDurations: true,
            ),
            'total_duration_seconds' => 0,
            'missing_duration_items' => 0,
            'font' => SetlistPdfFont::Inter,
        ]);

        // Three muted cells, one per missing field. The dash itself is U+2014;
        // assert it appears multiple times to confirm placeholders are emitted
        // for tonality, BPM and duration each.
        $dashCount = substr_count($html, '—');
        $this->assertGreaterThanOrEqual(3, $dashCount, 'A song with no tonality/tempo/duration must produce at least 3 — placeholders');
        $this->assertStringContainsString('muted', $html, 'Empty-cell placeholders should carry the muted class');
    }

    public function test_large_template_renders_missing_duration_notice(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace])->create();
        $song = SongFactory::new(['bandSpace' => $bandSpace, 'referenceDuration' => 180])->create();
        SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Song,
            'song' => $song,
            'durationOverride' => null,
            'position' => 0,
        ])->create();
        SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Talk,
            'label' => 'No duration',
            'durationOverride' => null,
            'position' => 1,
        ])->create();

        $entity = self::getContainer()->get(SetlistRepository::class)->find((string) $setlist->id);
        $html = self::getContainer()->get(Environment::class)->render('pdf/setlist/setlist_large.html.twig', [
            'setlist' => $entity,
            'options' => new SetlistPdfOptions(layout: SetlistPdfLayout::Large, showDurations: true),
            'total_duration_seconds' => 180,
            'missing_duration_items' => 1,
            'font' => SetlistPdfFont::Inter,
        ]);

        $this->assertStringContainsString('1 titre sans durée', $html);
        $this->assertStringContainsString('3 min 0 s', $html);
        // Total row in the table footer
        $this->assertStringContainsString('total-row', $html);
        $this->assertStringContainsString('Total', $html);
    }

    public function test_compact_template_omits_per_field_data_even_if_options_say_otherwise(): void
    {
        // Even when the template receives showNotes/showTransitions=true, Compact
        // is a stage sheet - it ignores those toggles by design (no <span class="sub-line">,
        // no tonality, no BPM, no duration). The provider also forces toggles off
        // (covered by SetlistPdfOptionsBuilderTest), so this is the layer-2 defence.
        $bandSpace = BandSpaceFactory::new()->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Stage sheet'])->create();
        $song = SongFactory::new([
            'bandSpace' => $bandSpace,
            'title' => 'Wonderwall',
            'tempo' => 87,
            'tonality' => 'Em',
            'referenceDuration' => 258,
        ])->create();
        SetlistItemFactory::new([
            'setlist' => $setlist,
            'type' => SetlistItemType::Song,
            'song' => $song,
            'note' => 'SHOULD_NOT_APPEAR',
            'transition' => 'NOT_VISIBLE',
            'position' => 0,
        ])->create();

        $entity = self::getContainer()->get(SetlistRepository::class)->find((string) $setlist->id);
        $html = self::getContainer()->get(Environment::class)->render('pdf/setlist/setlist_compact.html.twig', [
            'setlist' => $entity,
            'options' => new SetlistPdfOptions(layout: SetlistPdfLayout::Compact),
            'total_duration_seconds' => 258,
            'missing_duration_items' => 0,
            'font' => SetlistPdfFont::AtkinsonHyperlegible,
        ]);

        $this->assertStringContainsString('Wonderwall', $html);
        $this->assertStringContainsString('Stage sheet', $html);
        $this->assertStringContainsString($bandSpace->name, $html);
        $this->assertStringNotContainsString('SHOULD_NOT_APPEAR', $html);
        $this->assertStringNotContainsString('NOT_VISIBLE', $html);
        $this->assertStringNotContainsString('87', $html, 'Compact must not show BPM');
        $this->assertStringNotContainsString('Em', $html, 'Compact must not show tonality');
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
