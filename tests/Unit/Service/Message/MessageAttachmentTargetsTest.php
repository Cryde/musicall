<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\Message;

use App\Enum\BandSpace\BandSpaceSearchResultType;
use App\Service\Message\MessageAttachmentTargets;
use PHPUnit\Framework\TestCase;

/**
 * The maps are the only thing that can tell the resolver where a polymorphic target lives, and the
 * attribute-argument rules make it impossible to derive one from the other. An eighth kind of record
 * added to the enum must not silently become unresolvable, so this is what fails instead.
 */
class MessageAttachmentTargetsTest extends TestCase
{
    public function test_every_search_result_type_has_an_entity_and_a_title(): void
    {
        $expected = array_column(BandSpaceSearchResultType::cases(), 'value');

        self::assertSame($expected, array_keys(MessageAttachmentTargets::ENTITY_BY_TYPE));
        self::assertSame($expected, array_keys(MessageAttachmentTargets::TITLE_PROPERTY_BY_TYPE));
    }

    public function test_every_entity_and_title_property_exists(): void
    {
        foreach (MessageAttachmentTargets::ENTITY_BY_TYPE as $type => $entityClass) {
            self::assertTrue(class_exists($entityClass), $entityClass . ' does not exist');
            self::assertTrue(
                property_exists($entityClass, MessageAttachmentTargets::TITLE_PROPERTY_BY_TYPE[$type]),
                sprintf('%s has no %s property', $entityClass, MessageAttachmentTargets::TITLE_PROPERTY_BY_TYPE[$type]),
            );
        }
    }
}
