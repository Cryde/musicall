<?php

namespace App\Processor\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Forum\ForumPost;
use App\Service\Procedure\Forum\MessageCreationProcedure;

class ForumPostPostProcessor implements ProcessorInterface
{
    public function __construct(private readonly MessageCreationProcedure $messageCreationProcedure)
    {
    }

    /** @param ForumPost $data */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if ($operation instanceof Post) {
            return $this->messageCreationProcedure->process($data->getTopic(), $data->getContent());
        }
    }
}