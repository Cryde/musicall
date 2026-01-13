<?php

declare(strict_types=1);

namespace App\State\Processor\Musician;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Musician\MusicianProfileMedia;
use App\ApiResource\Musician\Profile\Media;
use App\Entity\User;
use App\Service\Builder\Musician\MusicianProfileMediaResourceBuilder;
use App\Service\Procedure\Musician\MusicianProfileMediaCreateProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<Media, MusicianProfileMedia>
 */
readonly class MusicianProfileMediaCreateProcessor implements ProcessorInterface
{


    public function __construct(
        private Security $security,
        private MusicianProfileMediaCreateProcedure $mediaCreateProcedure,
        private MusicianProfileMediaResourceBuilder $musicianProfileMediaResourceBuilder,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MusicianProfileMedia
    {
        /** @var Media $data */
        /** @var User $user */
        $user = $this->security->getUser();
        if (!$profile = $user->getMusicianProfile()) {
            throw new NotFoundHttpException('Profil musicien non trouvé');
        }

        $media = $this->mediaCreateProcedure->handle($data, $profile);

        return $this->musicianProfileMediaResourceBuilder->buildFromEntity($media);
    }
}
