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
        $this->namesByBandSpace[$bandSpaceId] ??= $this->membershipRepository->findDisplayNamesByUserId($bandSpaceId);

        // No membership row is not expected (memberships are never hard deleted), so fall back to the
        // site wide name, which reads the profile only in that unexpected case.
        return $this->namesByBandSpace[$bandSpaceId][$user->id] ?? $user->publicName();
    }

    /** For the scalar projections that never hydrate a User, such as the chat page. */
    public function nameById(string $bandSpaceId, string $userId, string $username, bool $isDeleted): string
    {
        $this->namesByBandSpace[$bandSpaceId] ??= $this->membershipRepository->findDisplayNamesByUserId($bandSpaceId);

        // Same unexpected case, without a User to read a profile from: the handle is all there is.
        return $this->namesByBandSpace[$bandSpaceId][$userId]
            ?? User::publicNameFor($username, $isDeleted, null, false);
    }

    public function reset(): void
    {
        $this->namesByBandSpace = [];
    }
}
