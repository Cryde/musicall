<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\TechRider;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\TechRider;
use App\Enum\BandSpace\TechRiderItemType;
use App\Repository\BandSpace\TechRiderItemRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileVersionFactory;
use App\Tests\Factory\BandSpace\TechRiderFactory;
use App\Tests\Factory\BandSpace\TechRiderItemFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A document item is a page of the rider that the band authored elsewhere: the reference
 * rider's wiring diagram is exactly this. It points at a file in the band's own files area
 * rather than uploading a second copy, so folders, versions, tags and quota keep working and
 * there is only one copy to keep current.
 */
#[ResetDatabase]
class TechRiderDocumentItemTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array PATCH_HEADERS = [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    public function test_attaching_a_file_makes_it_the_page(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        $file = $this->renderableFile($bandSpace, 'schema-rack.png', 'image/png');

        $this->patch($user, $bandSpace, $rider, $item, ['file_id' => (string) $file->id]);

        $this->assertResponseIsSuccessful();

        // Full body, so a regression anywhere else in the item shape shows up here too. Note
        // there is no `file_id`: the write-side field never comes back, the resolved block is
        // the one answer.
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRiderItem',
            '@id' => $this->itemUrl($bandSpace, $rider, $item),
            '@type' => 'TechRiderItem',
            'id' => (string) $item->id,
            'band_space_id' => (string) $bandSpace->id,
            'rider_id' => (string) $rider->id,
            'type' => 'document',
            'is_included' => true,
            'title' => 'Schéma de câblage',
            'content' => null,
            'file' => [
                'id' => (string) $file->id,
                'original_name' => 'schema-rack.png',
                'mime_type' => 'image/png',
                'is_archived' => false,
                'download_url' => '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id . '/download',
            ],
            'position' => 0,
            'creation_datetime' => $item->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $item->updateDatetime?->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_a_pdf_is_accepted(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        $file = $this->renderableFile($bandSpace, 'patch.pdf', 'application/pdf');

        $this->patch($user, $bandSpace, $rider, $item, ['file_id' => (string) $file->id]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRiderItem',
            '@id' => $this->itemUrl($bandSpace, $rider, $item),
            '@type' => 'TechRiderItem',
            'id' => (string) $item->id,
            'band_space_id' => (string) $bandSpace->id,
            'rider_id' => (string) $rider->id,
            'type' => 'document',
            'is_included' => true,
            'title' => 'Schéma de câblage',
            'content' => null,
            'file' => [
                'id' => (string) $file->id,
                'original_name' => 'patch.pdf',
                'mime_type' => 'application/pdf',
                'is_archived' => false,
                'download_url' => '/api/band_spaces/' . $bandSpace->id . '/files/' . $file->id . '/download',
            ],
            'position' => 0,
            'creation_datetime' => $item->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $item->updateDatetime?->format(\DateTimeInterface::ATOM),
        ]);
    }

    public function test_clearing_the_file_empties_the_page(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        $file = $this->renderableFile($bandSpace, 'schema.png', 'image/png');
        $item->file = $file;
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->patch($user, $bandSpace, $rider, $item, ['file_id' => null]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRiderItem',
            '@id' => $this->itemUrl($bandSpace, $rider, $item),
            '@type' => 'TechRiderItem',
            'id' => (string) $item->id,
            'band_space_id' => (string) $bandSpace->id,
            'rider_id' => (string) $rider->id,
            'type' => 'document',
            'is_included' => true,
            'title' => 'Schéma de câblage',
            'content' => null,
            'file' => null,
            'position' => 0,
            'creation_datetime' => $item->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $item->updateDatetime?->format(\DateTimeInterface::ATOM),
        ]);
    }

    /**
     * The cross space check is what stops an id from another band being used to surface its
     * files through a rider.
     */
    public function test_a_file_from_another_band_space_is_rejected(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        $otherSpace = BandSpaceFactory::new()->create();
        $theirFile = $this->renderableFile($otherSpace, 'leur-schema.png', 'image/png');

        $this->patch($user, $bandSpace, $rider, $item, ['file_id' => (string) $theirFile->id]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Fichier introuvable dans cet espace',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Fichier introuvable dans cet espace',
        ]);

        $stored = self::getContainer()->get(TechRiderItemRepository::class)
            ->findOneByIdAndRider((string) $item->id, $rider);
        $this->assertNull($stored?->file);
    }

    /**
     * A page has to have a visual form. A zip or a text file would render as nothing, so it
     * is refused at the point of choosing rather than discovered at export time.
     */
    public function test_a_file_that_cannot_be_rendered_as_a_page_is_rejected(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        $file = $this->renderableFile($bandSpace, 'stems.zip', 'application/zip');

        $this->patch($user, $bandSpace, $rider, $item, ['file_id' => (string) $file->id]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ce type de fichier ne peut pas servir de page (images et PDF uniquement)',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Ce type de fichier ne peut pas servir de page (images et PDF uniquement)',
        ]);
    }

    /**
     * A file in the trash must be reported, not rendered as a blank page. Restoring the file
     * restores the page, which is why the reference is kept rather than cleared.
     */
    public function test_a_trashed_file_is_reported_as_archived(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        $file = $this->renderableFile($bandSpace, 'schema.png', 'image/png');
        $item->file = $file;
        $file->archiveDatetime = new DateTimeImmutable('2026-03-01T10:00:00+00:00');
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->client->loginUser($user);
        $this->client->request(
            'GET',
            '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id,
        );

        $this->assertResponseIsSuccessful();
        $riderBody = $this->getResponseAsArray();
        $documentItem = $riderBody['items'][0];

        $this->assertTrue($documentItem['file']['is_archived']);
        $this->assertSame('schema.png', $documentItem['file']['original_name']);
    }

    /**
     * SET NULL, not cascade: destroying the file must not destroy the page it was on. The
     * item survives, empty, and the interface asks for a new file.
     */
    public function test_deleting_the_file_leaves_the_item_standing(): void
    {
        [, $bandSpace, $rider, $item] = $this->seed();
        $file = $this->renderableFile($bandSpace, 'schema.png', 'image/png');

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $item->file = $file;
        $entityManager->flush();

        $entityManager->remove($file);
        $entityManager->flush();
        $entityManager->clear();

        $reloaded = self::getContainer()->get(TechRiderItemRepository::class)
            ->findOneByIdAndRider((string) $item->id, $rider);

        $this->assertInstanceOf(\App\Entity\BandSpace\TechRiderItem::class, $reloaded);
        $this->assertNull($reloaded->file);
        $this->assertSame('Schéma de câblage', $reloaded->title);
    }

    /**
     * @return array{0: \App\Entity\User, 1: BandSpace, 2: TechRider, 3: \App\Entity\BandSpace\TechRiderItem}
     */
    /**
     * A file reference is meaningless on any other type, so it is refused rather than stored:
     * an item holding both prose and a file is a state nothing can render, and later code
     * walking items by `file !== null` instead of by type would act on it.
     */
    public function test_a_text_item_cannot_be_given_a_file(): void
    {
        [$user, $bandSpace, $rider] = $this->seed();
        $textItem = TechRiderItemFactory::new([
            'techRider' => $rider,
            'title' => 'Sonorisation',
            'type' => TechRiderItemType::Text,
            'position' => 1,
        ])->create();
        $file = $this->renderableFile($bandSpace, 'schema.png', 'image/png');

        $this->patch($user, $bandSpace, $rider, $textItem, ['file_id' => (string) $file->id]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Seul un élément de type document peut référencer un fichier',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Seul un élément de type document peut référencer un fichier',
        ]);

        $stored = self::getContainer()->get(TechRiderItemRepository::class)
            ->findOneByIdAndRider((string) $textItem->id, $rider);
        $this->assertNull($stored?->file);
    }

    private function itemUrl(mixed $bandSpace, mixed $rider, mixed $item): string
    {
        return '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id . '/items/' . $item->id;
    }

    private function seed(): array
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Rider'])->create();
        $item = TechRiderItemFactory::new([
            'techRider' => $rider,
            'title' => 'Schéma de câblage',
            'type' => TechRiderItemType::Document,
        ])->create();

        return [$user, $bandSpace, $rider, $item];
    }

    private function renderableFile(BandSpace $bandSpace, string $name, string $mimeType): BandSpaceFile
    {
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'originalName' => $name])->create();
        $version = BandSpaceFileVersionFactory::new([
            'bandSpaceFile' => $file,
            'mimeType' => $mimeType,
            'versionNumber' => 1,
        ])->create();

        $file->currentVersion = $version;
        self::getContainer()->get('doctrine')->getManager()->flush();

        return $file;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function patch(mixed $user, mixed $bandSpace, mixed $rider, mixed $item, array $payload): void
    {
        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id . '/items/' . $item->id,
            $payload,
            self::PATCH_HEADERS,
        );
    }
}
