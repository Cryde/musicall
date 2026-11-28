<?php

declare(strict_types=1);

namespace App\Tests\Api\Service\Search;

use App\Entity\Attribute\Style;
use App\Entity\Service\Service;
use App\Enum\Musician\MediaPlatform;
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

/** The producer search (#1413). */
#[ResetDatabase]
class ProducerSearchTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array PARIS = ['lat' => '48.8566', 'lng' => '2.3522'];
    // 7.1 km from Paris.
    private const array MONTREUIL = ['city' => 'Montreuil', 'latitude' => '48.8638', 'longitude' => '2.4485'];
    // 112 km.
    private const array ROUEN = ['city' => 'Rouen', 'latitude' => '49.4432', 'longitude' => '1.0999'];

    public function test_a_published_producer_is_found_with_what_their_card_shows(): void
    {
        $pop = StyleFactory::new()->asPop()->create();
        $sam = $this->producer('sam', [
            'headline' => 'Ton son, de la maquette au master',
            'specialities' => ['beatmaking', 'record_production'],
        ], styles: [$pop]);
        $sam->user->profile->displayName = 'Sam Diallo';
        ServicePlaceFactory::new()->remote()->create(['service' => $sam]);
        // Cheaper, but for a whole day: the card names the price of a track.
        ServiceOfferFactory::new()->create(['service' => $sam, 'unit' => OfferUnit::Day, 'quantity' => null, 'price' => 20000, 'title' => 'Une journée de studio']);
        ServiceOfferFactory::new()->create(['service' => $sam, 'unit' => OfferUnit::Track, 'quantity' => null, 'price' => 30000, 'title' => 'Une instru']);
        ServiceMediaFactory::new()->create(['service' => $sam, 'platform' => MediaPlatform::SOUNDCLOUD, 'url' => 'https://soundcloud.com/sam/beat-tape', 'embedId' => 'sam/beat-tape', 'title' => 'Beat tape']);
        $this->flush();

        $this->client->request('GET', '/api/services/production');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProducerSearchResult',
            '@id' => '/api/services/production',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/producer_search_results/sam',
                    '@type' => 'ProducerSearchResult',
                    'username' => 'sam',
                    'name' => 'Sam Diallo',
                    'headline' => 'Ton son, de la maquette au master',
                    'specialities' => ['beatmaking', 'record_production'],
                    'online' => true,
                    'price' => 30000,
                    'price_unit' => 'track',
                    'styles' => [['@type' => 'TeacherProfileStyle', 'id' => $pop->id, 'name' => 'Pop']],
                    'media' => ['@type' => 'TeacherSearchMedia', 'platform' => 'soundcloud', 'embed_id' => 'sam/beat-tape', 'title' => 'Beat tape'],
                ],
            ],
        ]);
    }

    /** Two services, never one (#1413): a sound engineer is not a producer, nor a draft or a private profile found. */
    public function test_drafts_private_profiles_and_other_kinds_are_never_found(): void
    {
        $this->producer('shown');
        $this->producer('draft')->status = ServiceStatus::Draft;
        $this->producer('private')->user->profile->isPublic = false;
        ServiceFactory::new()->soundEngineering()->create(['user' => UserFactory::new()->create(['username' => 'engineer'])]);
        $this->flush();

        $this->client->request('GET', '/api/services/production');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProducerSearchResult',
            '@id' => '/api/services/production',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->card('shown')],
        ]);
    }

    public function test_a_speciality_keeps_its_producers_and_comes_first_on_their_card(): void
    {
        $this->producer('beatmaker', ['specialities' => ['beatmaking']]);
        $this->producer('both', ['specialities' => ['beatmaking', 'topline']], created: '2024-01-02');
        $this->producer('arranger', ['specialities' => ['arrangement']]);

        $this->client->request('GET', '/api/services/production', ['speciality' => 'topline']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProducerSearchResult',
            '@id' => '/api/services/production',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->card('both', ['specialities' => ['topline', 'beatmaking']])],
            'view' => ['@id' => '/api/services/production?speciality=topline', '@type' => 'PartialCollectionView'],
        ]);
    }

    public function test_a_style_keeps_its_producers(): void
    {
        $pop = StyleFactory::new()->asPop()->create();
        $jazz = StyleFactory::new()->asJazz()->create();
        $this->producer('popper', styles: [$pop]);
        $this->producer('jazzman', styles: [$jazz]);

        $this->client->request('GET', '/api/services/production', ['style' => $jazz->id]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProducerSearchResult',
            '@id' => '/api/services/production',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [$this->card('jazzman', ['styles' => [['@type' => 'TeacherProfileStyle', 'id' => $jazz->id, 'name' => 'Jazz']]])],
            'view' => ['@id' => '/api/services/production?style=' . $jazz->id, '@type' => 'PartialCollectionView'],
        ]);
    }

    /** A producer works remotely first (#1413): online finds them wherever their studio is. */
    public function test_a_radius_keeps_the_places_in_reach_and_online_adds_remote_work(): void
    {
        ServicePlaceFactory::new()->atMyPlace(self::MONTREUIL)->create(['service' => $this->producer('montreuil')]);
        $rouenRemote = $this->producer('rouen_and_remote');
        ServicePlaceFactory::new()->atMyPlace(self::ROUEN)->create(['service' => $rouenRemote]);
        ServicePlaceFactory::new()->remote()->create(['service' => $rouenRemote]);
        ServicePlaceFactory::new()->atMyPlace(self::ROUEN)->create(['service' => $this->producer('rouen')]);

        $this->client->request('GET', '/api/services/production', ['radius' => '10', 'online' => '1'] + self::PARIS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProducerSearchResult',
            '@id' => '/api/services/production',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                $this->card('montreuil', ['distance' => 7.1]),
                // Found remotely, so no distance.
                $this->card('rouen_and_remote', ['online' => true]),
            ],
            'view' => ['@id' => '/api/services/production?lat=48.8566&lng=2.3522&online=1&radius=10', '@type' => 'PartialCollectionView'],
        ]);
    }

    public function test_the_second_page_holds_the_producers_after_the_first_twelve(): void
    {
        for ($day = 1; $day <= 13; ++$day) {
            $this->producer(sprintf('producer_%02d', $day), created: sprintf('2024-01-%02d', $day));
        }

        $this->client->request('GET', '/api/services/production', ['page' => '2']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProducerSearchResult',
            '@id' => '/api/services/production',
            '@type' => 'Collection',
            'totalItems' => 13,
            // The newest first: the oldest is the only one left for page 2.
            'member' => [$this->card('producer_01')],
            'view' => [
                '@id' => '/api/services/production?page=2',
                '@type' => 'PartialCollectionView',
                'first' => '/api/services/production?page=1',
                'last' => '/api/services/production?page=2',
                'previous' => '/api/services/production?page=1',
            ],
        ]);
    }

    public function test_a_style_that_is_not_an_id_is_refused(): void
    {
        $this->client->request('GET', '/api/services/production', ['style' => 'pop']);

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
        $this->client->request('GET', '/api/services/production', ['style' => '01a1e874-d06d-70df-8c07-b94cba3921d4']);

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

    /** Mixing stays a sound engineer's speciality (#1413). */
    public function test_a_sound_engineers_speciality_is_refused(): void
    {
        $this->client->request('GET', '/api/services/production', ['speciality' => 'mixing']);

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

    /**
     * A published production service.
     *
     * @param array<string, mixed> $fields
     * @param list<Style>          $styles
     */
    private function producer(string $username, array $fields = [], array $styles = [], string $created = '2024-01-01'): Service
    {
        $service = ServiceFactory::new()->production()->create([
            'user' => UserFactory::new()->create(['username' => $username]),
            'creationDatetime' => new DateTimeImmutable($created . 'T10:00:00+00:00'),
            ...$fields,
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
     * One result as the search answers it: a producer with nothing filled in, but what is given.
     *
     * @param array<string, mixed> $filled
     *
     * @return array<string, mixed>
     */
    private function card(string $username, array $filled = []): array
    {
        return [
            '@id' => '/api/producer_search_results/' . $username,
            '@type' => 'ProducerSearchResult',
            'username' => $username,
            'name' => $username,
            'specialities' => [],
            'online' => false,
            'styles' => [],
            ...$filled,
        ];
    }
}
