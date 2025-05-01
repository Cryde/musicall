<?php

namespace App\Service\User;

use App\Exception\NoMatchedUserAccountException;
use App\Repository\UserRepository;
use App\Service\Mail\Brevo\User\ResetPasswordEmail;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class ResetPassword
{
    public function __construct(
        private readonly UserRepository     $userRepository,
        private readonly UserTokenGenerator $userTokenGenerator,
        private readonly ResetPasswordEmail $resetPasswordEmail,
        private readonly RouterInterface    $router
    ) {
    }

    /**
     * @throws NoMatchedUserAccountException
     * @throws NonUniqueResultException
     */
    public function resetPasswordByLogin(string $login): void
    {
        $user = $this->userRepository->findOneByEmailOrLogin($login);
        if (!$user) {
            throw new NoMatchedUserAccountException('Aucun compte associé à ce login ou email n\'a été trouvé.');
        }

        $user->setResetRequestDatetime(new \DateTime());
        $this->userTokenGenerator->generate($user);

        $baseUrl = $this->router->generate('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->resetPasswordEmail->send(
            $user->getEmail(),
            $user->getUsername(),
            $baseUrl . 'lost-password/' . $user->getToken()
        );
    }
}
