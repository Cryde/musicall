<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Setlist;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Setlist\SetlistResource;
use App\Entity\BandSpace\BandSpaceFileAttachment;
use App\Entity\BandSpace\Setlist;
use App\Entity\BandSpace\SetlistItem;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\SetlistRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\SetlistBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, SetlistResource>
 */
readonly class SetlistDuplicateProcessor implements ProcessorInterface
{
    private const string ATTACHMENT_SOURCE_TYPE = 'setlist';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private SetlistRepository $setlistRepository,
        private BandSpaceFileAttachmentRepository $attachmentRepository,
        private BandSpaceActivityRecorder $activityRecorder,
        private SetlistBuilder $setlistBuilder,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SetlistResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        // Deliberately not guarded by SetlistWriteGuard: an archived setlist is a valid source and
        // duplicating it does not touch it. It is also the only way back out of the archive while
        // there is no restore (#761), so refusing it here would strand the work for good.
        $source = $this->setlistRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$source instanceof Setlist) {
            throw new NotFoundHttpException('Setlist introuvable');
        }

        $copy = new Setlist();
        $copy->bandSpace = $bandSpace;
        $copy->name = $source->name . ' (copie)';

        $this->entityManager->persist($copy);

        foreach ($source->items as $sourceItem) {
            $itemCopy = new SetlistItem();
            $itemCopy->setlist = $copy;
            $itemCopy->type = $sourceItem->type;
            $itemCopy->song = $sourceItem->song;
            $itemCopy->label = $sourceItem->label;
            $itemCopy->durationOverride = $sourceItem->durationOverride;
            $itemCopy->note = $sourceItem->note;
            $itemCopy->transition = $sourceItem->transition;
            $itemCopy->position = $sourceItem->position;

            $this->entityManager->persist($itemCopy);
            $copy->items->add($itemCopy);
        }

        $this->copyFileAttachments($source, $copy, $user);

        $this->activityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Setlist,
            type: BandSpaceSetlistActivityType::SetlistDuplicated,
            resourceId: (string) $copy->id,
            actor: $user,
            payload: ['name' => $copy->name, 'source_id' => (string) $source->id],
        );

        $this->entityManager->flush();

        return $this->setlistBuilder->buildItem($copy);
    }

    /**
     * Points the copy at the same files as the source.
     *
     * An attachment is keyed by (sourceType, sourceId), so nothing follows a copy on its own: the
     * lyric sheets and backing tracks of the gig would silently vanish from a list duplicated for
     * the next one, which is the whole reason this endpoint exists. Only the attachment rows are
     * cloned, never the files themselves, so this costs no storage and no quota.
     *
     * Attachments of the items are NOT cloned: an item points at a shared Song row, and song
     * attachments hang on that song id, so the copy already reaches them through the same song.
     * Cloning them would mean writing a second (song, same id) row, which the unique index refuses.
     */
    private function copyFileAttachments(Setlist $source, Setlist $copy, User $user): void
    {
        $sourceAttachments = $this->attachmentRepository->findActiveBySource(
            self::ATTACHMENT_SOURCE_TYPE,
            (string) $source->id,
        );

        foreach ($sourceAttachments as $sourceAttachment) {
            $attachmentCopy = new BandSpaceFileAttachment();
            $attachmentCopy->bandSpaceFile = $sourceAttachment->bandSpaceFile;
            $attachmentCopy->sourceType = self::ATTACHMENT_SOURCE_TYPE;
            $attachmentCopy->sourceId = Uuid::fromString((string) $copy->id);
            // The duplicator, not whoever attached the original: this attachment was created now,
            // and attachedDatetime already says so.
            $attachmentCopy->attachedBy = $user;

            $this->entityManager->persist($attachmentCopy);
        }
    }
}
