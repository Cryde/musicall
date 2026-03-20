<?php declare(strict_types=1);

namespace App\State\Processor\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Forum\ForumTopic;
use App\ApiResource\Forum\ForumTopicMessage;
use App\Entity\User;
use App\Service\Procedure\Forum\TopicCreationProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<ForumTopicMessage, ForumTopic>
 */
class ForumTopicMessageProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly TopicCreationProcedure $topicCreationProcedure,
        private readonly Security $security,
        #[Target('forum_topic_creation')]
        private readonly RateLimiterFactoryInterface $forumTopicCreationLimiter,
    ) {
    }

    /**
     * @param ForumTopicMessage $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ForumTopic
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $this->forumTopicCreationLimiter->create($user->getUserIdentifier())->consume()->ensureAccepted();

        return $this->topicCreationProcedure->process($data->forum, $data->title, $data->message);
    }
}
