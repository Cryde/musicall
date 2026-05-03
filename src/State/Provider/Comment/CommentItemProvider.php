<?php declare(strict_types=1);

namespace App\State\Provider\Comment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Comment\CommentResource;
use App\Entity\User;
use App\Repository\Comment\CommentRepository;
use App\Repository\Metric\VoteRepository;
use App\Service\Builder\Comment\CommentBuilder;
use App\Service\Identifier\RequestIdentifier;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class CommentItemProvider implements ProviderInterface
{
    public function __construct(
        private CommentRepository $commentRepository,
        private VoteRepository $voteRepository,
        private CommentBuilder $commentBuilder,
        private Security $security,
        private RequestIdentifier $requestIdentifier,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CommentResource
    {
        $comment = $this->commentRepository->find($uriVariables['id']);
        if (!$comment) {
            throw new NotFoundHttpException('Commentaire introuvable');
        }

        $userVote = null;
        if ($comment->voteCache !== null) {
            /** @var User|null $user */
            $user = $this->security->getUser();
            if ($user) {
                $vote = $this->voteRepository->findOneByUserAndVoteCache($user, $comment->voteCache);
                $userVote = $vote?->value;
            } else {
                $request = $this->requestStack->getCurrentRequest();
                if ($request !== null) {
                    $identifier = $this->requestIdentifier->fromRequest($request);
                    $vote = $this->voteRepository->findOneByIdentifierAndVoteCache($identifier, $comment->voteCache);
                    $userVote = $vote?->value;
                }
            }
        }

        return $this->commentBuilder->buildItem($comment, $userVote);
    }
}
