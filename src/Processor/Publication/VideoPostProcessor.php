<?php

namespace App\Processor\Publication;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Model\Publication\Request\AddVideo;
use App\Procedure\Publication\PublicationVideoCreationProcedure;
use Symfony\Bundle\SecurityBundle\Security;

class VideoPostProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly PublicationVideoCreationProcedure $publicationVideoCreationProcedure,
        private readonly Security $security
    ) {
    }

    /**
     * @param AddVideo $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var User $user */
        $user = $this->security->getUser();

        return $this->publicationVideoCreationProcedure->process($data, $user);
    }
}