<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileResource;
use App\ApiResource\BandSpace\File\BandSpaceFileUpload;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceFileAttachment;
use App\Entity\BandSpace\BandSpaceFileTag;
use App\Entity\BandSpace\BandSpaceFileVersion;
use App\Entity\BandSpace\BandSpaceFolder;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceFileTagRepository;
use App\Repository\BandSpace\BandSpaceFolderRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\EventListener\BandSpaceFileQuotaApproachingHeaderListener;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\File\BandSpaceFileMimeAllowlist;
use App\Service\BandSpace\File\BandSpaceFileQuotaService;
use App\Service\Builder\BandSpace\File\BandSpaceFileBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @implements ProcessorInterface<BandSpaceFileUpload, BandSpaceFileResource>
 */
readonly class BandSpaceFileUploadProcessor implements ProcessorInterface
{
    private const array ATTACHED_SOURCE_TYPES = ['task', 'finance'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFolderRepository $folderRepository,
        private BandSpaceFileTagRepository $tagRepository,
        private BandSpaceFileQuotaService $quotaService,
        private BandSpaceActivityRecorder $activityRecorder,
        private BandSpaceFileBuilder $fileBuilder,
        private ValidatorInterface $validator,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceFileResource
    {
        /** @var BandSpaceFileUpload $data */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $upload = $data->uploadedFile;
        if ($upload === null) {
            throw new BadRequestHttpException('Aucun fichier fourni');
        }

        $mimeType = $upload->getMimeType() ?? 'application/octet-stream';
        if (!BandSpaceFileMimeAllowlist::isAllowed($mimeType)) {
            throw new UnsupportedMediaTypeHttpException(sprintf('Type de fichier non autorisé : %s', $mimeType));
        }

        $size = $upload->getSize();
        if ($size === false) {
            throw new BadRequestHttpException('Taille de fichier invalide');
        }

        $this->quotaService->assertCanUpload($bandSpace, $size);

        $originalName = $upload instanceof UploadedFile ? $upload->getClientOriginalName() : $upload->getFilename();

        $folder = null;
        if ($data->folderId !== null && $data->folderId !== '') {
            $folder = $this->resolveFolder($data->folderId, $bandSpace);
        }

        $attachedSourceType = $data->attachedSourceType ?: null;
        $attachedSourceId = null;
        if ($attachedSourceType !== null) {
            if (!in_array($attachedSourceType, self::ATTACHED_SOURCE_TYPES, true)) {
                throw new BadRequestHttpException(sprintf('Type de source attaché invalide : %s', $attachedSourceType));
            }
            $rawSourceId = $data->attachedSourceId;
            if ($rawSourceId === null || $rawSourceId === '') {
                throw new BadRequestHttpException('attachedSourceId est requis quand attachedSourceType est défini');
            }
            $violations = $this->validator->validate($rawSourceId, [new Assert\Uuid()]);
            if (count($violations) > 0) {
                throw new BadRequestHttpException('attachedSourceId doit être un UUID valide');
            }
            $attachedSourceId = $rawSourceId;
        }

        $tags = [];
        if (count($data->tagIds) > 0) {
            $tags = $this->resolveTags($data->tagIds, $bandSpace);
        }

        $file = new BandSpaceFile();
        $file->bandSpace = $bandSpace;
        $file->createdBy = $user;
        $file->originalName = $originalName;
        $file->folder = $folder;
        foreach ($tags as $tag) {
            $file->tags->add($tag);
        }

        $version = new BandSpaceFileVersion();
        $version->bandSpaceFile = $file;
        $version->versionNumber = 1;
        $version->createdBy = $user;
        $version->mimeType = $mimeType;
        $version->size = $size;
        $version->setUploadedFile($upload);

        $this->entityManager->persist($file);
        $this->entityManager->persist($version);

        if ($attachedSourceType !== null) {
            $attachment = new BandSpaceFileAttachment();
            $attachment->bandSpaceFile = $file;
            $attachment->sourceType = $attachedSourceType;
            $attachment->sourceId = Uuid::fromString($attachedSourceId);
            $attachment->attachedBy = $user;
            $this->entityManager->persist($attachment);
        }

        $this->entityManager->flush();

        $file->currentVersion = $version;

        $this->activityRecorder->record(
            $bandSpace,
            BandSpaceModule::File,
            BandSpaceFileActivityType::Uploaded,
            resourceId: $file->id !== null ? (string) $file->id : null,
            actor: $user,
            payload: [
                'original_name' => $file->originalName,
                'size' => $size,
                'mime_type' => $mimeType,
            ],
        );

        $this->entityManager->flush();

        if ($this->quotaService->isApproachingLimit($bandSpace)) {
            $this->requestStack->getCurrentRequest()?->attributes->set(
                BandSpaceFileQuotaApproachingHeaderListener::REQUEST_ATTRIBUTE,
                true,
            );
        }

        return $this->fileBuilder->buildItem($file, versionCount: 1);
    }

    private function resolveFolder(string $folderId, BandSpace $bandSpace): BandSpaceFolder
    {
        $folder = $this->folderRepository->find($folderId);
        if ($folder === null || $folder->bandSpace->id !== $bandSpace->id) {
            throw new BadRequestHttpException('Dossier introuvable dans ce Band Space');
        }

        return $folder;
    }

    /**
     * @param string[] $tagIds
     *
     * @return BandSpaceFileTag[]
     */
    private function resolveTags(array $tagIds, BandSpace $bandSpace): array
    {
        $tags = $this->tagRepository->findBy(['id' => $tagIds, 'bandSpace' => $bandSpace]);
        if (count($tags) !== count(array_unique($tagIds))) {
            throw new BadRequestHttpException('Une ou plusieurs étiquettes sont invalides pour ce Band Space');
        }

        return $tags;
    }
}
