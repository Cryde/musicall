<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist\Lyrics;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Lyrics;
use App\Entity\User;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingGotenbergClient;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\LyricsFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Lyrics as a PDF (#1262). The HTML sent to Gotenberg is what is asserted: the PDF bytes come from a stub.
 */
#[ResetDatabase]
class LyricsPdfExportTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $lyrics = LyricsFactory::createOne(['content' => 'Secret']);

        $this->client->request('GET', $this->url($lyrics->bandSpace, $lyrics));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_a_non_member_cannot_export(): void
    {
        $outsider = UserFactory::new()->asBaseUser()->create(['username' => 'intrus', 'email' => 'intrus@test.com']);
        $lyrics = LyricsFactory::createOne(['content' => 'Secret']);

        $this->client->loginUser($outsider);
        $this->client->request('GET', $this->url($lyrics->bandSpace, $lyrics), server: ['HTTP_ACCEPT' => 'application/ld+json']);

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

    public function test_lyrics_of_another_space_are_not_found(): void
    {
        [$space, $singer] = $this->lyricsSungBy();
        $elsewhere = LyricsFactory::createOne(['content' => 'Secret']);

        $this->client->loginUser($singer);
        $this->client->request('GET', $this->url($space, $elsewhere), server: ['HTTP_ACCEPT' => 'application/ld+json']);

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
    }

    public function test_unlinked_lyrics_print_under_their_own_title_with_no_key(): void
    {
        [$space, $singer, $lyrics] = $this->lyricsSungBy();

        $this->client->loginUser($singer);
        $this->client->request('GET', $this->url($space, $lyrics));

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame(
            "attachment; filename=Nouvelle-chanson.pdf; filename*=utf-8''Nouvelle%20chanson.pdf",
            (string) $response->headers->get('Content-Disposition'),
        );

        $html = self::getContainer()->get(RecordingGotenbergClient::class)->sentHtml();
        $this->assertStringContainsString('<div class="song-sheet-title">Nouvelle chanson</div>', $html);
        $this->assertStringNotContainsString('Tonalité', $html);
        $this->assertStringNotContainsString('BPM', $html);
        $this->assertStringContainsString('<span class="song-chord">C</span>', $html);
        $this->assertStringContainsString('<span class="song-chord">G</span>', $html);
        $this->assertStringContainsString('<div class="song-section-label">Refrain</div>', $html);
        $this->assertStringContainsString('<span class="singer-tag" style="background: #4f46e5">chanteuse</span>', $html);
        $this->assertStringContainsString('<span style="background: #4f46e533;">de la</span>', $html);
    }

    public function test_linked_lyrics_print_as_their_song(): void
    {
        [$space, $singer, $lyrics] = $this->lyricsSungBy(linked: true);

        $this->client->loginUser($singer);
        $this->client->request('GET', $this->url($space, $lyrics));

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            "attachment; filename=Au-clair-de-la-lune.pdf; filename*=utf-8''Au%20clair%20de%20la%20lune.pdf",
            (string) $this->client->getResponse()->headers->get('Content-Disposition'),
        );

        $html = self::getContainer()->get(RecordingGotenbergClient::class)->sentHtml();
        $this->assertStringContainsString('<div class="song-sheet-title">Au clair de la lune</div>', $html);
        $this->assertStringContainsString('Tonalité : C', $html);
        $this->assertStringContainsString('96 BPM', $html);
        $this->assertStringContainsString('<span class="singer-tag" style="background: #4f46e5">chanteuse</span>', $html);
    }

    public function test_the_chords_and_singers_can_be_left_out(): void
    {
        [$space, $singer, $lyrics] = $this->lyricsSungBy();

        $this->client->loginUser($singer);
        $this->client->request('GET', $this->url($space, $lyrics) . '?chords=0&singers=false');

        $this->assertResponseIsSuccessful();
        $html = self::getContainer()->get(RecordingGotenbergClient::class)->sentHtml();
        $this->assertStringNotContainsString('class="song-chord"', $html);
        $this->assertStringNotContainsString('class="singer-tag"', $html);
        $this->assertStringContainsString('de la', $html);
    }

    public function test_unlinked_lyrics_transpose_their_chords_only(): void
    {
        [$space, $singer, $lyrics] = $this->lyricsSungBy();

        $this->client->loginUser($singer);
        $this->client->request('GET', $this->url($space, $lyrics) . '?transpose=-2');

        $this->assertResponseIsSuccessful();
        $html = self::getContainer()->get(RecordingGotenbergClient::class)->sentHtml();
        $this->assertStringContainsString('<span class="song-chord">Bb</span>', $html);
        $this->assertStringContainsString('<span class="song-chord">F</span>', $html);
        $this->assertStringNotContainsString('Tonalité', $html);
    }

    public function test_linked_lyrics_transpose_the_song_key_too(): void
    {
        [$space, $singer, $lyrics] = $this->lyricsSungBy(linked: true);

        $this->client->loginUser($singer);
        $this->client->request('GET', $this->url($space, $lyrics) . '?transpose=-2');

        $this->assertResponseIsSuccessful();
        $html = self::getContainer()->get(RecordingGotenbergClient::class)->sentHtml();
        $this->assertStringContainsString('<span class="song-chord">Bb</span>', $html);
        $this->assertStringContainsString('Tonalité : Bb', $html);
        $this->assertStringContainsString('96 BPM', $html);
    }

    /** The Corbeille is read only, not hidden: a member can still print what they archived. */
    public function test_archived_lyrics_still_download(): void
    {
        [$space, $singer, $lyrics] = $this->lyricsSungBy(['archiveDatetime' => new DateTimeImmutable('2026-10-01 10:00:00')]);

        $this->client->loginUser($singer);
        $this->client->request('GET', $this->url($space, $lyrics));

        $this->assertResponseIsSuccessful();
        $html = self::getContainer()->get(RecordingGotenbergClient::class)->sentHtml();
        $this->assertStringContainsString('<div class="song-sheet-title">Nouvelle chanson</div>', $html);
    }

    public function test_empty_lyrics_print_their_title_alone(): void
    {
        [$space, $singer, $lyrics] = $this->lyricsSungBy(['content' => null]);

        $this->client->loginUser($singer);
        $this->client->request('GET', $this->url($space, $lyrics) . '?transpose=3');

        $this->assertResponseIsSuccessful();
        $html = self::getContainer()->get(RecordingGotenbergClient::class)->sentHtml();
        $this->assertStringContainsString('<div class="song-sheet-title">Nouvelle chanson</div>', $html);
        $this->assertStringNotContainsString('class="song-chord"', $html);
    }

    public function test_a_transposition_out_of_range_is_refused(): void
    {
        [$space, $singer, $lyrics] = $this->lyricsSungBy();

        $this->client->loginUser($singer);
        $this->client->request('GET', $this->url($space, $lyrics) . '?transpose=12', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'transpose',
                    'message' => 'La transposition doit être entre -11 et 11 demi-tons',
                    'code' => '04b91c99-a946-4221-afc5-e65ebac401eb',
                ],
            ],
            'detail' => 'transpose: La transposition doit être entre -11 et 11 demi-tons',
            'type' => '/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            'title' => 'An error occurred',
            'description' => 'transpose: La transposition doit être entre -11 et 11 demi-tons',
        ]);
    }

    public function test_an_invalid_option_is_refused(): void
    {
        [$space, $singer, $lyrics] = $this->lyricsSungBy();

        $this->client->loginUser($singer);
        $this->client->request('GET', $this->url($space, $lyrics) . '?chords=oui', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'chords',
                    'message' => 'Valeur invalide',
                    'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7',
                ],
            ],
            'detail' => 'chords: Valeur invalide',
            'type' => '/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            'title' => 'An error occurred',
            'description' => 'chords: Valeur invalide',
        ]);
    }

    public function test_an_invalid_transposition_is_refused(): void
    {
        [$space, $singer, $lyrics] = $this->lyricsSungBy();

        $this->client->loginUser($singer);
        $this->client->request('GET', $this->url($space, $lyrics) . '?transpose=haut', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'transpose',
                    'message' => 'La transposition doit être un nombre de demi-tons',
                    'code' => 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
                ],
            ],
            'detail' => 'transpose: La transposition doit être un nombre de demi-tons',
            'type' => '/validation_errors/de1e3db3-5ed4-4941-aae4-59f3667cc3a3',
            'title' => 'An error occurred',
            'description' => 'transpose: La transposition doit être un nombre de demi-tons',
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array{BandSpace, User, Lyrics}
     */
    private function lyricsSungBy(array $attributes = [], bool $linked = false): array
    {
        $singer = UserFactory::new()->asBaseUser()->create(['username' => 'chanteuse', 'email' => 'chanteuse@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $singer])->create();
        $lyrics = LyricsFactory::createOne([
            'bandSpace' => $space,
            'title' => 'Nouvelle chanson',
            'content' => "{soc}\n[C]Au clair <span singer=\"@[{$singer->id}]\">de la</span> [G]<span singer=\"all\">lune</span>\n{eoc}",
            'song' => $linked ? SongFactory::createOne(['bandSpace' => $space, 'title' => 'Au clair de la lune', 'tonality' => 'C', 'tempo' => 96]) : null,
            ...$attributes,
        ]);

        return [$space, $singer, $lyrics];
    }

    private function url(BandSpace $space, Lyrics $lyrics): string
    {
        return '/api/band_spaces/' . $space->id . '/lyrics/' . $lyrics->id . '/pdf';
    }
}
