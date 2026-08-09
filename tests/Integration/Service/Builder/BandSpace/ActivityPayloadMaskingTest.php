<?php declare(strict_types=1);

namespace App\Tests\Integration\Service\Builder\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceActivity;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\Task;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Service\Builder\BandSpace\BandSpaceActivityBuilder;
use App\Service\Builder\BandSpace\File\BandSpaceFileActivityBuilder;
use App\Service\Builder\BandSpace\TaskActivityBuilder;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Three builders turn a stored BandSpaceActivity into a response DTO, and only one of them serves the
 * module invitation activities are recorded under. That makes the other two safe by coincidence: what
 * keeps an address away from them is a module filter in a provider, which a future cross-module feed
 * would drop without anything failing. So the row here carries an address under the Task module, and
 * every builder is asked to render it.
 */
class ActivityPayloadMaskingTest extends KernelTestCase
{
    private const string RAW_EMAIL = 'john.doe@gmail.com';

    /** @var array<string, string> */
    private const array MASKED_PAYLOAD = ['email' => 'j***@gmail.com', 'label' => 'Studio'];

    public function test_an_email_in_an_activity_payload_is_masked_whichever_builder_renders_it(): void
    {
        self::bootKernel();
        // Built by hand rather than fetched: each of the three has a single consumer, so the container
        // is free to inline it. The masking guarantee belongs to the classes, not to the wiring.
        $profilePictureUrlBuilder = self::getContainer()->get(UserProfilePictureUrlBuilder::class);

        $bandSpace = new BandSpace();
        $bandSpace->name = 'The Rockers';

        $actor = new User();
        $actor->id = '3f8b4a1e-0000-4000-8000-000000000001';
        $actor->username = 'admin_user';

        $activity = new BandSpaceActivity();
        $activity->bandSpace = $bandSpace;
        $activity->module = BandSpaceModule::Task;
        $activity->type = 'invitation_sent';
        $activity->actor = $actor;
        $activity->payload = ['email' => self::RAW_EMAIL, 'label' => 'Studio'];

        $task = new Task();
        $task->bandSpace = $bandSpace;
        $task->title = 'Répéter le nouveau morceau';

        $file = new BandSpaceFile();
        $file->bandSpace = $bandSpace;

        self::assertSame(
            self::MASKED_PAYLOAD,
            (new BandSpaceActivityBuilder($profilePictureUrlBuilder))->buildItem($activity)->payload,
        );
        self::assertSame(
            self::MASKED_PAYLOAD,
            (new TaskActivityBuilder($profilePictureUrlBuilder))->buildItem($task, $activity)->payload,
        );
        self::assertSame(
            self::MASKED_PAYLOAD,
            (new BandSpaceFileActivityBuilder($profilePictureUrlBuilder))->buildItem($file, $activity)->payload,
        );
    }
}
