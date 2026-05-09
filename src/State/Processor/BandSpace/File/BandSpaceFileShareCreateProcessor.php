<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileShareCreate;
use App\ApiResource\BandSpace\File\BandSpaceFileShareCreated;
use App\Entity\BandSpace\BandSpaceFileShare;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Security\BandSpace\BandSpaceAdminChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\File\FileShareTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<BandSpaceFileShareCreate, BandSpaceFileShareCreated>
 */
readonly class BandSpaceFileShareCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceAdminChecker $adminChecker,
        private BandSpaceFileRepository $fileRepository,
        private FileShareTokenService $tokenService,
        private PasswordHasherFactoryInterface $passwordHasherFactory,
        private BandSpaceActivityRecorder $activityRecorder,
        private RequestStack $requestStack,
        private Security $security,
        #[Target('band_space_file_share_create')]
        private RateLimiterFactoryInterface $shareCreateLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceFileShareCreated
    {
        /** @var BandSpaceFileShareCreate $data */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $this->shareCreateLimiter->create((string) $user->id)->consume()->ensureAccepted();

        [$bandSpace] = $this->adminChecker->checkAdmin((string) $uriVariables['bandSpaceId'], $user);

        $file = $this->fileRepository->findOneByIdAndBandSpace((string) $uriVariables['fileId'], $bandSpace);
        if ($file === null || $file->archiveDatetime !== null) {
            throw new NotFoundHttpException('Fichier introuvable');
        }

        $expiry = $this->parseFutureDatetime($data->expiryDatetime);

        $tokenPair = $this->tokenService->generate();

        $share = new BandSpaceFileShare();
        $share->bandSpaceFile = $file;
        $share->createdBy = $user;
        $share->tokenHash = $tokenPair['hash'];
        $share->expiryDatetime = $expiry;

        if ($data->password !== null && $data->password !== '') {
            $hasher = $this->passwordHasherFactory->getPasswordHasher(User::class);
            $share->passwordHash = $hasher->hash($data->password);
        }

        $this->entityManager->persist($share);

        $this->activityRecorder->record(
            $bandSpace,
            BandSpaceModule::File,
            BandSpaceFileActivityType::Shared,
            resourceId: (string) $file->id,
            actor: $user,
            payload: [
                'expiry_datetime' => $expiry->format(\DateTimeInterface::ATOM),
                'has_password' => $share->passwordHash !== null,
            ],
        );

        $this->entityManager->flush();

        $request = $this->requestStack->getCurrentRequest();
        $baseUrl = $request !== null ? $request->getSchemeAndHttpHost() : '';
        $shareUrl = $baseUrl . '/shares/' . $tokenPair['token'];

        $created = new BandSpaceFileShareCreated();
        $created->shareId = (string) $share->id;
        $created->shareUrl = $shareUrl;
        $created->expiryDatetime = $expiry->format(\DateTimeInterface::ATOM);
        $created->hasPassword = $share->passwordHash !== null;

        return $created;
    }

    private function parseFutureDatetime(?string $raw): \DateTimeImmutable
    {
        if ($raw === null) {
            throw new UnprocessableEntityHttpException('La date d\'expiration est obligatoire');
        }
        try {
            $expiry = new \DateTimeImmutable($raw);
        } catch (\Exception) {
            throw new UnprocessableEntityHttpException('Date d\'expiration invalide');
        }
        if ($expiry <= new \DateTimeImmutable()) {
            throw new UnprocessableEntityHttpException('La date d\'expiration doit être dans le futur');
        }

        return $expiry;
    }
}
