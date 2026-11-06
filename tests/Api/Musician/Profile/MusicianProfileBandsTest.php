<?php

declare(strict_types=1);

namespace App\Tests\Api\Musician\Profile;

use App\Entity\Musician\MusicianProfile;
use App\Entity\User;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\Musician\MusicianProfileFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** The bands a member plays in, on the musician profile (#1313). */
#[ResetDatabase]
class MusicianProfileBandsTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_create_with_bands(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $rock = StyleFactory::new()->asRock()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/musician-profile', [
            'instruments' => [],
            'style_ids' => [],
            'bands' => [
                ['name' => '  Ardent  ', 'style_id' => $rock->id, 'role' => ' Guitare, choeurs ', 'current' => true, 'url' => 'https://ardent.bandcamp.com'],
                ['name' => 'Les Anciens', 'style_id' => '00000000-0000-0000-0000-000000000000', 'role' => '', 'current' => false, 'url' => ''],
            ],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $this->profileOf($user)->id,
            'instruments' => [],
            'style_ids' => [],
            'styles' => [],
            'bands' => [
                [
                    '@type' => 'MusicianProfileBand',
                    'name' => 'Ardent',
                    'style_id' => $rock->id,
                    'style_name' => 'Rock',
                    'role' => 'Guitare, choeurs',
                    'current' => true,
                    'url' => 'https://ardent.bandcamp.com',
                ],
                [
                    '@type' => 'MusicianProfileBand',
                    'name' => 'Les Anciens',
                    'current' => false,
                ],
            ],
        ]);
        $this->assertSame([
            ['name' => 'Ardent', 'style_id' => $rock->id, 'role' => 'Guitare, choeurs', 'current' => true, 'url' => 'https://ardent.bandcamp.com'],
            ['name' => 'Les Anciens', 'style_id' => null, 'role' => null, 'current' => false, 'url' => null],
        ], $this->profileOf($user)->bands);
    }

    public function test_an_update_that_leaves_them_out_keeps_them(): void
    {
        [$user, $rock] = $this->musicianWithBands();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'availability_status' => 'looking_for_band',
            'instruments' => [],
            'style_ids' => [],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $this->profileOf($user)->id,
            'availability_status' => 'looking_for_band',
            'availability_status_label' => 'Cherche un groupe',
            'instruments' => [],
            'styles' => [],
            'bands' => [
                [
                    '@type' => 'MusicianProfileBand',
                    'name' => 'Ardent',
                    'style_id' => $rock->id,
                    'style_name' => 'Rock',
                    'role' => 'Guitare',
                    'current' => true,
                    'url' => 'https://ardent.bandcamp.com',
                ],
                [
                    '@type' => 'MusicianProfileBand',
                    'name' => 'Les Anciens',
                    'current' => false,
                ],
            ],
        ]);
    }

    public function test_an_update_replaces_them(): void
    {
        [$user] = $this->musicianWithBands();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'instruments' => [],
            'style_ids' => [],
            'bands' => [
                ['name' => 'Lueur', 'current' => true],
            ],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $this->profileOf($user)->id,
            'instruments' => [],
            'styles' => [],
            'bands' => [
                [
                    '@type' => 'MusicianProfileBand',
                    'name' => 'Lueur',
                    'current' => true,
                ],
            ],
        ]);
    }

    public function test_an_empty_list_clears_them(): void
    {
        [$user] = $this->musicianWithBands();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'instruments' => [],
            'style_ids' => [],
            'bands' => [],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MusicianProfileEdit',
            '@id' => '/api/user/musician-profile',
            '@type' => 'MusicianProfileEdit',
            'id' => $this->profileOf($user)->id,
            'instruments' => [],
            'styles' => [],
            'bands' => [],
        ]);
    }

    public function test_the_bands_are_bounded(): void
    {
        [$user] = $this->musicianWithBands();

        $this->client->loginUser($user);
        $this->client->jsonRequest('PATCH', '/api/user/musician-profile', [
            'instruments' => [],
            'style_ids' => [],
            'bands' => [
                ['name' => '   ', 'current' => true],
                ['name' => str_repeat('a', 81), 'role' => str_repeat('b', 81), 'current' => true],
                ['name' => 'Ardent', 'current' => true, 'url' => 'javascript:alert(1)'],
                ['name' => 'Lueur', 'current' => true, 'url' => 'https://' . str_repeat('c', 500) . '.com'],
                ['name' => 'Un', 'current' => true],
                ['name' => 'Deux', 'current' => false],
            ],
        ], ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=756b1212-697c-468d-a9ad-50dd783bb169;1=c1051bb4-d103-4f74-8988-acbcafc7fdc3;2=d94b19cc-114f-4f44-9cc4-4138e80a87b9;3=d94b19cc-114f-4f44-9cc4-4138e80a87b9;4=57c2f299-1154-4870-89bb-ef3b1f5ad229;5=d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'bands',
                    'message' => 'Au plus 5 groupes',
                    'code' => '756b1212-697c-468d-a9ad-50dd783bb169',
                ],
                [
                    'propertyPath' => 'bands[0].name',
                    'message' => 'Le nom du groupe est obligatoire',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
                [
                    'propertyPath' => 'bands[1].name',
                    'message' => 'Le nom du groupe fait au plus 80 caractères',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
                [
                    'propertyPath' => 'bands[1].role',
                    'message' => 'Le rôle fait au plus 80 caractères',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
                [
                    'propertyPath' => 'bands[2].url',
                    'message' => 'Le lien n\'est pas valide',
                    'code' => '57c2f299-1154-4870-89bb-ef3b1f5ad229',
                ],
                [
                    'propertyPath' => 'bands[3].url',
                    'message' => 'Le lien fait au plus 500 caractères',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
            ],
            'detail' => "bands: Au plus 5 groupes\nbands[0].name: Le nom du groupe est obligatoire\nbands[1].name: Le nom du groupe fait au plus 80 caractères\nbands[1].role: Le rôle fait au plus 80 caractères\nbands[2].url: Le lien n'est pas valide\nbands[3].url: Le lien fait au plus 500 caractères",
            'type' => '/validation_errors/0=756b1212-697c-468d-a9ad-50dd783bb169;1=c1051bb4-d103-4f74-8988-acbcafc7fdc3;2=d94b19cc-114f-4f44-9cc4-4138e80a87b9;3=d94b19cc-114f-4f44-9cc4-4138e80a87b9;4=57c2f299-1154-4870-89bb-ef3b1f5ad229;5=d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            'title' => 'An error occurred',
            'description' => "bands: Au plus 5 groupes\nbands[0].name: Le nom du groupe est obligatoire\nbands[1].name: Le nom du groupe fait au plus 80 caractères\nbands[1].role: Le rôle fait au plus 80 caractères\nbands[2].url: Le lien n'est pas valide\nbands[3].url: Le lien fait au plus 500 caractères",
        ]);
    }

    public function test_the_public_musician_page_shows_them(): void
    {
        [$user, $rock] = $this->musicianWithBands();

        $this->client->request('GET', '/api/user/profile/' . $user->username . '/musician');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicMusicianProfile',
            '@id' => '/api/user/profile/' . $user->username . '/musician',
            '@type' => 'PublicMusicianProfile',
            'username' => $user->username,
            'user_id' => $user->id,
            'instruments' => [],
            'styles' => [],
            'bands' => [
                [
                    '@type' => 'MusicianProfileBand',
                    'name' => 'Ardent',
                    'style_id' => $rock->id,
                    'style_name' => 'Rock',
                    'role' => 'Guitare',
                    'current' => true,
                    'url' => 'https://ardent.bandcamp.com',
                ],
                [
                    '@type' => 'MusicianProfileBand',
                    'name' => 'Les Anciens',
                    'current' => false,
                ],
            ],
            'musician_announces' => [],
            'media' => [],
            'creation_datetime' => '2024-02-20T14:30:00+00:00',
        ]);
    }

    /**
     * @return array{0: User, 1: \App\Entity\Attribute\Style}
     */
    private function musicianWithBands(): array
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'band_member']);
        $rock = StyleFactory::new()->asRock()->create();
        $profile = MusicianProfileFactory::new()->create([
            'user' => $user,
            'styles' => [],
            'availabilityStatus' => null,
            'creationDatetime' => new \DateTimeImmutable('2024-02-20T14:30:00+00:00'),
            'bands' => [
                ['name' => 'Ardent', 'style_id' => $rock->id, 'role' => 'Guitare', 'current' => true, 'url' => 'https://ardent.bandcamp.com'],
                // A style removed since the band was saved.
                ['name' => 'Les Anciens', 'style_id' => '0190f0a1-0000-7000-8000-000000000000', 'role' => null, 'current' => false, 'url' => null],
            ],
        ]);
        $user->musicianProfile = $profile;
        \Zenstruck\Foundry\Persistence\save($user);

        return [$user, $rock];
    }

    private function profileOf(User $user): MusicianProfile
    {
        $profile = self::getContainer()->get('doctrine')->getRepository(MusicianProfile::class)->findOneBy(['user' => $user->id]);
        $this->assertInstanceOf(MusicianProfile::class, $profile);

        return $profile;
    }
}
