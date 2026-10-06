<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum\Report;

use App\Enum\Report\ReportTargetType;
use PHPUnit\Framework\TestCase;

class ReportTargetTypeTest extends TestCase
{
    public function test_a_uuid_is_lower_cased(): void
    {
        $this->assertSame('b6f1a6a0-0000-4000-8000-00000000abcd', ReportTargetType::User->canonicalId('B6F1A6A0-0000-4000-8000-00000000ABCD'));
    }

    public function test_an_integer_loses_its_leading_zeros(): void
    {
        $this->assertSame('42', ReportTargetType::Comment->canonicalId('0042'));
        $this->assertSame('7', ReportTargetType::Publication->canonicalId('7'));
    }

    /** An int cast would saturate onto a real id; left as is, it simply finds nothing. */
    public function test_an_oversized_integer_is_left_alone(): void
    {
        $this->assertSame('99999999999999999999', ReportTargetType::Comment->canonicalId('99999999999999999999'));
    }

    public function test_a_non_numeric_comment_id_is_left_alone(): void
    {
        $this->assertSame('abc', ReportTargetType::Comment->canonicalId('abc'));
    }

    public function test_a_private_conversation_has_no_label_and_every_other_type_has_one(): void
    {
        foreach (ReportTargetType::cases() as $type) {
            $this->assertSame($type === ReportTargetType::Message, $type->labelKey() === null, $type->value);
        }
    }
}
