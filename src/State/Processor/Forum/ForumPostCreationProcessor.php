<?php

declare(strict_types=1);

namespace App\State\Processor\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Forum\ForumPostCreation;
use App\Entity\Forum\ForumPost;
use App\Service\Procedure\Forum\MessageCreationProcedure;

/**
 * @implements ProcessorInterface<ForumPostCreation, ForumPost>
 */
readonly class ForumPostCreationProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageCreationProcedure $messageCreationProcedure,
    ) {
    }

    /**
     * @param ForumPostCreation $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ForumPost
    {
        return $this->messageCreationProcedure->process($data->topic, $data->content);
    }
}
