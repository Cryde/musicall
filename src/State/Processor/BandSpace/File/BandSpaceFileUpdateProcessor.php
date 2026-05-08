<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileResource;
use App\Entity\User;
use App\Procedure\BandSpace\BandSpaceFileUpdateProcedure;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\File\BandSpaceFileBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<BandSpaceFileResource, BandSpaceFileResource>
 */
readonly class BandSpaceFileUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFileRepository $fileRepository,
        private BandSpaceFileUpdateProcedure $updateProcedure,
        private BandSpaceFileBuilder $fileBuilder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceFileResource
    {
        /** @var BandSpaceFileResource $data */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $file = $this->fileRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if ($file === null || $file->archiveDatetime !== null) {
            throw new NotFoundHttpException('Fichier introuvable');
        }

        $payload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        $file = $this->updateProcedure->update($file, $payload, $data, $bandSpace, $user);

        return $this->fileBuilder->buildItem($file);
    }
}
