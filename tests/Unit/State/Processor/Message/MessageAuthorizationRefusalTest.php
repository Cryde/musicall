<?php

declare(strict_types=1);

namespace App\Tests\Unit\State\Processor\Message;

use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\ApiResource\Message\MessageCreation;
use App\ApiResource\Message\MessageThreadMetaResource;
use App\ApiResource\Message\MessageThreadResource;
use App\Entity\Message\MessageThread;
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;
use App\Repository\Message\MessageRepository;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Repository\Message\MessageThreadRepository;
use App\Service\Access\ThreadAccess;
use App\Service\Builder\Message\MessageBuilder;
use App\Service\Builder\Message\MessageThreadMetaBuilder;
use App\Service\Message\DirectMessageMediaStore;
use App\Service\Message\ReplyTargetResolver;
use App\Service\Procedure\Message\MessageSenderProcedure;
use App\Service\User\Relation\UserContactPolicy;
use App\Service\User\UserNotificationPreferenceChecker;
use App\State\Processor\Message\MessagePostProcessor;
use App\State\Processor\Message\MessageThreadMetaPatchProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/** A 403, never a 401, for two refusals no API call reaches: a provider answers 404 first (#1026). */
class MessageAuthorizationRefusalTest extends TestCase
{
    public function test_posting_in_a_thread_you_are_not_part_of_is_forbidden(): void
    {
        $thread = new MessageThread();
        $thread->id = '01900000-0000-7000-8000-000000000001';
        $threadRepository = $this->createStub(MessageThreadRepository::class);
        $threadRepository->method('find')->willReturn($thread);
        $threadAccess = $this->createStub(ThreadAccess::class);
        $threadAccess->method('isOneOfParticipant')->willReturn(false);
        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn(new RateLimit(1, new \DateTimeImmutable(), true, 10));
        $limiterFactory = $this->createStub(RateLimiterFactoryInterface::class);
        $limiterFactory->method('create')->willReturn($limiter);

        $processor = new MessagePostProcessor(
            $this->authenticatedAs($this->user('01900000-0000-7000-8000-0000000000aa')),
            $threadAccess,
            $this->createStub(MessageSenderProcedure::class),
            $this->createStub(MessageBuilder::class),
            $threadRepository,
            $this->createStub(MessageRepository::class),
            $this->createStub(UserContactPolicy::class),
            $this->createStub(DirectMessageMediaStore::class),
            $this->createStub(ReplyTargetResolver::class),
            $this->createStub(LoggerInterface::class),
            $limiterFactory,
        );

        $input = new MessageCreation();
        $input->thread = new MessageThreadResource();
        $input->thread->id = (string) $thread->id;

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('message.thread.not_participant');

        $processor->process($input, new Post());
    }

    public function test_patching_somebody_elses_thread_meta_is_forbidden(): void
    {
        $meta = new MessageThreadMeta();
        $meta->id = '01900000-0000-7000-8000-000000000002';
        $meta->user = $this->user('01900000-0000-7000-8000-0000000000bb');
        $metaRepository = $this->createStub(MessageThreadMetaRepository::class);
        $metaRepository->method('find')->willReturn($meta);

        $processor = new MessageThreadMetaPatchProcessor(
            $this->authenticatedAs($this->user('01900000-0000-7000-8000-0000000000aa')),
            $this->createStub(EntityManagerInterface::class),
            $metaRepository,
            $this->createStub(MessageThreadMetaBuilder::class),
            $this->createStub(MessageRepository::class),
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(UserNotificationPreferenceChecker::class),
        );

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('message.thread_meta.update_forbidden');

        $processor->process(new MessageThreadMetaResource(), new Patch(), ['id' => $meta->id]);
    }

    private function authenticatedAs(User $user): Security
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);
        $security->method('getUser')->willReturn($user);

        return $security;
    }

    private function user(string $id): User
    {
        $user = new User();
        $user->id = $id;
        $user->username = 'user_' . substr($id, -2);

        return $user;
    }
}
