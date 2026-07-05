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

    public function test_pdf_export_multi_page_emits_multiple_pages(): void
    {
        // Many items force dompdf to span 2+ pages. Verified via /Type/Page
        // count - testing the literal "Page 1 / 2" text via strpos is unreliable
        // because dompdf compresses content streams by default. The canonical
        // bug ("Page 1 / 0") was a multi-page rendering issue, so what matters
        // is that the resulting PDF actually has multiple pages.
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
        $body = (string) $this->client->getResponse()->getContent();
        $pageCount = preg_match_all('#/Type\s*/Page[^s]#', $body);
        $this->assertGreaterThan(1, $pageCount, '50-item setlist must span more than one page');
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
    }

    public function test_pdf_export_actually_embeds_selected_font(): void
    {
        // Regression: dompdf silently falls back to Helvetica if registerFont() fails
        // (e.g. font dir not in chroot). Each font must produce a PDF that actually
        // references its own family name in the embedded /BaseFont stream. Calls
        // the renderer service directly to avoid loginUser's one-request limit
        // when iterating over the three fonts.
        $bandSpace = BandSpaceFactory::new()->create();
        $setlist = SetlistFactory::new(['bandSpace' => $bandSpace, 'name' => 'Font check'])->create();
        SetlistItemFactory::new(['setlist' => $setlist, 'type' => SetlistItemType::Talk, 'label' => 'Hello', 'position' => 0])->create();

        $renderer = self::getContainer()->get(SetlistPdfRenderer::class);
        $entity = self::getContainer()->get(SetlistRepository::class)->find((string) $setlist->id);

        $fonts = [
            [SetlistPdfFont::Inter, 'Inter'],
            [SetlistPdfFont::AtkinsonHyperlegible, 'AtkinsonHyperlegible'],
            [SetlistPdfFont::SourceSerif, 'SourceSerif'],
        ];

        $sizes = [];
        foreach ($fonts as [$font, $expectedFontMarker]) {
            $pdf = $renderer->render($entity, new SetlistPdfOptions(layout: SetlistPdfLayout::Large, font: $font), 0, 0);
            $this->assertStringStartsWith('%PDF-', $pdf);
            // dompdf writes the font's PostScript name into /BaseFont entries,
            // typically uppercase-prefixed with a 6-char subset tag like "ABCDEF+Inter-Regular".
            $this->assertMatchesRegularExpression(
                '/\/BaseFont\s*\/[A-Z]{6}\+' . preg_quote($expectedFontMarker, '/') . '/',
                $pdf,
                "PDF rendered with font={$font->value} must embed $expectedFontMarker, not silently fall back to Helvetica/DejaVu",
            );
            $sizes[$font->value] = strlen($pdf);
        }

        // Sanity: the three PDFs should not be identical-size by coincidence.
        $this->assertNotSame($sizes['inter'], $sizes['source_serif'], 'Inter and Source Serif must produce visibly different PDFs');
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
    }

    public function test_large_template_renders_notes_and_transitions_as_sub_line(): void
    {
        // Twig is rendered directly here to bypass dompdf compression - the PDF
        // binary doesn't grep cleanly for arbitrary text.
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
            'font_family' => 'Inter',
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
            'font_family' => 'Inter',
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
            'font_family' => 'Inter',
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
            'font_family' => 'Atkinson Hyperlegible',
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
