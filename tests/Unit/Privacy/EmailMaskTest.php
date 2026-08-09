<?php declare(strict_types=1);

namespace App\Tests\Unit\Privacy;

use App\Privacy\EmailMask;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EmailMaskTest extends TestCase
{
    #[DataProvider('provideAddresses')]
    public function test_it_masks(string $email, string $expected): void
    {
        self::assertSame($expected, EmailMask::mask($email));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideAddresses(): iterable
    {
        yield 'keeps the initial and the domain' => ['john.doe@gmail.com', 'j***@gmail.com'];
        yield 'a one letter local part is indistinguishable from a long one' => ['j@gmail.com', 'j***@gmail.com'];
        yield 'accented initial survives' => ['élodie@musicall.fr', 'é***@musicall.fr'];
        yield 'case is left alone, masking is not normalising' => ['John@Example.COM', 'J***@Example.COM'];
        yield 'a plus tag is hidden with the rest' => ['john+bands@gmail.com', 'j***@gmail.com'];
        yield 'only the last at separates, the rest stays hidden' => ['a@b@example.com', 'a***@example.com'];
        yield 'no at, nothing shown' => ['notanemail', '***'];
        yield 'empty local part, nothing shown' => ['@gmail.com', '***'];
        yield 'empty domain, nothing shown' => ['john@', '***'];
        yield 'empty string' => ['', '***'];
        yield 'a lone at' => ['@', '***'];
    }

    public function test_the_mask_length_does_not_follow_the_local_part_length(): void
    {
        self::assertSame(
            EmailMask::mask('j@musicall.fr'),
            EmailMask::mask('jeremy.tonneau@musicall.fr'),
        );
    }
}
