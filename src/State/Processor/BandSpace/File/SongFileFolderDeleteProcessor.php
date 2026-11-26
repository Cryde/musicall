<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Security\BandSpace\SongWriteChecker;
use App\Service\BandSpace\BandSpaceChangeSignal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Any member may delete a song folder: no file is lost. The database does the rest, its sub-folders go
 * with it (parent ON DELETE CASCADE) and every file in them returns to the song's root (the
 * attachment's song folder ON DELETE SET NULL).
 *
 * @implements ProcessorInterface<mixed, void>
 */
readonly class SongFileFolderDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private SongWriteChecker $songWriteChecker,
        private BandSpaceChangeSignal $changeSignal,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, , $folder] = $this->songWriteChecker->checkFolderForWrite(
            (string) $uriVariables['bandSpaceId'],
            (string) $uriVariables['songId'],
            (string) $uriVariables['id'],
            $user,
        );

        $this->entityManager->remove($folder);
        $this->entityManager->flush();

        $this->changeSignal->changed($bandSpace, BandSpaceModule::File);
    }
}
