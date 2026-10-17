<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileResource;
use App\ApiResource\BandSpace\File\BandSpaceFileUpload;
use App\Entity\BandSpace\BandSpaceFile;
use App\Entity\BandSpace\BandSpaceFileAttachment;
use App\Entity\BandSpace\BandSpaceFileVersion;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\EventListener\BandSpaceFileQuotaApproachingHeaderListener;
use App\Exception\TranslatableHttpException;
use App\Repository\BandSpace\BandSpaceFolderRepository;
use App\Repository\BandSpace\BandSpaceFileTagRepository;
use App\Repository\BandSpace\SetlistRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Security\BandSpace\SetlistWriteGuard;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\File\BandSpaceFileMimeAllowlist;
use App\Service\BandSpace\File\BandSpaceFileQuotaService;
use App\Service\Builder\BandSpace\File\BandSpaceFileBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<BandSpaceFileUpload, BandSpaceFileResource>
 */
readonly class BandSpaceSetlistFileAttachProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private SetlistWriteGuard $setlistWriteGuard,
        private SetlistRepository $setlistRepository,
        private BandSpaceFolderRepository $folderRepository,
        private BandSpaceFileTagRepository $tagRepository,
        private BandSpaceFileQuotaService $quotaService,
        private BandSpaceActivityRecorder $activityRecorder,
        private BandSpaceFileBuilder $fileBuilder,
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

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $setlist = $this->setlistRepository->findOneByIdAndBandSpace((string) $uriVariables['setlistId'], $bandSpace);
        if (!$setlist instanceof \App\Entity\BandSpace\Setlist) {
            throw new NotFoundHttpException('band_space.setlist.not_found');
        }

        $this->setlistWriteGuard->assertWritable($setlist);

        $upload = $data->uploadedFile;
        if ($upload === null) {
            throw new BadRequestHttpException('band_space.file.not_provided');
        }

        $mimeType = $upload->getMimeType() ?? 'application/octet-stream';
        if (!BandSpaceFileMimeAllowlist::isAllowed($mimeType)) {
            throw new TranslatableHttpException(
                Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
                'band_space.file.type_not_allowed',
                ['type' => $mimeType],
            );
        }

        $size = $upload->getSize();
        if ($size === false) {
            throw new BadRequestHttpException('band_space.file.size_invalid');
        }

        $this->quotaService->assertCanUpload($bandSpace, $size);

        $originalName = $upload instanceof UploadedFile ? $upload->getClientOriginalName() : $upload->getFilename();

        $folder = null;
        if ($data->folderId !== null && $data->folderId !== '') {
            $folder = $this->folderRepository->find($data->folderId);
            if ($folder === null || $folder->bandSpace->id !== $bandSpace->id) {
                throw new BadRequestHttpException('band_space.folder.not_found_in_space');
            }
        }

        $tags = [];
        if (count($data->tagIds) > 0) {
            $tags = $this->tagRepository->findBy(['id' => $data->tagIds, 'bandSpace' => $bandSpace]);
            if (count($tags) !== count(array_unique($data->tagIds))) {
                throw new BadRequestHttpException('band_space.tag.invalid_in_space');
            }
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

        $attachment = new BandSpaceFileAttachment();
        $attachment->bandSpaceFile = $file;
        $attachment->sourceType = 'setlist';
        $attachment->sourceId = Uuid::fromString((string) $setlist->id);
        $attachment->attachedBy = $user;

        $this->entityManager->persist($file);
        $this->entityManager->persist($version);
        $this->entityManager->persist($attachment);
        $this->entityManager->flush();

        $file->currentVersion = $version;

        $this->activityRecorder->record(
            $bandSpace,
            BandSpaceModule::File,
            BandSpaceFileActivityType::Uploaded,
            resourceId: (string) $file->id,
            actor: $user,
            payload: [
                'original_name' => $file->originalName,
                'size' => $size,
                'mime_type' => $mimeType,
            ],
        );

        // File feed: "this file was attached to setlist X" - payload describes the SOURCE.
        $this->activityRecorder->record(
            $bandSpace,
            BandSpaceModule::File,
            BandSpaceFileActivityType::Attached,
            resourceId: (string) $file->id,
            actor: $user,
            payload: [
                'source_type' => 'setlist',
                'source_id' => (string) $setlist->id,
                'source_label' => $setlist->name,
            ],
        );

        // Setlist feed: "a file was attached to this setlist" - payload describes the FILE.
        $this->activityRecorder->record(
            $bandSpace,
            BandSpaceModule::Setlist,
            BandSpaceSetlistActivityType::SetlistFileAttached,
            resourceId: (string) $setlist->id,
            actor: $user,
            payload: [
                'file_id' => (string) $file->id,
                'original_name' => $file->originalName,
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
}
