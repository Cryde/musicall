<?php

declare(strict_types=1);

namespace App\Controller;

use App\Event\UserConfirmedEvent;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register/confirm/{token}', name: 'app_register_confirm', options: ['expose' => true])]
    public function confirm(
        string                   $token,
        UserRepository           $userRepository,
        EntityManagerInterface   $entityManager,
        EventDispatcherInterface $eventDispatcher,
    ): RedirectResponse|Response {
        if (!$user = $userRepository->findOneBy(['token' => $token])) {
            return new Response('Ce token n\'existe pas/plus, il est possible que vous ayez déjà confirmé votre compte', Response::HTTP_NOT_FOUND);
        }
        $user->setConfirmationDatetime(new \DateTime());
        $user->setToken(null);
        $entityManager->flush();

        $eventDispatcher->dispatch(new UserConfirmedEvent($user));

        return $this->redirect('/registration/success');
    }
}
