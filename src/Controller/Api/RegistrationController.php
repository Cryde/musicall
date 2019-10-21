<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\Jsonizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegistrationController extends AbstractController
{
    /**
     * @Route("/api/register", name="api_register", options={"expose": true})
     *
     * @param Request                      $request
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @param Jsonizer                     $jsonizer
     * @param ValidatorInterface           $validator
     *
     * @return Response
     */
    public function register(
        Request $request,
        UserPasswordEncoderInterface $passwordEncoder,
        Jsonizer $jsonizer,
        ValidatorInterface $validator
    ): Response {

        $data = $jsonizer->decodeRequest($request);

        $user = (new User())
            ->setUsername($data['username'])
            ->setEmail($data['email']);

        $errors = $validator->validate($user);

        if (count($errors) > 0) {
            return $this->json(['data' => ['errors' => $errors]], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // encode the plain password
        $user->setPassword(
            $passwordEncoder->encodePassword(
                $user,
                $data['password']
            )
        );

        $this->getDoctrine()->getManager()->persist($user);
        $this->getDoctrine()->getManager()->flush();

        // do anything else you need here, like send an email
        return $this->json(['data' => ['success' => 1]]);
    }
}
