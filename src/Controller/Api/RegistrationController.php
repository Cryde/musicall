<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Event\UserRegisteredEvent;
use App\Service\Jsonizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class RegistrationController extends AbstractController
{
    #[Route(path: '/api/register', name: 'api_register', options: ['expose' => true], methods: ['POST'])]
    public function register(
        Request                     $request,
        UserPasswordHasherInterface $userPasswordHasher,
        Jsonizer                    $jsonizer,
        ValidatorInterface          $validator,
        EntityManagerInterface      $entityManager,
        EventDispatcherInterface    $eventDispatcher
    ): JsonResponse {
        if ($this->getUser()) {
            return $this->json(['errors' => 'you already have an account'], Response::HTTP_FORBIDDEN);
        }

        $data = $jsonizer->decodeRequest($request);
        $user = (new User())
            ->setUsername($data['username'])
            ->setEmail($data['email'])
            ->setPlainPassword($data['password']);
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }
        // encode the plain password
        $user->setPassword(
            $userPasswordHasher->hashPassword(
                $user,
                $data['password']
            )
        );
        $entityManager->persist($user);
        $entityManager->flush();
        $eventDispatcher->dispatch(new UserRegisteredEvent($user));

        return $this->json(['data' => ['success' => 1]]);
    }
}
