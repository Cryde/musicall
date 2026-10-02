<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\TechRider;

use App\Entity\BandSpace\BandSpace;
use App\Enum\BandSpace\TechRiderItemType;
use App\Enum\BandSpace\TechRiderPatchDirection;
use App\Service\BandSpace\TechRider\TechRiderMicrophoneCatalogue;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TechRiderFactory;
use App\Tests\Factory\BandSpace\TechRiderItemFactory;
use App\Tests\Factory\BandSpace\TechRiderPatchRowFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The Micro / DI suggestions of the patch list editor (#1099): what the band already uses, most
 * used first, then the catalogue less those.
 */
#[ResetDatabase]
class TechRiderMicrophoneSuggestionsTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_a_member_gets_the_band_s_microphones_then_the_rest_of_the_catalogue(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        // Two riders, one archived: both are the band's habit.
        $this->seedMicrophones($bandSpace, ['SM58', 'SM58', 'Beta 91A', 'Sontronics Halo'], archived: false);
        $this->seedMicrophones($bandSpace, ['SM58', 'Beta 91A'], archived: true);
        // Outputs carry no microphone worth suggesting, and another band's habits are not this one's.
        $this->seedMicrophones($bandSpace, ['Ear monitor'], archived: false, direction: TechRiderPatchDirection::Output);
        $this->seedMicrophones(BandSpaceFactory::new()->create(), ['e906'], archived: false);

        $this->client->loginUser($user);
        $this->client->request('GET', $this->url($bandSpace));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRiderMicrophoneSuggestions',
            '@id' => $this->url($bandSpace),
            '@type' => 'TechRiderMicrophoneSuggestions',
            'used' => [
                ['name' => 'SM58', 'usage_count' => 3],
                ['name' => 'Beta 91A', 'usage_count' => 2],
                ['name' => 'Sontronics Halo', 'usage_count' => 1],
            ],
            'catalogue' => array_values(array_diff(TechRiderMicrophoneCatalogue::MODELS, ['SM58', 'Beta 91A'])),
        ]);
    }

    /**
     * The column compares without case, as the provider does: « sm58 » typed in a rider is the SM58
     * of the catalogue, offered once. A row with no microphone suggests nothing.
     */
    public function test_a_model_already_used_in_another_case_is_not_offered_twice(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $this->seedMicrophones($bandSpace, ['sm58', null], archived: false);

        $this->client->loginUser($user);
        $this->client->request('GET', $this->url($bandSpace));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRiderMicrophoneSuggestions',
            '@id' => $this->url($bandSpace),
            '@type' => 'TechRiderMicrophoneSuggestions',
            'used' => [['name' => 'sm58', 'usage_count' => 1]],
            'catalogue' => array_values(array_diff(TechRiderMicrophoneCatalogue::MODELS, ['SM58'])),
        ]);
    }

    /** Capped at 30: past that a band is typing its model, not picking it. */
    public function test_the_band_s_models_are_capped(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $models = array_map(static fn (int $n): string => sprintf('Modèle %02d', $n), range(1, 31));
        $this->seedMicrophones($bandSpace, $models, archived: false);

        $this->client->loginUser($user);
        $this->client->request('GET', $this->url($bandSpace));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRiderMicrophoneSuggestions',
            '@id' => $this->url($bandSpace),
            '@type' => 'TechRiderMicrophoneSuggestions',
            // Every model is used once, so the tie breaks on the name and the 31st is left out.
            'used' => array_map(
                static fn (string $model): array => ['name' => $model, 'usage_count' => 1],
                array_slice($models, 0, 30),
            ),
            'catalogue' => TechRiderMicrophoneCatalogue::MODELS,
        ]);
    }

    public function test_a_non_member_is_forbidden(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();

        $this->client->loginUser($user);
        $this->client->request('GET', $this->url($bandSpace));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Vous n'êtes pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Vous n'êtes pas membre de ce Band Space",
        ]);
    }

    public function test_an_anonymous_visitor_is_refused(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();

        $this->client->request('GET', $this->url($bandSpace));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    /**
     * @param list<string|null> $microphones
     */
    private function seedMicrophones(
        BandSpace $bandSpace,
        array $microphones,
        bool $archived,
        TechRiderPatchDirection $direction = TechRiderPatchDirection::Input,
    ): void {
        $rider = TechRiderFactory::new([
            'bandSpace' => $bandSpace,
            'name' => 'Rider',
            'archiveDatetime' => $archived ? new \DateTimeImmutable('2026-01-01') : null,
        ])->create();
        $item = TechRiderItemFactory::new([
            'techRider' => $rider,
            'type' => TechRiderItemType::PatchList,
            'title' => 'Patch list',
            'position' => 0,
        ])->create();
        foreach ($microphones as $index => $microphone) {
            TechRiderPatchRowFactory::new([
                'item' => $item,
                'direction' => $direction,
                'channel' => $index + 1,
                'microphone' => $microphone,
                'position' => $index,
            ])->create();
        }
    }

    private function url(BandSpace $bandSpace): string
    {
        return sprintf('/api/band_spaces/%s/tech_rider_microphones', $bandSpace->id);
    }
}
