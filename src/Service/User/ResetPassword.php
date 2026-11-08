<?php declare(strict_types=1);

namespace App\Service\User;

use App\Http\ReturnUrl;
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
    public function resetPasswordByLogin(string $login, ?string $returnUrl = null): void
    {
        if (!($user = $this->userRepository->findOneByEmailOrLogin($login)) instanceof \App\Entity\User) {
            // we just do nothing no need to send exception
            return;
        }

        $user->resetRequestDatetime = new \DateTime();
        $this->userTokenGenerator->generate($user);

        $baseUrl = $this->router->generate('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $link = $baseUrl . 'lost-password/' . $user->token;
        // The link opens a new page, often in another tab, so the return can only travel inside it.
        if ($returnUrl !== null && ReturnUrl::isSafePath($returnUrl)) {
            $link .= '?' . http_build_query(['return_url' => $returnUrl]);
        }
        $this->resetPasswordEmail->send($user->email, $user->username, $link, $user->locale);
    }
}
