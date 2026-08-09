<?php declare(strict_types=1);

namespace App\Tests\Unit\Privacy;

use App\Privacy\ActivityPayloadMask;
use PHPUnit\Framework\TestCase;

class ActivityPayloadMaskTest extends TestCase
{
    public function test_it_masks_the_email_and_leaves_the_rest_alone(): void
    {
        self::assertSame(
            ['email' => 'j***@gmail.com', 'invited_username' => 'johnny', 'invited_user_id' => null],
            ActivityPayloadMask::mask([
                'email' => 'john.doe@gmail.com',
                'invited_username' => 'johnny',
                'invited_user_id' => null,
            ]),
        );
    }

    public function test_a_payload_without_an_email_passes_through(): void
    {
        self::assertSame(['label' => 'Studio'], ActivityPayloadMask::mask(['label' => 'Studio']));
    }

    public function test_a_null_payload_stays_null(): void
    {
        self::assertNull(ActivityPayloadMask::mask(null));
    }

    /**
     * The column is JSON, so nothing stops a caller storing something other than a string under the key.
     * Masking must not turn that into a type error on a read path.
     */
    public function test_a_non_string_email_is_left_untouched(): void
    {
        self::assertSame(['email' => null], ActivityPayloadMask::mask(['email' => null]));
        self::assertSame(['email' => ['a@b.com']], ActivityPayloadMask::mask(['email' => ['a@b.com']]));
    }
}
