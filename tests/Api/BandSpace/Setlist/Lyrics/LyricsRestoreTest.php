<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist\Lyrics;

use App\Entity\BandSpace\BandSpaceActivity;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Repository\BandSpace\LyricsRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\LyricsFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class LyricsRestoreTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    /** Back unlinked: archiving took them off their song, which may have new lyrics by now. */
    public function test_restored_lyrics_come_back_unlinked(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $created = new DateTime('2026-10-01 10:00:00');
        $lyrics = LyricsFactory::createOne([
            'bandSpace' => $space,
            'title' => 'Repêchées',
            'content' => 'La',
            'creationDatetime' => $created,
            'archiveDatetime' => new DateTimeImmutable('2026-10-20 18:30:00'),
        ]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/restore', [], self::HEADERS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Lyrics',
            '@id' => '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id,
            '@type' => 'Lyrics',
            'id' => $lyrics->id,
            'band_space_id' => $space->id,
            'title' => 'Repêchées',
            'content' => 'La',
            'version' => 1,
            'has_content' => true,
            'song' => null,
            'singers' => [],
            'archive_datetime' => null,
            'creation_datetime' => $created->format(DateTimeInterface::ATOM),
            'update_datetime' => null,
        ]);
        $this->assertSame(
            [['lyrics_restored', ['title' => 'Repêchées']]],
            array_map(
                static fn (BandSpaceActivity $activity): array => [$activity->type, $activity->payload],
                self::getContainer()->get(BandSpaceActivityRepository::class)->findForResource($space, BandSpaceModule::Setlist, (string) $lyrics->id),
            ),
        );
    }

    /** The song got new lyrics while these were in the Corbeille: both stay, the new ones keep the song. */
    public function test_a_restore_never_takes_the_song_back(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->withLyrics('Les nouvelles')->create(['bandSpace' => $space]);
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'content' => 'Les anciennes', 'archiveDatetime' => new DateTimeImmutable()]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/restore', [], self::HEADERS);

        $this->assertResponseIsSuccessful();
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repository = self::getContainer()->get(LyricsRepository::class);
        $this->assertNull($repository->find((string) $lyrics->id)?->song);
        $this->assertSame('Les nouvelles', $repository->findOneBy(['song' => (string) $song->id])?->content);
    }

    public function test_a_space_pending_deletion_is_read_only(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create(['deletionScheduledDatetime' => new DateTimeImmutable('+30 days')]);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'archiveDatetime' => new DateTimeImmutable()]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/restore', [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
        ]);
    }

    public function test_lyrics_not_in_the_corbeille_cannot_be_restored(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space]);

        $this->client->loginUser($member);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/restore', [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ces paroles ne sont pas dans la corbeille',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Ces paroles ne sont pas dans la corbeille',
        ]);
    }
}
