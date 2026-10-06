<?php declare(strict_types=1);

namespace App\Service\BandSpace;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Resolves what a user is called inside one band space (#1115), with one query per space and
 * request however many rows a payload names. Reset between requests so the FrankenPHP worker never
 * serves a stage name changed since.
 */
class BandSpaceMemberNames implements ResetInterface
{
    /** @var array<string, array<string, string>> band space id => user id => name */
    private array $namesByBandSpace = [];

    public function __construct(
        private readonly BandSpaceMembershipRepository $membershipRepository,
    ) {
    }

    public function nameOf(User $user, string $bandSpaceId): string
    {
        return $this->nameById($bandSpaceId, $user->id, $user->username, $user->isDeleted());
    }

    /** For the scalar projections that never hydrate a User, such as the chat page. */
    public function nameById(string $bandSpaceId, string $userId, string $username, bool $isDeleted): string
    {
        $this->namesByBandSpace[$bandSpaceId] ??= $this->membershipRepository->findDisplayNamesByUserId($bandSpaceId);

        // No membership row is not expected (memberships are never hard deleted), so fall back to the handle.
        return $this->namesByBandSpace[$bandSpaceId][$userId]
            ?? BandSpaceMembership::nameFor(null, $username, $isDeleted);
    }

    public function reset(): void
    {
        $this->namesByBandSpace = [];
    }
}
