<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist\Lyrics;

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
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class LyricsGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_the_list_names_each_text_and_its_song_without_the_text(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $retired = SongFactory::new()->create([
            'bandSpace' => $space,
            'title' => 'Vieux tube',
            'tonality' => 'Em',
            'archiveDatetime' => new DateTimeImmutable('2026-09-01 08:00:00'),
        ]);
        $created = new DateTime('2026-10-01 10:00:00');
        $draft = LyricsFactory::createOne(['bandSpace' => $space, 'title' => 'Brouillon', 'content' => null, 'creationDatetime' => $created]);
        $linked = LyricsFactory::createOne(['bandSpace' => $space, 'song' => $retired, 'title' => 'Au clair', 'content' => '[C]La', 'creationDatetime' => $created]);
        LyricsFactory::createOne(['bandSpace' => $space, 'title' => 'À la corbeille', 'archiveDatetime' => new DateTimeImmutable()]);
        LyricsFactory::createOne(['title' => 'Ailleurs']);

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/lyrics', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/LyricsListItem',
            '@id' => '/api/band_spaces/' . $space->id . '/lyrics',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/lyrics_list_items/id=' . $linked->id . ';bandSpaceId=' . $space->id,
                    '@type' => 'LyricsListItem',
                    'id' => $linked->id,
                    'band_space_id' => $space->id,
                    'title' => 'Au clair',
                    'has_content' => true,
                    'song' => [
                        '@type' => 'LyricsSongInfo',
                        'id' => $retired->id,
                        'title' => 'Vieux tube',
                        'tonality' => 'Em',
                        'archive_datetime' => '2026-09-01T08:00:00+00:00',
                    ],
                    'archive_datetime' => null,
                    'creation_datetime' => $created->format(DateTimeInterface::ATOM),
                    'update_datetime' => null,
                ],
                [
                    '@id' => '/api/lyrics_list_items/id=' . $draft->id . ';bandSpaceId=' . $space->id,
                    '@type' => 'LyricsListItem',
                    'id' => $draft->id,
                    'band_space_id' => $space->id,
                    'title' => 'Brouillon',
                    'has_content' => false,
                    'song' => null,
                    'archive_datetime' => null,
                    'creation_datetime' => $created->format(DateTimeInterface::ATOM),
                    'update_datetime' => null,
                ],
            ],
        ]);
    }

    public function test_archived_true_lists_the_corbeille(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        LyricsFactory::createOne(['bandSpace' => $space, 'title' => 'Vivantes']);
        $created = new DateTime('2026-10-01 10:00:00');
        $archived = new DateTimeImmutable('2026-10-20 18:30:00');
        $binned = LyricsFactory::createOne([
            'bandSpace' => $space,
            'title' => 'Jetées',
            'content' => 'La la',
            'creationDatetime' => $created,
            'archiveDatetime' => $archived,
        ]);

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/lyrics?archived=true', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/LyricsListItem',
            '@id' => '/api/band_spaces/' . $space->id . '/lyrics',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/lyrics_list_items/id=' . $binned->id . ';bandSpaceId=' . $space->id,
                    '@type' => 'LyricsListItem',
                    'id' => $binned->id,
                    'band_space_id' => $space->id,
                    'title' => 'Jetées',
                    'has_content' => true,
                    'song' => null,
                    'archive_datetime' => '2026-10-20T18:30:00+00:00',
                    'creation_datetime' => $created->format(DateTimeInterface::ATOM),
                    'update_datetime' => null,
                ],
            ],
            'view' => [
                '@id' => '/api/band_spaces/' . $space->id . '/lyrics?archived=true',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_a_non_member_cannot_list_the_lyrics(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();

        $this->client->loginUser($stranger);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/lyrics', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Tu n\'es pas membre de ce Band Space',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Tu n\'es pas membre de ce Band Space',
        ]);
    }
}
