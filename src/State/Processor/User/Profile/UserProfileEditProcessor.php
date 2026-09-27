<?php

declare(strict_types=1);

namespace App\State\Processor\User\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Profile\UserProfileEdit;
use App\Entity\User;
use App\Entity\User\UserProfile;
use App\State\Provider\User\Profile\UserProfileEditProvider;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<UserProfileEdit, UserProfileEdit>
 */
readonly class UserProfileEditProcessor implements ProcessorInterface
{
    private const string PICK_A_CITY = 'Choisissez une ville dans la liste pour enregistrer sa position';

    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private UserProfileEditProvider $userProfileEditProvider,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserProfileEdit
    {
        /** @var UserProfileEdit $data */
        /** @var User $user */
        $user = $this->security->getUser();
        $profile = $user->profile;

        $profile->displayName = $data->displayName;
        $profile->bio = $data->bio;
        [$profile->latitude, $profile->longitude] = $this->resolveCoordinates($data, $profile);
        $profile->location = $data->location;
        $profile->isPublic = $data->isPublic;
        $profile->updateDatetime = new DateTimeImmutable();

        $this->entityManager->flush();

        return $this->userProfileEditProvider->provide($operation, $uriVariables, $context);
    }

    /**
     * Checked against the stored profile rather than on the DTO, which the read fills in for every
     * field the merge patch leaves out, so only here can "not sent" be told from "sent unchanged".
     *
     * @return array{?float, ?float}
     */
    private function resolveCoordinates(UserProfileEdit $data, UserProfile $profile): array
    {
        $isLatitudeChanged = $data->latitude !== $profile->latitude;
        $isLongitudeChanged = $data->longitude !== $profile->longitude;
        // A picked city moves both at once: one coordinate alone would pair with the old city's other.
        if ($isLatitudeChanged !== $isLongitudeChanged) {
            throw new UnprocessableEntityHttpException(self::PICK_A_CITY);
        }

        if (!$isLatitudeChanged) {
            // Retyped or cleared without picking a city: the old coordinates no longer describe it.
            return $data->location === $profile->location ? [$profile->latitude, $profile->longitude] : [null, null];
        }

        if ($data->latitude !== null && trim((string) $data->location) === '') {
            throw new UnprocessableEntityHttpException(self::PICK_A_CITY);
        }

        return [$data->latitude, $data->longitude];
    }
}
