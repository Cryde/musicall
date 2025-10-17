<?php declare(strict_types=1);

namespace App\Service\User;

use App\Repository\UserRepository;
use App\Service\Mail\Brevo\User\ResetPasswordEmail;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

readonly class ResetPassword
{
    public function __construct(
        private UserRepository     $userRepository,
        private UserTokenGenerator $userTokenGenerator,
        private ResetPasswordEmail $resetPasswordEmail,
        private RouterInterface    $router
    ) {
    }

    /**
     * @throws NonUniqueResultException
     */
    public function resetPasswordByLogin(string $login): void
    {
        if (!$user = $this->userRepository->findOneByEmailOrLogin($login)) {
            // we just do nothing no need to send exception
            return;
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
