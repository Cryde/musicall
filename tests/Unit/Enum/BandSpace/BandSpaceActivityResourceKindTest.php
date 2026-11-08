<?php declare(strict_types=1);

namespace App\Tests\Unit\Enum\BandSpace;

use App\Enum\BandSpace\BandSpaceAgendaActivityType;
use App\Enum\BandSpace\BandSpaceFileActivityType;
use App\Enum\BandSpace\BandSpaceFinanceActivityType;
use App\Enum\BandSpace\BandSpaceFolderActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceNoteActivityType;
use App\Enum\BandSpace\BandSpaceRiderActivityType;
use App\Enum\BandSpace\BandSpaceSetlistActivityType;
use App\Enum\BandSpace\BandSpaceSettingsActivityType;
use App\Enum\BandSpace\BandSpaceTaskActivityType;
use PHPUnit\Framework\TestCase;

/**
 * The server and assets/js/utils/bandSpaceActivityLink.js must agree on which activity types name an
 * item a screen opens (#1354). Both are checked against the same fixture.
 */
class BandSpaceActivityResourceKindTest extends TestCase
{
    public function test_every_activity_type_names_the_same_kind_of_item_as_the_front_end(): void
    {
        $links = json_decode(
            (string) file_get_contents(__DIR__ . '/../../../Fixtures/BandSpaceActivity/links.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $types = [
            BandSpaceModule::Task->value => BandSpaceTaskActivityType::cases(),
            BandSpaceModule::Notes->value => BandSpaceNoteActivityType::cases(),
            BandSpaceModule::Agenda->value => BandSpaceAgendaActivityType::cases(),
            BandSpaceModule::Finance->value => BandSpaceFinanceActivityType::cases(),
            BandSpaceModule::File->value => [...BandSpaceFileActivityType::cases(), ...BandSpaceFolderActivityType::cases()],
            BandSpaceModule::Setlist->value => BandSpaceSetlistActivityType::cases(),
            BandSpaceModule::Rider->value => BandSpaceRiderActivityType::cases(),
            BandSpaceModule::Settings->value => BandSpaceSettingsActivityType::cases(),
        ];

        $expected = [];
        $actual = [];
        foreach ($types as $module => $cases) {
            foreach ($cases as $case) {
                $expected[$module . '.' . $case->value] = array_key_exists($case->value, $links[$module])
                    ? $links[$module][$case->value]
                    : 'missing from links.json';
                // Folder and settings lines never name an item a screen opens, so those enums declare nothing.
                $actual[$module . '.' . $case->value] = method_exists($case, 'resourceKind') ? $case->resourceKind()?->value : null;
            }
        }

        $this->assertSame($expected, $actual);
    }
}
