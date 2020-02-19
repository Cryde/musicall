<?php

namespace App\Controller\Api;

use App\Model\ChangePasswordModel;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
}
