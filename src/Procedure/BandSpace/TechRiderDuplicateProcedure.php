<?php declare(strict_types=1);

namespace App\Procedure\BandSpace;

use App\Entity\BandSpace\TechRider;
use App\Entity\BandSpace\TechRiderItem;
use App\Entity\BandSpace\TechRiderPatchRow;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceRiderActivityType;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use Doctrine\ORM\EntityManagerInterface;

readonly class TechRiderDuplicateProcedure
{
    public const string NAME_SUFFIX = ' (copie)';

    /** Matches the column length on TechRider::$name. */
    public const int MAX_NAME_LENGTH = 255;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceActivityRecorder $activityRecorder,
    ) {
    }

    /**
     * Deep copies a rider, in one transaction.
     *
     * Deep is the whole point. A shallow copy that shared rows would not be a missing feature, it
     * would be data corruption: editing next year's patch list would silently rewrite last year's,
     * and the band would not find out until they looked at the archived document.
     *
     * A rider with seven items and 128 patch rows is 136 inserts, so the graph is built first and
     * flushed once rather than per entity.
     */
    public function duplicate(TechRider $source, ?string $name, User $actor): TechRider
    {
        return $this->entityManager->wrapInTransaction(function () use ($source, $name, $actor): TechRider {
            $copy = new TechRider();
            $copy->bandSpace = $source->bandSpace;
            $copy->name = $name ?? $this->defaultName($source->name);
            // The person duplicating, not the original author: they are the one who made this rider.
            $copy->createdBy = $actor;
            // Always live, even from an archived source. Starting next year's rider from last
            // year's archived one is the main reason this endpoint exists, so producing another
            // archived rider would defeat it.
            $copy->archiveDatetime = null;

            $this->entityManager->persist($copy);

            foreach ($source->items as $sourceItem) {
                $this->entityManager->persist($this->copyItem($sourceItem, $copy));
            }

            $this->activityRecorder->record(
                bandSpace: $source->bandSpace,
                module: BandSpaceModule::Rider,
                type: BandSpaceRiderActivityType::RiderDuplicated,
                resourceId: (string) $copy->id,
                actor: $actor,
                payload: [
                    'name' => $copy->name,
                    'source_name' => $source->name,
                    'source_id' => (string) $source->id,
                ],
            );

            return $copy;
        });
    }

    /**
     * What a copy means differs per item type, and each answer here is deliberate:
     *
     * - `content` carries the body of a Text, Contacts or StagePlot item. PHP arrays are value
     *   types, so this assignment is already a copy; there is no shared reference to worry about
     *   once it is written back to its own JSON column.
     * - `file` copies the **reference**, never the file. A rider's document item points at
     *   something already in the band's files, so duplicating a rider must not duplicate a 40MB
     *   PDF, must not touch the storage quota, and must leave one copy of the diagram to keep
     *   current.
     * - `patchRows` are real rows in their own table, so they are the one part that genuinely has
     *   to be recreated.
     *
     * A Contacts item needs nothing beyond its content: it holds no member data at all and renders
     * from the roster on every read, so the copy is correct the moment it exists.
     */
    private function copyItem(TechRiderItem $source, TechRider $rider): TechRiderItem
    {
        $copy = new TechRiderItem();
        $copy->techRider = $rider;
        $copy->type = $source->type;
        $copy->title = $source->title;
        $copy->isIncluded = $source->isIncluded;
        $copy->content = $source->content;
        $copy->file = $source->file;
        $copy->position = $source->position;

        $rider->items->add($copy);

        foreach ($source->patchRows as $sourceRow) {
            $rowCopy = new TechRiderPatchRow();
            $rowCopy->item = $copy;
            $rowCopy->direction = $sourceRow->direction;
            $rowCopy->channel = $sourceRow->channel;
            $rowCopy->name = $sourceRow->name;
            $rowCopy->microphone = $sourceRow->microphone;
            $rowCopy->routing = $sourceRow->routing;
            $rowCopy->colour = $sourceRow->colour;
            $rowCopy->position = $sourceRow->position;

            $this->entityManager->persist($rowCopy);
            $copy->patchRows->add($rowCopy);
        }

        return $copy;
    }

    /**
     * The source name is trimmed rather than the whole result, so the suffix survives: "(copie)"
     * is the part that tells a reader which of two identically named riders is the new one.
     */
    private function defaultName(string $sourceName): string
    {
        $available = self::MAX_NAME_LENGTH - mb_strlen(self::NAME_SUFFIX);

        return mb_substr($sourceName, 0, $available) . self::NAME_SUFFIX;
    }
}
