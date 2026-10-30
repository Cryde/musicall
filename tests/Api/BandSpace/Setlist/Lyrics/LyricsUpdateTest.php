<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist\Lyrics;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceActivity;
use App\Entity\BandSpace\Lyrics;
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
class LyricsUpdateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_member_saves_new_content(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $created = new DateTime('2026-10-01 10:00:00');
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'title' => 'Brouillon', 'content' => 'La', 'version' => 2, 'creationDatetime' => $created]);

        $this->client->loginUser($member);
        $this->patch($space, $lyrics, ['content' => '[D]La la', 'expected_version' => 2]);

        $this->assertResponseIsSuccessful();
        $stored = $this->stored($lyrics);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Lyrics',
            '@id' => '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id,
            '@type' => 'Lyrics',
            'id' => $lyrics->id,
            'band_space_id' => $space->id,
            'title' => 'Brouillon',
            'content' => '[D]La la',
            'version' => 3,
            'has_content' => true,
            'song' => null,
            'singers' => [],
            'archive_datetime' => null,
            'creation_datetime' => $created->format(DateTimeInterface::ATOM),
            'update_datetime' => $stored->updateDatetime?->format(DateTimeInterface::ATOM),
        ]);
        $this->assertNotNull($stored->updateDatetime);
        $this->assertSame([['lyrics_updated', ['title' => 'Brouillon']]], $this->activities($space, $lyrics));
    }

    /** A rename cannot wipe anyone's text, so it is not held to the revision and does not move it. */
    public function test_a_rename_needs_no_version(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $created = new DateTime('2026-10-01 10:00:00');
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'title' => 'Brouillon', 'content' => 'La', 'version' => 4, 'creationDatetime' => $created]);

        $this->client->loginUser($member);
        $this->patch($space, $lyrics, ['title' => 'Le vrai titre']);

        $this->assertResponseIsSuccessful();
        $stored = $this->stored($lyrics);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Lyrics',
            '@id' => '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id,
            '@type' => 'Lyrics',
            'id' => $lyrics->id,
            'band_space_id' => $space->id,
            'title' => 'Le vrai titre',
            'content' => 'La',
            'version' => 4,
            'has_content' => true,
            'song' => null,
            'singers' => [],
            'archive_datetime' => null,
            'creation_datetime' => $created->format(DateTimeInterface::ATOM),
            'update_datetime' => $stored->updateDatetime?->format(DateTimeInterface::ATOM),
        ]);
        $this->assertSame(['Le vrai titre', 4], [$stored->title, $stored->version]);
    }

    public function test_content_without_a_version_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'content' => 'La']);

        $this->client->loginUser($member);
        $this->patch($space, $lyrics, ['content' => 'Écrasé']);

        $this->assertResponseStatusCodeSame(Response::HTTP_PRECONDITION_REQUIRED);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/428',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Indique la version des paroles sur laquelle tu as travaillé pour les enregistrer.',
            'status' => 428,
            'type' => '/errors/428',
            'description' => 'Indique la version des paroles sur laquelle tu as travaillé pour les enregistrer.',
        ]);
        $this->assertSame('La', $this->stored($lyrics)->content);
    }

    public function test_a_save_from_a_stale_copy_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'content' => 'Leur version', 'version' => 5]);

        $this->client->loginUser($member);
        $this->patch($space, $lyrics, ['content' => 'Ma version', 'expected_version' => 4]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ces paroles ont été modifiées par un autre membre depuis que tu les as ouvertes. Tes modifications n\'ont pas été enregistrées afin de ne pas effacer les siennes.',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Ces paroles ont été modifiées par un autre membre depuis que tu les as ouvertes. Tes modifications n\'ont pas été enregistrées afin de ne pas effacer les siennes.',
        ]);
        $this->assertSame(['Leur version', 5], [$this->stored($lyrics)->content, $this->stored($lyrics)->version]);
    }

    public function test_lyrics_in_the_corbeille_are_read_only(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'title' => 'Jetées', 'archiveDatetime' => new DateTimeImmutable()]);

        $this->client->loginUser($member);
        $this->patch($space, $lyrics, ['title' => 'Repêchées']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ces paroles sont dans la corbeille, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Ces paroles sont dans la corbeille, les modifications sont désactivées',
        ]);
        $this->assertSame('Jetées', $this->stored($lyrics)->title);
    }

    public function test_lyrics_on_an_archived_song_are_read_only(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'archiveDatetime' => new DateTimeImmutable()]);
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'song' => $song, 'content' => 'La']);

        $this->client->loginUser($member);
        $this->patch($space, $lyrics, ['content' => 'Autre', 'expected_version' => 1]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cette chanson est archivée, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
            'description' => 'Cette chanson est archivée, les modifications sont désactivées',
        ]);
    }

    public function test_a_space_pending_deletion_is_read_only(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create(['deletionScheduledDatetime' => new DateTimeImmutable('+30 days')]);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'content' => 'La']);

        $this->client->loginUser($member);
        $this->patch($space, $lyrics, ['content' => 'Autre', 'expected_version' => 1]);

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

    public function test_a_singer_outside_the_band_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $stranger = UserFactory::new()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'content' => 'La']);

        $this->client->loginUser($member);
        $this->patch($space, $lyrics, ['content' => "<span singer=\"@[{$stranger->id}]\">la</span>", 'expected_version' => 1]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_5c1f0e2a-8b7d-4f3e-9a6c-2d4b8e1f7a90',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Un chanteur attribué n\'est pas membre de ce Band Space',
                    'code' => 'music_all_5c1f0e2a-8b7d-4f3e-9a6c-2d4b8e1f7a90',
                ],
            ],
            'detail' => 'content: Un chanteur attribué n\'est pas membre de ce Band Space',
            'type' => '/validation_errors/music_all_5c1f0e2a-8b7d-4f3e-9a6c-2d4b8e1f7a90',
            'title' => 'An error occurred',
            'description' => 'content: Un chanteur attribué n\'est pas membre de ce Band Space',
        ]);
    }

    /** The body cannot point the singer check at another space, where the stranger is a member. */
    public function test_the_body_cannot_choose_the_space_the_singers_are_checked_in(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $stranger = UserFactory::new()->create();
        $space = BandSpaceFactory::new()->create();
        $otherSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $otherSpace, 'user' => $stranger])->create();
        $lyrics = LyricsFactory::createOne(['bandSpace' => $space, 'content' => 'La']);

        $this->client->loginUser($member);
        $this->patch($space, $lyrics, [
            'content' => "<span singer=\"@[{$stranger->id}]\">la</span>",
            'expected_version' => 1,
            'band_space_id' => (string) $otherSpace->id,
            'id' => (string) $otherSpace->id,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_5c1f0e2a-8b7d-4f3e-9a6c-2d4b8e1f7a90',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Un chanteur attribué n\'est pas membre de ce Band Space',
                    'code' => 'music_all_5c1f0e2a-8b7d-4f3e-9a6c-2d4b8e1f7a90',
                ],
            ],
            'detail' => 'content: Un chanteur attribué n\'est pas membre de ce Band Space',
            'type' => '/validation_errors/music_all_5c1f0e2a-8b7d-4f3e-9a6c-2d4b8e1f7a90',
            'title' => 'An error occurred',
            'description' => 'content: Un chanteur attribué n\'est pas membre de ce Band Space',
        ]);
        $this->assertSame('La', $this->stored($lyrics)->content);
    }

    public function test_lyrics_of_another_space_are_not_found(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $elsewhere = LyricsFactory::createOne(['title' => 'Pas à nous']);

        $this->client->loginUser($member);
        $this->patch($space, $elsewhere, ['title' => 'Volées']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Paroles introuvables',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Paroles introuvables',
        ]);
        $this->assertSame('Pas à nous', $this->stored($elsewhere)->title);
    }

    /** @param array<string, mixed> $body */
    private function patch(BandSpace $space, Lyrics $lyrics, array $body): void
    {
        $this->client->jsonRequest('PATCH', '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id, $body, [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
    }

    private function stored(Lyrics $lyrics): Lyrics
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $stored = self::getContainer()->get(LyricsRepository::class)->find((string) $lyrics->id);
        $this->assertInstanceOf(Lyrics::class, $stored);

        return $stored;
    }

    /** @return list<array{string, mixed}> */
    private function activities(BandSpace $space, Lyrics $lyrics): array
    {
        return array_map(
            static fn (BandSpaceActivity $activity): array => [$activity->type, $activity->payload],
            self::getContainer()->get(BandSpaceActivityRepository::class)->findForResource($space, BandSpaceModule::Setlist, (string) $lyrics->id),
        );
    }
}
