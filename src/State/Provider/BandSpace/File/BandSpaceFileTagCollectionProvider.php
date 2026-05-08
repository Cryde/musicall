<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileTagResource;
use App\Entity\BandSpace\BandSpaceFileTag;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceFileTagRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\File\BandSpaceFileTagBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<BandSpaceFileTagResource>
 */
readonly class BandSpaceFileTagCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFileTagRepository $tagRepository,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileTagBuilder $tagBuilder,
        private Security $security,
    ) {
    }

    /**
     * @return BandSpaceFileTagResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $tags = $this->tagRepository->findByBandSpace($bandSpace);

        $tagIds = array_map(fn (BandSpaceFileTag $tag): string => (string) $tag->id, $tags);
        $fileCounts = $this->fileRepository->countByTagIds($tagIds);

        return $this->tagBuilder->buildFromList($tags, $fileCounts);
    }
}
