<?php

namespace App\Controller;

use App\Entity\User;
use App\Serializer\UserAppArraySerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="app_homepage")
     *
     * @param UserAppArraySerializer $userAppArraySerializer
     *
     * @return Response
     */
    public function indexAction(UserAppArraySerializer $userAppArraySerializer)
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return $this->render('base.html.twig', [
            'is_authenticated' => json_encode(!empty($user)),
            'user' => json_encode($user ? $userAppArraySerializer->toArray($user) : [])
        ]);
    }
}
