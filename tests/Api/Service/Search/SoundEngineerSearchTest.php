<?php

declare(strict_types=1);

namespace App\Tests\Api\Service\Search;

use App\Entity\Attribute\Style;
use App\Entity\Service\Service;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServiceStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\Service\ServiceMediaFactory;
use App\Tests\Factory\Service\ServiceOfferFactory;
use App\Tests\Factory\Service\ServicePlaceFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Uuid;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** The sound engineer search (#1410). */
#[ResetDatabase]
class SoundEngineerSearchTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array BRUSSELS = ['lat' => '50.8503', 'lng' => '4.3517'];
    // 2.2 km from Brussels.
    private const array IXELLES = ['city' => 'Ixelles', 'latitude' => '50.8333', 'longitude' => '4.3667'];
    // 89.7 km.
    private const array LIEGE = ['city' => 'Liège', 'latitude' => '50.6326', 'longitude' => '5.5797'];

    public function test_a_published_sound_engineer_is_found_with_what_their_card_shows(): void
    {
        $rock = StyleFactory::new()->asRock()->create();
        $julien = $this->engineer('julien', [
            'headline' => 'Ta démo mixée comme un disque',
            'specialities' => ['mixing', 'mastering'],
            'turnaroundDays' => 5,
        ], styles: [$rock]);
        $julien->user->profile->displayName = 'Julien Roche';
        ServicePlaceFactory::new()->atMyPlace(self::IXELLES)->create(['service' => $julien]);
        ServicePlaceFactory::new()->remote()->create(['service' => $julien]);
        // Cheaper, but for a whole day: the card names the price of a track.
        ServiceOfferFactory::new()->create(['service' => $julien, 'unit' => OfferUnit::Day, 'quantity' => null, 'price' => 6000, 'title' => 'Une journée']);
        ServiceOfferFactory::new()->create(['service' => $julien, 'unit' => OfferUnit::Track, 'quantity' => null, 'price' => 8000, 'title' => "Mixage d'un titre"]);
        ServiceMediaFactory::new()->create(['service' => $julien, 'position' => 0, 'embedId' => 'first', 'title' => 'First']);
        $this->flush();

        $this->client->request('GET', '/api/services/sound-engineering');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SoundEngineerSearchResult',
            '@id' => '/api/services/sound-engineering',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/sound_engineer_search_results/julien',
                    '@type' => 'SoundEngineerSearchResult',
                    'username' => 'julien',
                    'name' => 'Julien Roche',
                    'headline' => 'Ta démo mixée comme un disque',
                    'specialities' => ['mixing', 'mastering'],
                    'online' => true,
                    'price' => 8000,
                    'price_unit' => 'track',
                    'turnaround_days' => 5,
                    'styles' => [['@type' => 'TeacherProfileStyle', 'id' => $rock->id, 'name' => 'Rock']],
                    'media' => ['@type' => 'TeacherSearchMedia', 'platform' => 'youtube', 'embed_id' => 'first', 'title' => 'First'],
                ],
            ],
        ]);
    }

    /** Without a price per track, the card names the cheapest offer of any unit, and says which. */
    public function test_without_a_track_price_the_cheapest_offer_is_shown(): void
    {
        $service = $this->engineer('live');
        ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Flat, 'quantity' => null, 'price' => 30000, 'title' => 'Un concert']);
        ServiceOfferFactory::new()->create(['service' => $service, 'unit' => OfferUnit::Hour, 'quantity' => null, 'price' => 4000, 'title' => 'Une heure']);
        $this->flush();

        $this->client->request('GET', '/api/services/sound-engineering');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SoundEngineerSearchResult',
            '@id' => '/api/services/sound-engineering',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->card('live', ['price' => 4000, 'price_unit' => 'hour'])],
        ]);
    }

    public function test_drafts_private_profiles_and_other_kinds_are_never_found(): void
    {
        $this->engineer('shown');
        $this->engineer('draft')->status = ServiceStatus::Draft;
        $this->engineer('private')->user->profile->isPublic = false;
        ServiceFactory::new()->teaching()->create(['user' => UserFactory::new()->create(['username' => 'teacher'])]);
        $this->flush();

        $this->client->request('GET', '/api/services/sound-engineering');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SoundEngineerSearchResult',
            '@id' => '/api/services/sound-engineering',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->card('shown')],
        ]);
    }

    public function test_a_speciality_keeps_its_engineers_and_comes_first_on_their_card(): void
    {
        $this->engineer('mixer', ['specialities' => ['mixing']]);
        $this->engineer('both', ['specialities' => ['mixing', 'mastering']], created: '2024-01-02');
        $this->engineer('live', ['specialities' => ['live_sound']]);

        $this->client->request('GET', '/api/services/sound-engineering', ['speciality' => 'mastering']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SoundEngineerSearchResult',
            '@id' => '/api/services/sound-engineering',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->card('both', ['specialities' => ['mastering', 'mixing']])],
            'view' => ['@id' => '/api/services/sound-engineering?speciality=mastering', '@type' => 'PartialCollectionView'],
        ]);
    }

    public function test_a_style_keeps_its_engineers(): void
    {
        $rock = StyleFactory::new()->asRock()->create();
        $jazz = StyleFactory::new()->asJazz()->create();
        $this->engineer('rocker', styles: [$rock]);
        $this->engineer('jazzman', styles: [$jazz]);

        $this->client->request('GET', '/api/services/sound-engineering', ['style' => $jazz->id]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SoundEngineerSearchResult',
            '@id' => '/api/services/sound-engineering',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->card('jazzman', ['styles' => [['@type' => 'TeacherProfileStyle', 'id' => $jazz->id, 'name' => 'Jazz']]])],
            'view' => ['@id' => '/api/services/sound-engineering?style=' . $jazz->id, '@type' => 'PartialCollectionView'],
        ]);
    }

    public function test_a_radius_keeps_the_places_in_reach_and_online_adds_remote_work(): void
    {
        ServicePlaceFactory::new()->atMyPlace(self::IXELLES)->create(['service' => $this->engineer('ixelles')]);
        $liegeRemote = $this->engineer('liege_and_remote');
        ServicePlaceFactory::new()->atMyPlace(self::LIEGE)->create(['service' => $liegeRemote]);
        ServicePlaceFactory::new()->remote()->create(['service' => $liegeRemote]);
        ServicePlaceFactory::new()->atMyPlace(self::LIEGE)->create(['service' => $this->engineer('liege')]);

        $this->client->request('GET', '/api/services/sound-engineering', ['radius' => '10', 'online' => '1'] + self::BRUSSELS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SoundEngineerSearchResult',
            '@id' => '/api/services/sound-engineering',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                $this->card('ixelles', ['distance' => 2.2]),
                // Found remotely, so no distance.
                $this->card('liege_and_remote', ['online' => true]),
            ],
            'view' => ['@id' => '/api/services/sound-engineering?lat=50.8503&lng=4.3517&online=1&radius=10', '@type' => 'PartialCollectionView'],
        ]);
    }

    public function test_the_second_page_holds_the_engineers_after_the_first_twelve(): void
    {
        for ($day = 1; $day <= 13; ++$day) {
            $this->engineer(sprintf('engineer_%02d', $day), created: sprintf('2024-01-%02d', $day));
        }

        $this->client->request('GET', '/api/services/sound-engineering', ['page' => '2']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/SoundEngineerSearchResult',
            '@id' => '/api/services/sound-engineering',
            '@type' => 'Collection',
            'totalItems' => 13,
            // The newest first: the oldest is the only one left for page 2.
            'member' => [$this->card('engineer_01')],
            'view' => [
                '@id' => '/api/services/sound-engineering?page=2',
                '@type' => 'PartialCollectionView',
                'first' => '/api/services/sound-engineering?page=1',
                'last' => '/api/services/sound-engineering?page=2',
                'previous' => '/api/services/sound-engineering?page=1',
            ],
        ]);
    }

    public function test_an_unknown_speciality_is_refused(): void
    {
        $this->client->request('GET', '/api/services/sound-engineering', ['speciality' => 'teaching']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'speciality', 'message' => "Cette spécialité n'existe pas", 'code' => Choice::NO_SUCH_CHOICE_ERROR],
            ],
            'detail' => "speciality: Cette spécialité n'existe pas",
            'description' => "speciality: Cette spécialité n'existe pas",
            'type' => '/validation_errors/' . Choice::NO_SUCH_CHOICE_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_a_style_that_is_not_an_id_is_refused(): void
    {
        $this->client->request('GET', '/api/services/sound-engineering', ['style' => 'rock']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . Uuid::INVALID_CHARACTERS_ERROR,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                ['propertyPath' => 'style', 'message' => "Le style n'est pas valide", 'code' => Uuid::INVALID_CHARACTERS_ERROR],
            ],
            'detail' => "style: Le style n'est pas valide",
            'description' => "style: Le style n'est pas valide",
            'type' => '/validation_errors/' . Uuid::INVALID_CHARACTERS_ERROR,
            'title' => 'An error occurred',
        ]);
    }

    public function test_an_unknown_style_is_not_found(): void
    {
        $this->client->request('GET', '/api/services/sound-engineering', ['style' => '01a1e874-d06d-70df-8c07-b94cba3921d4']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Style introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Style introuvable',
        ]);
    }

    /**
     * A published sound engineering service, its details given as SoundEngineeringDetails' own fields.
     *
     * @param array<string, mixed> $details
     * @param list<Style>          $styles
     */
    private function engineer(string $username, array $details = [], array $styles = [], string $created = '2024-01-01'): Service
    {
        $service = ServiceFactory::new()->soundEngineering()->create([
            'user' => UserFactory::new()->create(['username' => $username]),
            'creationDatetime' => new DateTimeImmutable($created . 'T10:00:00+00:00'),
            ...$details,
        ]);
        foreach ($styles as $style) {
            $service->addStyle($style);
        }
        $this->flush();

        return $service;
    }

    private function flush(): void
    {
        self::getContainer()->get('doctrine')->getManager()->flush();
    }

    /**
     * One result as the search answers it: a sound engineer with nothing filled in, but what is given.
     *
     * @param array<string, mixed> $filled
     *
     * @return array<string, mixed>
     */
    private function card(string $username, array $filled = []): array
    {
        return [
            '@id' => '/api/sound_engineer_search_results/' . $username,
            '@type' => 'SoundEngineerSearchResult',
            'username' => $username,
            'name' => $username,
            'specialities' => [],
            'online' => false,
            'styles' => [],
            ...$filled,
        ];
    }
}
