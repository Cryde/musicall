<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\TechRider;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\TechRider;
use App\Enum\BandSpace\TechRiderColour;
use App\Enum\BandSpace\TechRiderItemType;
use App\Enum\BandSpace\TechRiderPatchDirection;
use App\Repository\BandSpace\TechRiderRepository;
use App\Service\BandSpace\TechRider\TechRiderPdfRenderer;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingGotenbergClient;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileVersionFactory;
use App\Tests\Factory\BandSpace\TechRiderFactory;
use App\Tests\Factory\BandSpace\TechRiderItemFactory;
use App\Tests\Factory\BandSpace\TechRiderPatchRowFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Sensiolabs\GotenbergBundle\Exception\ClientException;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The export, asserted on what it sends rather than on opaque PDF bytes.
 *
 * The segment pipeline is the part worth pinning here: a rider with no attachment must cost one
 * request, and a PDF attachment must split the document into three calls ending in a merge. Whether
 * the result is a readable document is a question only Chromium can answer, and that lives in
 * TechRiderPdfRenderTest.
 */
#[ResetDatabase]
class TechRiderPdfExportTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const string MERGE_ENDPOINT = '/forms/pdfengines/merge';
    private const string HTML_ENDPOINT = '/forms/chromium/convert/html';

    public function test_a_member_downloads_the_rider_as_a_pdf(): void
    {
        [$user, $bandSpace, $rider] = $this->seed();

        $this->client->loginUser($user);
        $this->client->request('GET', $this->url($bandSpace, $rider));

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame(
            "attachment; filename=Fiche-technique.pdf; filename*=utf-8''Fiche%20technique.pdf",
            (string) $response->headers->get('Content-Disposition'),
        );
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    /** #731 again, and worth its own case because a rider is named by a French band. */
    public function test_an_accented_rider_name_keeps_the_header_ascii(): void
    {
        [$user, $bandSpace, $rider] = $this->seed(name: 'Fiche générale');

        $this->client->loginUser($user);
        $this->client->request('GET', $this->url($bandSpace, $rider));

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            "attachment; filename=Fiche-generale.pdf; filename*=utf-8''Fiche%20g%C3%A9n%C3%A9rale.pdf",
            (string) $this->client->getResponse()->headers->get('Content-Disposition'),
        );
    }

    /** Matching GET /tech_riders/{id} and the setlist policy: last year's rider is a thing you send. */
    public function test_an_archived_rider_is_still_exportable(): void
    {
        [$user, $bandSpace, $rider] = $this->seed(archived: true);

        $this->client->loginUser($user);
        $this->client->request('GET', $this->url($bandSpace, $rider));

        $this->assertResponseIsSuccessful();
    }

    public function test_a_rider_of_another_band_is_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $mine = BandSpaceFactory::new()->create();
        $other = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $mine, 'user' => $user])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $other, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $other])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', $this->url($mine, $rider));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Tech rider introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Tech rider introuvable',
        ]);
    }

    public function test_a_non_member_is_refused(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', $this->url($bandSpace, $rider));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * New failure mode: the rider used to be data, and is now a call to a service. A dependency being
     * down is a 502, not the 500 an uncaught transport error would produce. The detail is deliberately
     * generic, because API Platform blanks the message on any 5xx outside debug.
     */
    public function test_an_unreachable_renderer_is_a_502(): void
    {
        [$user, $bandSpace, $rider] = $this->seed();
        self::getContainer()->get(RecordingGotenbergClient::class)->failWith(
            new ClientException('Could not resolve host: gotenberg.musicall'),
        );

        $this->client->loginUser($user);
        $this->client->request('GET', $this->url($bandSpace, $rider));

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

    // ------------------------------------------------------------------ the segment pipeline

    /** The ordinary rider. One request, no merge, and the whole document in a single render. */
    public function test_a_rider_without_a_pdf_attachment_costs_one_request(): void
    {
        [, $bandSpace, $rider] = $this->seed();
        $this->addTextItem($rider, 'Accueil', 0);

        $gotenberg = $this->render($bandSpace, $rider);

        $this->assertCount(1, $gotenberg->calls());
        $this->assertSame(self::HTML_ENDPOINT, $gotenberg->lastCall()['endpoint']);
    }

    /**
     * A PDF attachment cannot share a page with rider content, so the document splits around it: the
     * items before, the attachment, the items after, then one merge.
     */
    public function test_a_pdf_attachment_splits_the_document_and_merges(): void
    {
        [$user, $bandSpace, $rider] = $this->seed();
        $this->addTextItem($rider, 'Avant', 0);
        $this->addPdfAttachment($user, $bandSpace, $rider, position: 1);
        $this->addTextItem($rider, 'Après', 2);

        $gotenberg = $this->render($bandSpace, $rider);
        $calls = $gotenberg->calls();

        $this->assertCount(3, $calls, 'Two HTML segments and the merge');
        $this->assertSame(self::HTML_ENDPOINT, $calls[0]['endpoint']);
        $this->assertSame(self::HTML_ENDPOINT, $calls[1]['endpoint']);
        $this->assertSame(self::MERGE_ENDPOINT, $calls[2]['endpoint']);

        // The cover belongs to the first segment only, and the split really is around the attachment.
        $this->assertStringContainsString('Avant', $calls[0]['documents']['index.html']);
        $this->assertStringNotContainsString('Après', $calls[0]['documents']['index.html']);
        $this->assertStringContainsString('Après', $calls[1]['documents']['index.html']);
        $this->assertStringNotContainsString('Avant', $calls[1]['documents']['index.html']);

        /**
         * Gotenberg merges in alphabetical order of filename rather than in the order the files are
         * sent, so the ordinal prefix is the only thing keeping the composed order. Without it an
         * attachment called "Plan de salle.pdf" sorts ahead of the cover page, and the merge still
         * succeeds with the right page count, which is why this is asserted rather than assumed.
         */
        $merged = array_keys($calls[2]['assets']);
        $this->assertSame(['00.pdf', '01.pdf', '02.pdf'], $merged);
    }

    #[DataProvider('degradedAttachmentProvider')]
    public function test_an_unusable_attachment_is_named_rather_than_dropped(string $case, string $expected): void
    {
        [$user, $bandSpace, $rider] = $this->seed();
        $this->addPdfAttachment($user, $bandSpace, $rider, position: 0, degrade: $case);

        $gotenberg = $this->render($bandSpace, $rider);

        // No split and no merge: there is nothing to splice in.
        $this->assertCount(1, $gotenberg->calls());
        $this->assertStringContainsString($expected, $gotenberg->lastCall()['documents']['index.html']);
    }

    /**
     * A rider that silently drops an attachment is worse than one that admits it, so every refusal
     * prints the file's name and the reason instead of throwing.
     *
     * The expectations deliberately avoid apostrophes: this asserts on rendered markup, where Twig
     * has already turned every one of them into `&#039;`.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function degradedAttachmentProvider(): iterable
    {
        yield 'the file was trashed' => ['archived', 'supprimé de'];
        yield 'the version is gone' => ['no-version', 'Aucune version disponible'];
        yield 'the type is no longer usable' => ['bad-mime', 'ne peut pas être inclus'];
        yield 'it is larger than the cap' => ['huge', 'trop volumineux'];
        yield 'the object is missing from storage' => ['missing-bytes', 'introuvable dans le stockage'];
        yield 'it is not really a pdf' => ['not-a-pdf', 'pas un PDF valide'];
        yield 'it is password protected' => ['encrypted', 'protégé par un mot de passe'];
    }

    /**
     * Patch rows sort by direction **then** position. Position restarts per direction, so on its own
     * it is not a total order: sorted by position alone the two tables interleave, and the seeding
     * below is deliberately arranged so that bug would show.
     */
    public function test_patch_rows_are_grouped_by_direction_and_ordered_within_it(): void
    {
        [, $bandSpace, $rider] = $this->seed();
        $item = TechRiderItemFactory::new([
            'techRider' => $rider,
            'type' => TechRiderItemType::PatchList,
            'title' => 'Patch list',
            'position' => 0,
        ])->create();

        // Interleaved on purpose, and both directions restart at position 0.
        foreach ([
            [TechRiderPatchDirection::Output, 0, 'SORTIE UNE'],
            [TechRiderPatchDirection::Input, 1, 'ENTREE DEUX'],
            [TechRiderPatchDirection::Output, 1, 'SORTIE DEUX'],
            [TechRiderPatchDirection::Input, 0, 'ENTREE UNE'],
        ] as $index => [$direction, $position, $name]) {
            TechRiderPatchRowFactory::new([
                'item' => $item, 'direction' => $direction, 'channel' => $index + 1,
                'name' => $name, 'position' => $position, 'colour' => TechRiderColour::Red,
            ])->create();
        }

        $html = $this->render($bandSpace, $rider)->lastCall()['documents']['index.html'];

        // Every input before every output, and each in its own position order.
        $this->assertLessThan(strpos($html, 'ENTREE DEUX'), strpos($html, 'ENTREE UNE'));
        $this->assertLessThan(strpos($html, 'SORTIE UNE'), strpos($html, 'ENTREE DEUX'));
        $this->assertLessThan(strpos($html, 'SORTIE DEUX'), strpos($html, 'SORTIE UNE'));
        // The palette reaches the page as a swatch rather than as a colour name alone.
        $this->assertStringContainsString(TechRiderColour::Red->hex(), $html);
    }

    /**
     * The rider goes to a venue, so an address that was not opted in must not be on it. The flag is
     * stored per item and the roster is read live, which makes this the one place the two meet.
     */
    #[DataProvider('emailVisibilityProvider')]
    public function test_member_emails_appear_only_when_the_item_asks_for_them(bool $showEmails): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['email' => 'regie@musicall.test']);
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace, 'user' => $user, 'stageName' => 'Jérémy',
        ])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Fiche technique'])->create();

        TechRiderItemFactory::new([
            'techRider' => $rider,
            'type' => TechRiderItemType::Contacts,
            'title' => 'Contacts',
            'position' => 0,
            'content' => ['showEmails' => $showEmails, 'note' => ['type' => 'doc', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'NOTE DU GROUPE']]],
            ]]],
        ])->create();

        $html = $this->render($bandSpace, $rider)->lastCall()['documents']['index.html'];

        // The roster line and the note are there either way; only the address is gated.
        $this->assertStringContainsString('Jérémy', $html);
        $this->assertStringContainsString('NOTE DU GROUPE', $html);

        if ($showEmails) {
            $this->assertStringContainsString('regie@musicall.test', $html);
        } else {
            $this->assertStringNotContainsString('regie@musicall.test', $html);
        }
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function emailVisibilityProvider(): iterable
    {
        yield 'opted in' => [true];
        yield 'not opted in' => [false];
    }

    /** Reachable before a file is picked, and again after the referenced file is deleted (SET NULL). */
    public function test_a_document_item_with_no_file_says_so(): void
    {
        [, $bandSpace, $rider] = $this->seed();
        TechRiderItemFactory::new([
            'techRider' => $rider,
            'type' => TechRiderItemType::Document,
            'title' => 'Plan de salle',
            'position' => 0,
        ])->create();

        $gotenberg = $this->render($bandSpace, $rider);

        $this->assertCount(1, $gotenberg->calls());
        $this->assertStringContainsString(
            'Aucun fichier',
            $gotenberg->lastCall()['documents']['index.html'],
        );
    }

    /** The flag is honoured nowhere else on the server, so the export is the only thing enforcing it. */
    public function test_an_excluded_item_is_left_out(): void
    {
        [, $bandSpace, $rider] = $this->seed();
        $this->addTextItem($rider, 'Visible', 0);
        $this->addTextItem($rider, 'Cachée', 1, included: false);

        $gotenberg = $this->render($bandSpace, $rider);
        $html = $gotenberg->lastCall()['documents']['index.html'];

        $this->assertStringContainsString('Visible', $html);
        $this->assertStringNotContainsString('Cachée', $html);
    }

    /** The fetch join carries no ORDER BY, so the renderer sorting is the only thing ordering these. */
    public function test_items_are_composed_in_position_order(): void
    {
        [, $bandSpace, $rider] = $this->seed();
        $this->addTextItem($rider, 'Troisième', 2);
        $this->addTextItem($rider, 'Premier', 0);
        $this->addTextItem($rider, 'Deuxième', 1);

        $gotenberg = $this->render($bandSpace, $rider);
        $html = $gotenberg->lastCall()['documents']['index.html'];

        $this->assertLessThan(strpos($html, 'Deuxième'), strpos($html, 'Premier'));
        $this->assertLessThan(strpos($html, 'Troisième'), strpos($html, 'Deuxième'));
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @return array{\App\Entity\User, BandSpace, TechRider}
     */
    private function seed(string $name = 'Fiche technique', bool $archived = false): array
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => $name,
            'archiveDatetime' => $archived ? new \DateTimeImmutable('-1 day') : null,
        ])->create();

        return [$user, $bandSpace, $rider];
    }

    private function addTextItem(object $rider, string $title, int $position, bool $included = true): void
    {
        TechRiderItemFactory::new([
            'techRider' => $rider,
            'type' => TechRiderItemType::Text,
            'title' => $title,
            'position' => $position,
            'isIncluded' => $included,
            'content' => ['type' => 'doc', 'content' => []],
        ])->create();
    }

    private function addPdfAttachment(
        object $user,
        object $bandSpace,
        object $rider,
        int $position,
        ?string $degrade = null,
    ): void {
        $bytes = match ($degrade) {
            'not-a-pdf' => 'GIF89a this is not a pdf at all',
            'encrypted' => "%PDF-1.7\n1 0 obj\n<<>>\nendobj\ntrailer\n<< /Encrypt 9 0 R >>\n%%EOF\n",
            default => "%PDF-1.4\n1 0 obj\n<</Type /Page>>\nendobj\ntrailer\n<<>>\n%%EOF\n",
        };

        $storagePath = 'rider-' . bin2hex(random_bytes(4)) . '.pdf';
        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'originalName' => 'Plan de salle.pdf',
            'archiveDatetime' => $degrade === 'archived' ? new \DateTimeImmutable('-1 day') : null,
        ])->create();

        if ($degrade !== 'no-version') {
            $version = BandSpaceFileVersionFactory::new([
                'bandSpaceFile' => $file,
                'versionNumber' => 1,
                'createdBy' => $user,
                'mimeType' => $degrade === 'bad-mime' ? 'audio/mpeg' : 'application/pdf',
                'size' => $degrade === 'huge' ? 400 * 1024 * 1024 : strlen($bytes),
                'storagePath' => $storagePath,
            ])->create();
            $file->currentVersion = $version;
        }

        self::getContainer()->get(EntityManagerInterface::class)->flush();

        if ($degrade !== 'missing-bytes' && $degrade !== 'no-version') {
            self::getContainer()->get('oneup_flysystem.musicall_filesystem')
                ->write('/band_space_files/' . $bandSpace->id . '/' . $storagePath, $bytes);
        }

        TechRiderItemFactory::new([
            'techRider' => $rider,
            'type' => TechRiderItemType::Document,
            'title' => 'Plan de salle',
            'position' => $position,
            'file' => $file,
        ])->create();
    }

    /** Renders through the service so a rider can be exercised without loginUser's one-request life. */
    private function render(object $bandSpace, object $rider): RecordingGotenbergClient
    {
        $gotenberg = self::getContainer()->get(RecordingGotenbergClient::class);
        $entity = self::getContainer()->get(TechRiderRepository::class)
            ->findOneByIdAndBandSpace((string) $rider->id, $bandSpace);

        self::assertInstanceOf(TechRider::class, $entity);
        self::getContainer()->get(TechRiderPdfRenderer::class)->render($entity);

        return $gotenberg;
    }

    private function url(object $bandSpace, object $rider): string
    {
        return sprintf('/api/band_spaces/%s/tech_riders/%s/pdf', $bandSpace->id, $rider->id);
    }
}
