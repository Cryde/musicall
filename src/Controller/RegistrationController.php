<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    /**
     * @Route("/register/confirm/{token}", name="app_register_confirm", options={"expose": true})
     *
     * @param string         $token
     * @param UserRepository $userRepository
     *
     * @return RedirectResponse|Response
     * @throws \Exception
     */
    public function confirm(string $token, UserRepository $userRepository)
    {
        if (!$user = $userRepository->findOneBy(['confirmationToken' => $token])) {
            return new Response('Ce token n\'existe pas/plus, il est possible que vous ayez déjà confirmé votre compte', Response::HTTP_NOT_FOUND);
        }

        $user->setConfirmationDatetime(new \DateTime());
        $user->setConfirmationToken(null);
        $this->getDoctrine()->getManager()->flush();

        return $this->redirectToRoute('app_homepage', ['_fragment' => '/registration/success']);
    }
}
