<?php

namespace App\Controller\Api\User;

use App\Entity\Image\UserProfilePicture;
use App\Entity\User;
use App\Exception\NoMatchedUserAccountException;
use App\Form\ImageUploaderType;
use App\Model\ChangePasswordModel;
use App\Model\ResetPasswordModel;
use App\Repository\UserRepository;
use App\Serializer\User\UserArraySerializer;
use App\Service\Jsonizer;
use App\Service\User\ResetPassword;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $userPasswordHasher;
    private ValidatorInterface $validator;
    private SerializerInterface $serializer;

    public function __construct(
        EntityManagerInterface      $entityManager,
        UserPasswordHasherInterface $userPasswordHasher,
        ValidatorInterface          $validator,
        SerializerInterface         $serializer
    ) {
        $this->entityManager = $entityManager;
        $this->userPasswordHasher = $userPasswordHasher;
        $this->validator = $validator;
        $this->serializer = $serializer;
    }

    /**
     * @Route("/api/users/change-password", name="api_user_change_password", methods={"POST"}, options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     */
    public function changePassword(
        Request $request,
        #[CurrentUser] $user
    ): JsonResponse {
        /** @var ChangePasswordModel $changePasswordModel */
        $changePasswordModel = $this->serializer->deserialize($request->getContent(), ChangePasswordModel::class, 'json');
        $errors = $this->validator->validate($changePasswordModel);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        if (!$this->userPasswordHasher->isPasswordValid($user, $changePasswordModel->getOldPassword())) {
            return $this->json(['L\'ancien mot de passe est invalide'], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($this->userPasswordHasher->hashPassword($user, $changePasswordModel->getNewPassword()));
        $this->entityManager->flush();

        return $this->json([]);
    }

    /**
     * @Route("/api/users/request-reset-password", name="api_user_request_reset_password", methods={"POST"}, options={"expose": true}, format="json")
     *
     * @throws NonUniqueResultException
     */
    public function requestResetPassword(
        Request       $request,
        Jsonizer      $jsonizer,
        ResetPassword $resetPassword
    ): JsonResponse {
        $data = $jsonizer->decodeRequest($request);

        $resetPassword->resetPasswordByLogin($data['login']);

        return $this->json(['data' => ['success' => 1]]);
    }

    /**
     * @Route("/api/users/reset-password/{token}", name="api_user_reset_password", methods={"POST"}, options={"expose": true}, defaults={"_format": "json"})
     */
    public function resetTokenPassword(
        string $token,
        Request $request,
        UserRepository $userRepository
    ): JsonResponse {
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

    /**
     * @Route("/api/users/me", name="api_user_get", methods={"GET"}, options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     */
    public function show(UserArraySerializer $userArraySerializer, #[CurrentUser] $user): JsonResponse
    {
        return $this->json($userArraySerializer->toArray($user, true));
    }

    /**
     * @Route("/api/users/picture", name="api_user_picture", methods={"POST"}, options={"expose" = true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     */
    public function changePicture(
        Request             $request,
        UserArraySerializer $userArraySerializer,
        #[CurrentUser] $user
    ): JsonResponse {
        $previousProfilePicture = $user->getProfilePicture() ? $user->getProfilePicture() : null;
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
