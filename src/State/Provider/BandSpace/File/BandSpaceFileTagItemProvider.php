<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileTagResource;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Repository\BandSpace\BandSpaceFileTagRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\File\BandSpaceFileTagBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<BandSpaceFileTagResource>
 */
readonly class BandSpaceFileTagItemProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFileTagRepository $tagRepository,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileTagBuilder $tagBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?BandSpaceFileTagResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $tag = $this->tagRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$tag instanceof \App\Entity\BandSpace\BandSpaceFileTag) {
            throw new NotFoundHttpException('Tag introuvable');
        }

        $fileCounts = $this->fileRepository->countByTagIds([(string) $tag->id]);

        return $this->tagBuilder->buildItem($tag, $fileCounts[(string) $tag->id] ?? 0);
    }
}
