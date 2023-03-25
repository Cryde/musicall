<?php

namespace App\Controller\Api\Media\User;

use App\Entity\Image\UserProfilePicture;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsController]
class CreateUserProfilePictureAction extends AbstractController
{
    public function __construct(
        private readonly Security               $security,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(Request $request): UserProfilePicture
    {
        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile) {
            throw new BadRequestHttpException('"file" is required');
        }
        dump('mdr');
        /** @var User $user */
        $user = $this->security->getUser();
        $previousProfilePicture = $user->getProfilePicture() ?: null;
        if ($previousProfilePicture) {
            $user->setProfilePicture(null);
            $this->entityManager->flush();
            $this->entityManager->remove($previousProfilePicture);
            $this->entityManager->flush();
        }
        $userProfilePicture = new UserProfilePicture();
        $userProfilePicture->setImageFile($uploadedFile);
        $userProfilePicture->setUser($user);
        $user->setProfilePicture($userProfilePicture);

        return $userProfilePicture;
    }
}