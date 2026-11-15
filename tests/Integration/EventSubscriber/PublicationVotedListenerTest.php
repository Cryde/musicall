<?php

declare(strict_types=1);

namespace App\Tests\Integration\EventSubscriber;

use App\Entity\Publication;
use App\Event\PublicationVotedEvent;
use App\EventSubscriber\PublicationVotedListener;
use App\Repository\Notification\NotificationRepository;
use App\Service\Notification\NotificationCreator;
use App\Service\User\UserNotificationPreferenceChecker;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class PublicationVotedListenerTest extends KernelTestCase
{
    /** The vote is already flushed when the listener runs: a failed notification is logged, never thrown (epic #689). */
    public function test_a_failed_notification_is_logged_and_does_not_reach_the_vote(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $publication = PublicationFactory::new([
            'author' => $author,
            'status' => Publication::STATUS_ONLINE,
            'subCategory' => PublicationSubCategoryFactory::new()->asArticle()->create(),
            'type' => Publication::TYPE_TEXT,
        ])->create();
        $failure = new \RuntimeException('Connection lost');
        $repository = $this->createStub(NotificationRepository::class);
        $repository->method('findUnreadPublicationVoted')->willThrowException($failure);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            'Failed to create publication vote notification',
            ['publication_id' => (string) $publication->id, 'exception' => $failure],
        );

        $listener = new PublicationVotedListener(
            self::getContainer()->get(NotificationCreator::class),
            $repository,
            self::getContainer()->get(UserNotificationPreferenceChecker::class),
            $logger,
        );

        $listener(new PublicationVotedEvent($publication, null));
    }
}
