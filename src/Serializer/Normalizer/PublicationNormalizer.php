<?php declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Entity\Publication;
use App\Entity\User;
use App\Repository\Metric\VoteRepository;
use App\Service\Identifier\RequestIdentifier;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PublicationNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'PUBLICATION_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        private readonly VoteRepository    $voteRepository,
        private readonly Security          $security,
        private readonly RequestIdentifier $requestIdentifier,
        private readonly RequestStack      $requestStack,
    ) {
    }

    public function normalize(mixed $publication, ?string $format = null, array $context = []): float|int|bool|\ArrayObject|array|string|null
    {
        $context[self::ALREADY_CALLED] = true;
        /** @var Publication $publication */
        $arrayPublication = $this->normalizer->normalize($publication, $format, $context);
        if (is_array($arrayPublication)) {
            $voteCache = $publication->getVoteCache();
            $arrayPublication['upvotes'] = $voteCache?->getUpvoteCount() ?? 0;
            $arrayPublication['downvotes'] = $voteCache?->getDownvoteCount() ?? 0;
            $arrayPublication['user_vote'] = $this->resolveUserVote($publication);
        }

        return $arrayPublication;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof Publication;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Publication::class => false];
    }

    private function resolveUserVote(Publication $publication): ?int
    {
        $voteCache = $publication->getVoteCache();
        if (!$voteCache) {
            return null;
        }

        /** @var User|null $user */
        $user = $this->security->getUser();
        if ($user) {
            $vote = $this->voteRepository->findOneByUserAndVoteCache($user, $voteCache);

            return $vote?->getValue();
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $identifier = $this->requestIdentifier->fromRequest($request);
            $vote = $this->voteRepository->findOneByIdentifierAndVoteCache($identifier, $voteCache);

            return $vote?->getValue();
        }

        return null;
    }
}
