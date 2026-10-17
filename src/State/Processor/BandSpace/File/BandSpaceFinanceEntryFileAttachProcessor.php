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
use App\Enum\BandSpace\FinanceEntryScope;
use App\Exception\TranslatableHttpException;
use App\Repository\BandSpace\BandSpaceFileTagRepository;
use App\Repository\BandSpace\BandSpaceFolderRepository;
use App\Repository\BandSpace\FinanceEntryRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\EventListener\BandSpaceFileQuotaApproachingHeaderListener;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\BandSpaceChangeSignal;
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
readonly class BandSpaceFinanceEntryFileAttachProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private FinanceEntryRepository $financeEntryRepository,
        private BandSpaceFolderRepository $folderRepository,
        private BandSpaceFileTagRepository $tagRepository,
        private BandSpaceFileQuotaService $quotaService,
        private BandSpaceActivityRecorder $activityRecorder,
        private BandSpaceFileBuilder $fileBuilder,
        private Security $security,
        private RequestStack $requestStack,
        private BandSpaceChangeSignal $changeSignal,
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

        $entry = $this->financeEntryRepository->findOneByIdAndBandSpace((string) $uriVariables['entryId'], $bandSpace);
        if (!$entry instanceof \App\Entity\BandSpace\FinanceEntry) {
            throw new NotFoundHttpException('band_space.finance.entry.not_found');
        }

        if ($entry->scope === FinanceEntryScope::Personal && $entry->member?->user->id !== $user->id) {
            throw new AccessDeniedHttpException('band_space.finance.entry.update_not_owner');
        }

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
        $attachment->sourceType = 'finance';
        $attachment->sourceId = Uuid::fromString((string) $entry->id);
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

        $this->activityRecorder->record(
            $bandSpace,
            BandSpaceModule::File,
            BandSpaceFileActivityType::Attached,
            resourceId: (string) $file->id,
            actor: $user,
            payload: [
                'source_type' => 'finance',
                'source_id' => (string) $entry->id,
                'source_label' => $entry->labelVisibleToBand(),
            ],
        );

        // A personal entry stays between its owner and the server, so the band hears nothing.
        if ($entry->scope !== FinanceEntryScope::Personal) {
            $this->changeSignal->changed($bandSpace, BandSpaceModule::Finance);
        }

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
