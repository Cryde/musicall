<?php

declare(strict_types=1);

namespace App\State\Processor\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Forum\ForumPostEdit;
use App\ApiResource\Forum\ForumPostResource;
use App\Entity\Forum\ForumPost;
use App\Repository\Forum\ForumPostRepository;
use App\Service\Procedure\Forum\ForumPostEditProcedure;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<ForumPostEdit, ForumPostResource>
 */
readonly class ForumPostEditProcessor implements ProcessorInterface
{
    public function __construct(
        private ForumPostRepository    $forumPostRepository,
        private ForumPostEditProcedure $forumPostEditProcedure,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ForumPostResource
    {
        $post = $this->forumPostRepository->find($uriVariables['id']);
        if (!$post instanceof ForumPost) {
            throw new NotFoundHttpException('Message de forum inexistant');
        }

        return $this->forumPostEditProcedure->process($post, $data->content);
    }
}
