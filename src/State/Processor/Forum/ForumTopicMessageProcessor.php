<?php declare(strict_types=1);

namespace App\State\Processor\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Forum\ForumTopic;
use App\ApiResource\Forum\ForumTopicMessage;
use App\Service\Procedure\Forum\TopicCreationProcedure;

/**
 * @implements ProcessorInterface<ForumTopicMessage, ForumTopic>
 */
class ForumTopicMessageProcessor implements ProcessorInterface
{
    public function __construct(readonly private TopicCreationProcedure $topicCreationProcedure)
    {
    }

    /**
     * @param ForumTopicMessage $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ForumTopic
    {
        return $this->topicCreationProcedure->process($data->forum, $data->title, $data->message);
    }
}
