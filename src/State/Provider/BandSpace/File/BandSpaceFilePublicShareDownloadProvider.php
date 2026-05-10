<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceFileShareRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\File\FileShareTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * @implements ProviderInterface<StreamedResponse>
 */
readonly class BandSpaceFilePublicShareDownloadProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceFileShareRepository $shareRepository,
        private FileShareTokenService $tokenService,
        private PasswordHasherFactoryInterface $passwordHasherFactory,
        private BandSpaceActivityRecorder $activityRecorder,
        private StorageInterface $vichStorage,
        private RequestStack $requestStack,
        #[Target('band_space_file_share_access')]
        private RateLimiterFactoryInterface $shareAccessLimiter,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StreamedResponse
    {
        $request = $this->requestStack->getCurrentRequest();
        $clientIp = $request?->getClientIp() ?? 'unknown';
        $this->shareAccessLimiter->create($clientIp)->consume()->ensureAccepted();

        $token = (string) $uriVariables['token'];
        $share = $this->shareRepository->findOneByTokenHash($this->tokenService->hashOf($token));
        if (!$share instanceof \App\Entity\BandSpace\BandSpaceFileShare) {
            throw new NotFoundHttpException('Lien de partage introuvable');
        }

        if ($share->revocationDatetime instanceof \DateTimeImmutable) {
            throw new GoneHttpException('Ce lien de partage a été révoqué');
        }

        $now = new \DateTimeImmutable();
        if ($share->expiryDatetime instanceof \DateTimeImmutable && $share->expiryDatetime <= $now) {
            throw new GoneHttpException('Ce lien de partage a expiré');
        }

        $file = $share->bandSpaceFile;
        if ($file->archiveDatetime instanceof \DateTimeImmutable) {
            throw new NotFoundHttpException('Fichier introuvable');
        }
        if (!$file->currentVersion instanceof \App\Entity\BandSpace\BandSpaceFileVersion) {
            throw new NotFoundHttpException('Aucune version disponible pour ce fichier');
        }

        if ($share->passwordHash !== null) {
            $submittedPassword = $request?->query->getString('password') ?? '';
            if ($submittedPassword === '') {
                throw new UnauthorizedHttpException('Bearer', 'Mot de passe requis');
            }
            $hasher = $this->passwordHasherFactory->getPasswordHasher(User::class);
            if (!$hasher->verify($share->passwordHash, $submittedPassword)) {
                throw new UnauthorizedHttpException('Bearer', 'Mot de passe incorrect');
            }
        }

        $share->accessCount += 1;
        $share->lastAccessDatetime = $now;

        $this->activityRecorder->record(
            $file->bandSpace,
            BandSpaceModule::File,
            BandSpaceFileActivityType::PublicAccessed,
            resourceId: (string) $file->id,
            actor: null,
            payload: [
                'share_id' => (string) $share->id,
                'ip' => $clientIp,
            ],
        );

        $this->entityManager->flush();

        return BandSpaceFileDownloadProvider::stream($file, $file->currentVersion, $this->vichStorage);
    }
}
