<?php declare(strict_types=1);

namespace App\Tests\Integration\State;

use ApiPlatform\Metadata\Patch;
use App\Repository\BandSpace\SongRepository;
use App\Repository\UserRepository;
use App\Service\Builder\BandSpace\SongBuilder;
use App\State\Processor\BandSpace\Setlist\Song\SongUpdateProcessor;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Two members editing one song at once (#1069). Driven at the processor because the race needs the
 * song to change between the provider's read and the write, which one HTTP request cannot stage.
 */
#[ResetDatabase]
class SongUpdateProcessorTest extends KernelTestCase
{
    public function test_a_field_left_out_of_the_patch_keeps_what_another_member_saved_meanwhile(): void
    {
        self::bootKernel();
        $user = UserFactory::new()->asBaseUser()->create();
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $user])->create();
        $song = SongFactory::new()->create(['bandSpace' => $space, 'title' => 'Neon Tide', 'tonality' => 'C', 'tempo' => 100]);
        $container = self::getContainer();

        // The request's snapshot, as SongItemProvider built it before anything else happened.
        $snapshot = $container->get(SongBuilder::class)->buildItem($song);
        $snapshot->tempo = 140;

        // Another member saves the key in the meantime.
        $container->get(EntityManagerInterface::class)->getConnection()
            ->executeStatement('UPDATE band_space_song SET tonality = :key WHERE id = :id', ['key' => 'D', 'id' => (string) $song->id]);
        $container->get(EntityManagerInterface::class)->clear();

        $member = $container->get(UserRepository::class)->find($user->id);
        $container->get('security.token_storage')->setToken(new UsernamePasswordToken($member, 'main', $member->getRoles()));
        $container->get(RequestStack::class)->push(Request::create(
            '/api/band_spaces/' . $space->id . '/songs/' . $song->id,
            'PATCH',
            content: '{"tempo":140}',
        ));

        $container->get(SongUpdateProcessor::class)->process(
            $snapshot,
            new Patch(),
            ['bandSpaceId' => (string) $space->id, 'id' => (string) $song->id],
        );

        $container->get(EntityManagerInterface::class)->clear();
        $saved = $container->get(SongRepository::class)->find((string) $song->id);
        $this->assertSame(140, $saved->tempo);
        $this->assertSame('D', $saved->tonality, 'The key another member saved is not written back from the snapshot');
        $this->assertSame('Neon Tide', $saved->title);
    }
}
