<?php declare(strict_types=1);

namespace App\Procedure\BandSpace;

use App\Entity\BandSpace\TechRiderItem;
use App\Entity\BandSpace\TechRiderPatchRow;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceRiderActivityType;
use App\Enum\BandSpace\TechRiderColour;
use App\Enum\BandSpace\TechRiderPatchDirection;
use App\Repository\BandSpace\TechRiderPatchRowRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

readonly class TechRiderPatchListReplaceProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TechRiderPatchRowRepository $patchRowRepository,
        private BandSpaceActivityRecorder $activityRecorder,
    ) {
    }

    /**
     * Swaps the item's rows for the ones given, and records the save, in one transaction.
     *
     * The transaction is the point. Without it the bulk delete commits on its own, and an insert
     * failing afterwards would leave the band with no patch list at all rather than the one they
     * started with. The payload is already known to be valid by the time this runs, so a failure
     * here means something infrastructural, which is exactly what a rollback is for.
     *
     * The activity row and the timestamp are written inside the same transaction rather than by
     * the caller afterwards. A second flush outside it could fail on its own and leave the grid
     * durably replaced with nothing in the feed to say who did it. Same shape as TaskMoveProcedure.
     *
     * @param array<array-key, mixed> $inputs
     * @param array<array-key, mixed> $outputs
     */
    public function replace(TechRiderItem $item, array $inputs, array $outputs, User $actor): void
    {
        // No flush inside: wrapInTransaction flushes for us once the callback returns, and
        // commits only if that succeeded.
        $this->entityManager->wrapInTransaction(function () use ($item, $inputs, $outputs, $actor): void {
            $this->patchRowRepository->deleteByItem($item);

            $rows = [
                ...$this->buildRows($item, $inputs, TechRiderPatchDirection::Input),
                ...$this->buildRows($item, $outputs, TechRiderPatchDirection::Output),
            ];

            // The rider is loaded with its rows fetch joined, so this collection still holds the
            // ones the bulk delete just removed. Resetting it is what stops the builder
            // serialising deleted rows back to the client. Safe to clear only because the
            // association has no orphanRemoval, which would turn this into a second delete of
            // every row. TechRiderItem::$patchRows records that.
            $item->patchRows->clear();
            foreach ($rows as $row) {
                $this->entityManager->persist($row);
                $item->patchRows->add($row);
            }

            $item->updateDatetime = new DateTime();

            $techRider = $item->techRider;
            // An explicit save of a whole grid, not an autosave, so no coalescing: every one of
            // these is a deliberate act somebody may need to trace.
            $this->activityRecorder->record(
                bandSpace: $techRider->bandSpace,
                module: BandSpaceModule::Rider,
                type: BandSpaceRiderActivityType::RiderPatchListUpdated,
                resourceId: (string) $item->id,
                actor: $actor,
                payload: [
                    'rider_name' => $techRider->name,
                    'title' => $item->title,
                    'input_count' => count($inputs),
                    'output_count' => count($outputs),
                ],
            );
        });
    }

    /**
     * @param array<array-key, mixed> $payloadRows
     * @return list<TechRiderPatchRow>
     */
    private function buildRows(TechRiderItem $item, array $payloadRows, TechRiderPatchDirection $direction): array
    {
        $rows = [];
        // array_values, so position always comes from the order rows appear in. A JSON object
        // with numeric keys deserialises into this property just as an array does, and reading
        // the keys would let a client set positions it is not supposed to control.
        foreach (array_values($payloadRows) as $position => $payloadRow) {
            /** @var array{channel: int, name?: string|null, microphone?: string|null, routing?: string|null, colour?: string|null} $payloadRow */
            $row = new TechRiderPatchRow();
            $row->item = $item;
            $row->direction = $direction;
            $row->channel = $payloadRow['channel'];
            $row->name = $this->trimToNull($payloadRow['name'] ?? null);
            $row->microphone = $this->trimToNull($payloadRow['microphone'] ?? null);
            $row->routing = $this->trimToNull($payloadRow['routing'] ?? null);
            $row->colour = isset($payloadRow['colour']) ? TechRiderColour::from($payloadRow['colour']) : null;
            $row->position = $position;

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * An empty cell in the grid arrives as "" and means the field was left blank, which is the
     * same thing as null. Storing both would make "has a microphone" two checks.
     */
    private function trimToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
