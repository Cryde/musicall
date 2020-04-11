<?php

namespace App\Service\User;

use App\Exception\NoMatchedUserAccountException;
use App\Repository\UserRepository;
use App\Service\Mail\ResetPasswordMail;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class ResetPassword
{
    /**
     * @var UserRepository
     */
    private UserRepository $userRepository;
    /**
     * @var UserTokenGenerator
     */
    private UserTokenGenerator $userTokenGenerator;
    /**
     * @var ResetPasswordMail
     */
    private ResetPasswordMail $resetPasswordMail;
    /**
     * @var RouterInterface
     */
    private RouterInterface $router;

    public function __construct(UserRepository $userRepository, UserTokenGenerator $userTokenGenerator, ResetPasswordMail $resetPasswordMail, RouterInterface $router)
    {
        $this->userRepository = $userRepository;
        $this->userTokenGenerator = $userTokenGenerator;
        $this->resetPasswordMail = $resetPasswordMail;
        $this->router = $router;
    }

    /**
     * @param string $login
     *
     * @throws NoMatchedUserAccountException
     * @throws NonUniqueResultException
     */
    public function resetPasswordByLogin(string $login)
    {
        $user = $this->userRepository->findOneByEmailOrLogin($login);

        if(!$user) {
            throw new NoMatchedUserAccountException('Aucun compte associé à ce login ou email n\'a été trouvé.');
        }

        $user->setResetRequestDatetime(new \DateTime());
        $this->userTokenGenerator->generate($user);

        $baseUrl = $this->router->generate('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->resetPasswordMail->send(
            $user->getEmail(),
            $user->getUsername(),
            $baseUrl . 'lost-password/' . $user->getToken()
        );
    }
}
