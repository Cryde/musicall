<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Event\UserRegisteredEvent;
use App\Service\Jsonizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class RegistrationController extends AbstractController
{
    /**
     * @Route("/api/register", name="api_register", options={"expose": true})
     *
     * @param Request                      $request
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @param Jsonizer                     $jsonizer
     * @param ValidatorInterface           $validator
     * @param EventDispatcherInterface     $eventDispatcher
     *
     * @return Response
     */
    public function register(
        Request $request,
        UserPasswordEncoderInterface $passwordEncoder,
        Jsonizer $jsonizer,
        ValidatorInterface $validator,
        EventDispatcherInterface $eventDispatcher
    ): Response {

        $data = $jsonizer->decodeRequest($request);

        $user = (new User())
            ->setUsername($data['username'])
            ->setEmail($data['email'])
            ->setPlainPassword($data['password']);

        $errors = $validator->validate($user);

        if (count($errors) > 0) {
            return $this->json(['data' => ['errors' => $errors]], Response::HTTP_UNAUTHORIZED);
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

        $eventDispatcher->dispatch(new UserRegisteredEvent($user), UserRegisteredEvent::NAME);

        return $this->json(['data' => ['success' => 1]]);
    }
}
