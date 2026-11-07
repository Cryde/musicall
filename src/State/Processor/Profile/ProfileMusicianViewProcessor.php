<?php

declare(strict_types=1);

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Musician\MusicianProfile;
use App\Security\Profile\ProfileWallAccess;
use App\Service\Profile\ProfileViewTracker;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, null>
 */
readonly class ProfileMusicianViewProcessor implements ProcessorInterface
{
    public function __construct(
        private ProfileWallAccess $wallAccess,
        private ProfileViewTracker $viewTracker,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        // The profile's own rules: gone, suspended or private is a 404.
        $owner = $this->wallAccess->owner((string) $uriVariables['username']);
        if (!$owner->musicianProfile instanceof MusicianProfile) {
            throw new NotFoundHttpException('profile.musician.not_found');
        }

        $this->viewTracker->track($owner->musicianProfile, $owner);

        return null;
    }
}
