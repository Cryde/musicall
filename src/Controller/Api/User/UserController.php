<?php

namespace App\Controller\Api\User;

use App\Entity\Image\UserProfilePicture;
use App\Form\ImageUploaderType;
use App\Model\ChangePasswordModel;
use App\Model\ResetPasswordModel;
use App\Repository\UserRepository;
use App\Serializer\User\UserArraySerializer;
use App\Service\Jsonizer;
use App\Service\User\ResetPassword;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface      $entityManager,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly ValidatorInterface          $validator,
        private readonly SerializerInterface         $serializer
    ) {
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route(path: '/api/users/request-reset-password', name: 'api_user_request_reset_password', options: ['expose' => true], methods: ['POST'], format: 'json')]
    public function requestResetPassword(
        Request       $request,
        Jsonizer      $jsonizer,
        ResetPassword $resetPassword
    ): JsonResponse {
        $data = $jsonizer->decodeRequest($request);
        $resetPassword->resetPasswordByLogin($data['login']);

        return $this->json(['data' => ['success' => 1]]);
    }

    #[Route(path: '/api/users/reset-password/{token}', name: 'api_user_reset_password', options: ['expose' => true], defaults: ['_format' => 'json'], methods: ['POST'])]
    public function resetTokenPassword(string $token, Request $request, UserRepository $userRepository): JsonResponse
    {
        // @todo : add temporal validation on the token
        if (!$user = $userRepository->findOneBy(['token' => $token])) {
            return $this->json(['message' => 'Ce lien n\'est plus valable'], Response::HTTP_BAD_REQUEST);
        }
        /** @var ResetPasswordModel $resetPasswordModel */
        $resetPasswordModel = $this->serializer->deserialize($request->getContent(), ResetPasswordModel::class, 'json');
        $errors = $this->validator->validate($resetPasswordModel);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $resetPasswordModel->getPassword()));
        $user->setToken(null);
        $this->entityManager->flush();

        return $this->json([]);
    }

    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    #[Route(path: '/api/users/me', name: 'api_user_get', options: ['expose' => true], methods: ['GET'])]
    public function show(UserArraySerializer $userArraySerializer, #[CurrentUser] $user): JsonResponse
    {
        return $this->json($userArraySerializer->toArray($user, true));
    }

    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    #[Route(path: '/api/users/picture', name: 'api_user_picture', options: ['expose' => true], methods: ['POST'])]
    public function changePicture(
        Request             $request,
        UserArraySerializer $userArraySerializer,
        #[CurrentUser]      $user
    ): JsonResponse {
        $previousProfilePicture = $user->getProfilePicture() ?: null;
        $profilePicture = new UserProfilePicture();
        $form = $this->createForm(ImageUploaderType::class, $profilePicture);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($previousProfilePicture) {
                $user->setProfilePicture(null);
                $this->entityManager->flush();
                $this->entityManager->remove($previousProfilePicture);
                $this->entityManager->flush();
            }
            $user->setProfilePicture($profilePicture);
            $profilePicture->setUser($user);
            $this->entityManager->flush();

            return $this->json($userArraySerializer->toArray($user, true));
        }

        return $this->json($form->getErrors(true, true), Response::HTTP_BAD_REQUEST);
    }
}
