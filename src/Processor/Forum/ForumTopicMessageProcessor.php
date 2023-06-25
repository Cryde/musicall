<?php

namespace App\Processor\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Forum\ForumTopicMessage;
use App\Service\Procedure\Forum\TopicCreationProcedure;

class ForumTopicMessageProcessor implements ProcessorInterface
{
    public function __construct(readonly private TopicCreationProcedure $topicCreationProcedure)
    {
    }

    /**
     * @param ForumTopicMessage $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        return $this->topicCreationProcedure->process($data->forum, $data->title, $data->message);
    }
}