<?php declare(strict_types=1);

namespace App\Tests\Unit\Procedure\BandSpace;

use App\Entity\BandSpace\TechRiderItem;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * The failure this guards against is a future item type bringing storage that the duplication then
 * silently drops. It is not hypothetical: a patch list keeps its rows in its own table, a document
 * keeps a file reference, a stage plot keeps a JSON document, and every one of those had to be
 * handled by hand in TechRiderDuplicateProcedure::copyItem.
 *
 * An exhaustive match on the item type would not catch it. Match exhaustiveness is over enum cases,
 * and the danger is a new *property*, not a new case; today every branch would also perform the
 * identical three operations, so the match would be duplication for no behavioural gain.
 *
 * So this enumerates the entity's own properties instead and fails on an unrecognised one, pointing
 * at the place a decision is owed. Same shape as BandSpaceWriteGuardCoverageTest, which greps
 * processors, and TechRiderStagePlotIconArtworkTest, which pairs enum cases with files on disk.
 */
class TechRiderItemCopyCoverageTest extends TestCase
{
    /**
     * Every property of TechRiderItem, and what duplication does with it. Adding a property means
     * adding a line here, which is the moment to decide what a copy of it means.
     *
     * @var array<string, string>
     */
    private const array COPY_DECISIONS = [
        // Identity and ownership: the copy gets its own.
        'id' => 'new, generated',
        'techRider' => 'points at the new rider',
        'creationDatetime' => 'now, set by the constructor',
        'updateDatetime' => 'null, the copy has not been edited',

        // Carried across verbatim.
        'type' => 'copied',
        'title' => 'copied',
        'isIncluded' => 'copied',
        'content' => 'copied by value, PHP arrays are value types',
        'position' => 'copied, so the composed order survives',

        // The two deliberate non-deep copies.
        'file' => 'the reference is shared, never the file: duplicating a rider must not duplicate '
            . 'a 40MB PDF, touch the quota, or leave two copies of a diagram to keep current',

        // The one part that genuinely has to be recreated.
        'patchRows' => 'recreated as new rows in their own table',
    ];

    public function test_every_property_of_an_item_has_a_duplication_decision(): void
    {
        $undecided = array_diff($this->propertyNames(), array_keys(self::COPY_DECISIONS));

        self::assertSame([], array_values($undecided), sprintf(
            "These properties of %s have no recorded duplication decision.\n"
            . "Decide what a copy of each one means, handle it in %s::copyItem(), and add it to\n"
            . "COPY_DECISIONS in %s.\n",
            TechRiderItem::class,
            'App\Procedure\BandSpace\TechRiderDuplicateProcedure',
            self::class,
        ));
    }

    /** The other direction, so a removed property does not leave a stale note claiming otherwise. */
    public function test_the_decision_list_has_no_stale_entries(): void
    {
        $stale = array_diff(array_keys(self::COPY_DECISIONS), $this->propertyNames());

        self::assertSame([], array_values($stale), 'These decisions name properties that no longer exist.');
    }

    /**
     * Guards against the reflection silently returning nothing, which would make the test above pass
     * vacuously.
     */
    public function test_the_reflection_actually_finds_the_properties(): void
    {
        self::assertGreaterThan(8, count($this->propertyNames()));
    }

    /**
     * @return list<string>
     */
    private function propertyNames(): array
    {
        return array_values(array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            array_filter(
                (new ReflectionClass(TechRiderItem::class))->getProperties(),
                // Static and virtual-only members are not state a copy has to carry.
                static function (ReflectionProperty $property): bool {
                    $type = $property->getType();

                    return !$property->isStatic()
                        && !($type instanceof ReflectionNamedType && $type->getName() === 'void');
                },
            ),
        ));
    }
}
