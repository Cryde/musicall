<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Setlist\Song;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\Song;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingGotenbergClient;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A song's lyrics as a PDF (#1055). The HTML sent to Gotenberg is what is asserted: the PDF bytes
 * come from a stub.
 */
#[ResetDatabase]
class SongPdfExportTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $song = SongFactory::new()->create();

        $this->client->request('GET', '/api/band_spaces/' . $song->bandSpace->id . '/songs/' . $song->id . '/pdf');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_a_non_member_cannot_export(): void
    {
        $outsider = UserFactory::new()->asBaseUser()->create(['username' => 'intrus', 'email' => 'intrus@test.com']);
        $song = SongFactory::new()->create(['lyrics' => 'Secret']);

        $this->client->loginUser($outsider);
        $this->client->request('GET', '/api/band_spaces/' . $song->bandSpace->id . '/songs/' . $song->id . '/pdf', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous n\'êtes pas membre de ce Band Space',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous n\'êtes pas membre de ce Band Space',
        ]);
    }

    public function test_the_sheet_carries_the_chords_and_the_singers_by_default(): void
    {
        [$space, $singer, $song] = $this->songSungBy('chanteuse');

        $this->client->loginUser($singer);
        $this->client->request('GET', $this->url($space, $song));

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame(
            "attachment; filename=Au-clair-de-la-lune.pdf; filename*=utf-8''Au%20clair%20de%20la%20lune.pdf",
            (string) $response->headers->get('Content-Disposition'),
        );

        $html = self::getContainer()->get(RecordingGotenbergClient::class)->sentHtml();
        $this->assertStringContainsString('<span class="song-chord">C</span>', $html);
        $this->assertStringContainsString('<span class="song-chord">G</span>', $html);
        $this->assertStringContainsString('Tonalité : C', $html);
        $this->assertStringContainsString('<div class="song-section-label">Refrain</div>', $html);
        $this->assertStringContainsString('<span class="singer-tag" style="background: #4f46e5">chanteuse</span>', $html);
        $this->assertStringContainsString('<span style="background: #4f46e533;">de la</span>', $html);
        $this->assertStringContainsString('<span style="background: #64748b33;">lune</span>', $html);
    }

    public function test_the_chords_and_singers_can_be_left_out(): void
    {
        [$space, $singer, $song] = $this->songSungBy('chanteuse');

        $this->client->loginUser($singer);
        $this->client->request('GET', $this->url($space, $song) . '?chords=0&singers=false');

        $this->assertResponseIsSuccessful();
        $html = self::getContainer()->get(RecordingGotenbergClient::class)->sentHtml();
        $this->assertStringNotContainsString('class="song-chord"', $html);
        $this->assertStringNotContainsString('class="singer-tag"', $html);
        $this->assertStringContainsString('de la', $html);
    }

    public function test_the_print_can_be_transposed(): void
    {
        [$space, $singer, $song] = $this->songSungBy('chanteuse');

        $this->client->loginUser($singer);
        $this->client->request('GET', $this->url($space, $song) . '?transpose=-2');

        $this->assertResponseIsSuccessful();
        $html = self::getContainer()->get(RecordingGotenbergClient::class)->sentHtml();
        $this->assertStringContainsString('<span class="song-chord">Bb</span>', $html);
        $this->assertStringContainsString('<span class="song-chord">F</span>', $html);
        $this->assertStringContainsString('Tonalité : Bb', $html);
    }

    /** A singer who left the band is still named on the songs they sang. */
    public function test_a_former_member_keeps_their_name(): void
    {
        [$space, $member, $song] = $this->songSungBy('ancienne', MembershipStatus::Left);
        $reader = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $reader])->create();

        $this->client->loginUser($reader);
        $this->client->request('GET', $this->url($space, $song));

        $this->assertResponseIsSuccessful();
        $html = self::getContainer()->get(RecordingGotenbergClient::class)->sentHtml();
        $this->assertStringContainsString('ancienne</span> (ancien membre)', $html);
    }

    public function test_an_invalid_transposition_is_refused(): void
    {
        [$space, $singer, $song] = $this->songSungBy('chanteuse');

        $this->client->loginUser($singer);
        $this->client->request('GET', $this->url($space, $song) . '?transpose=haut', server: ['HTTP_ACCEPT' => 'application/ld+json']);

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
     * @return array{BandSpace, User, Song}
     */
    private function songSungBy(string $username, MembershipStatus $status = MembershipStatus::Active): array
    {
        $singer = UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $singer, 'status' => $status])->create();
        $song = SongFactory::new()->create([
            'bandSpace' => $space,
            'title' => 'Au clair de la lune',
            'tonality' => 'C',
            'lyrics' => "{soc}\n[C]Au clair <span singer=\"@[{$singer->id}]\">de la</span> [G]<span singer=\"all\">lune</span>\n{eoc}",
        ]);

        return [$space, $singer, $song];
    }

    private function url(BandSpace $space, Song $song): string
    {
        return '/api/band_spaces/' . $space->id . '/songs/' . $song->id . '/pdf';
    }
}
