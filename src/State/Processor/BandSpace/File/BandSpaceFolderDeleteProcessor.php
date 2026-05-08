<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFolderResource;
use App\Entity\User;
use App\Enum\BandSpace\Role;
use App\Repository\BandSpace\BandSpaceFolderRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<BandSpaceFolderResource, void>
 */
readonly class BandSpaceFolderDeleteProcessor implements ProcessorInterface
{
    private const string STRATEGY_MOVE_TO_ROOT = 'move_to_root';
    private const string STRATEGY_CASCADE = 'cascade';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFolderRepository $folderRepository,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [, $membership] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $folder = $this->folderRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $membership->bandSpace);
        if ($folder === null) {
            throw new NotFoundHttpException('Dossier introuvable');
        }

        $strategy = $this->requestStack->getCurrentRequest()?->query->getString('strategy') ?: self::STRATEGY_MOVE_TO_ROOT;

        if (!in_array($strategy, [self::STRATEGY_MOVE_TO_ROOT, self::STRATEGY_CASCADE], true)) {
            throw new BadRequestHttpException(sprintf('Stratégie de suppression invalide : %s', $strategy));
        }

        if ($strategy === self::STRATEGY_CASCADE && $membership->role !== Role::Admin) {
            throw new AccessDeniedHttpException('Seul un administrateur peut supprimer un dossier en cascade');
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            if ($strategy === self::STRATEGY_CASCADE) {
                $descendantIds = $this->folderRepository->findDescendantIds($folder);
                $connection->executeStatement(
                    'UPDATE band_space_file SET archive_datetime = :now WHERE folder_id IN (:ids) AND archive_datetime IS NULL',
                    ['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'), 'ids' => $descendantIds],
                    ['ids' => \Doctrine\DBAL\ArrayParameterType::STRING],
                );
            } else {
                $connection->executeStatement(
                    'UPDATE band_space_folder SET parent_id = NULL WHERE parent_id = :folderId',
                    ['folderId' => (string) $folder->id],
                );
                $connection->executeStatement(
                    'UPDATE band_space_file SET folder_id = NULL WHERE folder_id = :folderId',
                    ['folderId' => (string) $folder->id],
                );
            }

            $this->entityManager->remove($folder);
            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }
}
