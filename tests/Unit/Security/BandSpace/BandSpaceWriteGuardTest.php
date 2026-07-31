<?php declare(strict_types=1);

namespace App\Tests\Unit\Security\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Security\BandSpace\BandSpaceWriteGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BandSpaceWriteGuardTest extends TestCase
{
    public function test_a_space_pending_deletion_rejects_writes_with_a_conflict(): void
    {
        $bandSpace = new BandSpace();
        $bandSpace->deletionScheduledDatetime = new \DateTimeImmutable('+30 days');

        try {
            (new BandSpaceWriteGuard())->assertWritable($bandSpace);
            self::fail('Expected a ConflictHttpException');
        } catch (ConflictHttpException $exception) {
            self::assertSame(Response::HTTP_CONFLICT, $exception->getStatusCode());
            self::assertSame(
                'Cet espace est en attente de suppression, les modifications sont désactivées',
                $exception->getMessage(),
            );
        }
    }

    public function test_an_ordinary_space_is_writable(): void
    {
        $bandSpace = new BandSpace();

        (new BandSpaceWriteGuard())->assertWritable($bandSpace);

        self::assertFalse($bandSpace->isPendingDeletion());
    }

    /**
     * The purge command deletes a space once the due date has passed, but until it runs the space is still
     * there. A past due date must stay blocked rather than flip back to writable.
     */
    public function test_a_space_whose_due_date_has_passed_is_still_blocked(): void
    {
        $bandSpace = new BandSpace();
        $bandSpace->deletionScheduledDatetime = new \DateTimeImmutable('-1 day');

        $this->expectException(ConflictHttpException::class);

        (new BandSpaceWriteGuard())->assertWritable($bandSpace);
    }
}
