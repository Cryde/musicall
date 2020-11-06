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
use Doctrine\ORM\NonUniqueResultException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserController extends AbstractController
{
    /**
     * @Route("/api/users/change-password", name="api_user_change_password", methods={"POST"}, options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Request                      $request
     * @param SerializerInterface          $serializer
     * @param ValidatorInterface           $validator
     * @param UserPasswordEncoderInterface $userPasswordEncoder
     *
     * @return JsonResponse
     */
    public function changePassword(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UserPasswordEncoderInterface $userPasswordEncoder
    ) {
        /** @var ChangePasswordModel $changePasswordModel */
        $changePasswordModel = $serializer->deserialize($request->getContent(), ChangePasswordModel::class, 'json');
        $errors = $validator->validate($changePasswordModel);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();

        if (!$userPasswordEncoder->isPasswordValid($user, $changePasswordModel->getOldPassword())) {
            return $this->json(['L\'ancien mot de passe est invalide'], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($userPasswordEncoder->encodePassword($user, $changePasswordModel->getNewPassword()));
        $this->getDoctrine()->getManager()->flush();

        return $this->json([]);
    }

    /**
     * @Route("/api/users/request-reset-password", name="api_user_request_reset_password", methods={"POST"}, options={"expose": true}, format="json")
     *
     * @param Request       $request
     * @param Jsonizer      $jsonizer
     * @param ResetPassword $resetPassword
     *
     * @return JsonResponse
     * @throws NoMatchedUserAccountException
     * @throws NonUniqueResultException
     */
    public function requestResetPassword(Request $request, Jsonizer $jsonizer, ResetPassword $resetPassword)
    {
        $data = $jsonizer->decodeRequest($request);

        $resetPassword->resetPasswordByLogin($data['login']);

        return $this->json(['data' => ['success' => 1]]);
    }

    /**
     * @Route("/api/users/reset-password/{token}", name="api_user_reset_password", methods={"POST"}, options={"expose": true}, defaults={"_format": "json"})
     *
     * @param string                       $token
     * @param Request                      $request
     * @param UserRepository               $userRepository
     * @param SerializerInterface          $serializer
     * @param ValidatorInterface           $validator
     * @param UserPasswordEncoderInterface $userPasswordEncoder
     *
     * @return JsonResponse
     */
    public function resetTokenPassword(
        string $token,
        Request $request,
        UserRepository $userRepository,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UserPasswordEncoderInterface $userPasswordEncoder
    ) {
        // @todo : add temporal validation on the token
        if (!$user = $userRepository->findOneBy(['token' => $token])) {
            return $this->json(['message' => 'Ce lien n\'est plus valable'], Response::HTTP_BAD_REQUEST);
        }

        /** @var ResetPasswordModel $resetPasswordModel */
        $resetPasswordModel = $serializer->deserialize($request->getContent(), ResetPasswordModel::class, 'json');
        $errors = $validator->validate($resetPasswordModel);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($userPasswordEncoder->encodePassword($user, $resetPasswordModel->getPassword()));
        $user->setToken(null);
        $this->getDoctrine()->getManager()->flush();

        return $this->json([]);
    }

    /**
     * @Route("/api/users/me", name="api_user_get", methods={"GET"}, options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param UserArraySerializer $userArraySerializer
     *
     * @return JsonResponse
     */
    public function show(UserArraySerializer $userArraySerializer)
    {
        return $this->json($userArraySerializer->toArray($this->getUser(), true));
    }

    /**
     * @Route("/api/users/picture", name="api_user_picture", methods={"POST"}, options={"expose" = true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     */
    public function changePicture(Request $request, UserArraySerializer $userArraySerializer)
    {
        /** @var User $user */
        $user = $this->getUser();
        $previousProfilePicture = $user->getProfilePicture() ? $user->getProfilePicture() : null;
        $profilePicture = new UserProfilePicture();

        $form = $this->createForm(ImageUploaderType::class, $profilePicture);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($previousProfilePicture) {
                $user->setProfilePicture(null);
                $this->getDoctrine()->getManager()->flush();
                $this->getDoctrine()->getManager()->remove($previousProfilePicture);
                $this->getDoctrine()->getManager()->flush();
            }

            $user->setProfilePicture($profilePicture);
            $profilePicture->setUser($user);

            $this->getDoctrine()->getManager()->flush();

            return $this->json($userArraySerializer->toArray($user, true));
        }

        return $this->json($form->getErrors(true, true), Response::HTTP_BAD_REQUEST);
    }
}
