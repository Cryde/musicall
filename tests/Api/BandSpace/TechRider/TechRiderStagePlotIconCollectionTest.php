<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\TechRider;

use App\Enum\BandSpace\TechRiderStagePlotIcon;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The catalogue exists so the icon picker has one source of truth rather than a JS constant that
 * drifts the first time an icon is added. It is static application data, so it is not band space
 * scoped and only asks that you are logged in.
 */
#[ResetDatabase]
class TechRiderStagePlotIconCollectionTest extends ApiTestCase
{
    private const string URL = '/api/band_space_tech_rider_stage_plot_icons';

    use ApiTestAssertionsTrait;

    public function test_the_catalogue_lists_every_icon_once(): void
    {
        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->request('GET', self::URL, [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();

        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        $slugs = array_column($body['member'], 'slug');

        $this->assertSame(TechRiderStagePlotIcon::values(), $slugs);
        $this->assertSame(count($slugs), count(array_unique($slugs)));
        $this->assertSame(count(TechRiderStagePlotIcon::cases()), $body['totalItems']);
    }

    /** The full shape of one entry, so a renamed field shows up here. */
    public function test_an_entry_carries_its_label_category_and_image(): void
    {
        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->request('GET', self::URL, [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();

        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        $drumKit = array_values(array_filter(
            $body['member'],
            static fn (array $entry): bool => $entry['slug'] === 'drum_kit',
        ));

        $this->assertSame([
            '@id' => '/api/band_space_tech_rider_stage_plot_icons/drum_kit',
            '@type' => 'TechRiderStagePlotIcon',
            'slug' => 'drum_kit',
            'label' => 'Batterie',
            'category' => 'instrument',
            'category_label' => 'Instruments',
            'category_colour' => '#059669',
            'image_url' => '/images/band_space/stage_plot/drum_kit.png',
        ], $drumKit[0]);
    }

    /**
     * Every advertised image resolves to a file that exists. A slug added without artwork would
     * otherwise reach the picker as a broken image and nothing would fail.
     */
    public function test_every_advertised_image_exists_on_disk(): void
    {
        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->request('GET', self::URL, [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();

        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertNotEmpty($body['member']);

        $missing = [];
        foreach ($body['member'] as $entry) {
            // tests/Api/BandSpace/TechRider -> project root
            if (!is_file(\dirname(__DIR__, 4) . '/public' . $entry['image_url'])) {
                $missing[] = $entry['image_url'];
            }
        }

        $this->assertSame([], $missing);
    }

    /**
     * The collection's own envelope. The per-entry assertions above deliberately compare against
     * the enum rather than a hardcoded list of 21, so nothing else here would notice the wrapper
     * changing shape.
     */
    public function test_the_collection_envelope_is_well_formed(): void
    {
        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->request('GET', self::URL, [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();

        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame('/api/contexts/TechRiderStagePlotIcon', $body['@context']);
        $this->assertSame(self::URL, $body['@id']);
        $this->assertSame('Collection', $body['@type']);
        $this->assertSame(count(TechRiderStagePlotIcon::cases()), $body['totalItems']);
        // Unpaginated on purpose: a static catalogue split across pages would make the picker
        // fetch twice to show one list.
        $this->assertArrayNotHasKey('view', $body);
    }

    /** The @id advertised on every entry resolves, which is the reason the item route exists. */
    public function test_the_advertised_item_url_resolves(): void
    {
        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->request(
            'GET',
            self::URL . '/drum_kit',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRiderStagePlotIcon',
            '@id' => self::URL . '/drum_kit',
            '@type' => 'TechRiderStagePlotIcon',
            'slug' => 'drum_kit',
            'label' => 'Batterie',
            'category' => 'instrument',
            'category_label' => 'Instruments',
            'category_colour' => '#059669',
            'image_url' => '/images/band_space/stage_plot/drum_kit.png',
        ]);
    }

    public function test_an_unknown_slug_is_not_found(): void
    {
        $this->client->loginUser(UserFactory::new()->asBaseUser()->create());
        $this->client->request(
            'GET',
            self::URL . '/theremin',
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Icône introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Icône introuvable',
        ]);
    }

    public function test_the_catalogue_requires_being_logged_in(): void
    {
        $this->client->request('GET', self::URL, [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }
}
