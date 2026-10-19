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
use App\Exception\TranslatableHttpException;
use App\Repository\BandSpace\BandSpaceFileTagRepository;
use App\Repository\BandSpace\BandSpaceFolderRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\EventListener\BandSpaceFileQuotaApproachingHeaderListener;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\BandSpace\File\BandSpaceFileMimeAllowlist;
use App\Service\BandSpace\File\BandSpaceFileQuotaService;
use App\Service\BandSpace\File\BandSpaceFileSourceTypes;
use App\Service\Builder\BandSpace\File\BandSpaceFileBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @implements ProcessorInterface<BandSpaceFileUpload, BandSpaceFileResource>
 */
readonly class BandSpaceFileUploadProcessor implements ProcessorInterface
{
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
        #[Target('band_space_file_upload')]
        private RateLimiterFactoryInterface $uploadLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceFileResource
    {
        /** @var BandSpaceFileUpload $data */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $this->uploadLimiter->create($user->id)->consume()->ensureAccepted();

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

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
            $folder = $this->resolveFolder($data->folderId, $bandSpace);
        }

        $attachedSourceType = $data->attachedSourceType ?: null;
        $attachedSourceId = null;
        if ($attachedSourceType !== null) {
            if (!in_array($attachedSourceType, BandSpaceFileSourceTypes::ALL, true)) {
                throw new TranslatableHttpException(
                    Response::HTTP_BAD_REQUEST,
                    'band_space.file.attached_source_type_invalid',
                    ['type' => $attachedSourceType],
                );
            }
            $rawSourceId = $data->attachedSourceId;
            if ($rawSourceId === null || $rawSourceId === '') {
                throw new BadRequestHttpException('band_space.file.attached_source_id_required');
            }
            $violations = $this->validator->validate($rawSourceId, [new Assert\Uuid()]);
            if (count($violations) > 0) {
                throw new BadRequestHttpException('band_space.file.attached_source_id_invalid');
            }
            // Stored lower case, as every generated id is: the file card matches it with ===.
            $attachedSourceId = mb_strtolower($rawSourceId);
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
            $attachment->sourceId = $attachedSourceId;
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
            throw new BadRequestHttpException('band_space.folder.not_found_in_space');
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
            throw new BadRequestHttpException('band_space.tag.invalid_in_space');
        }

        return $tags;
    }
}
