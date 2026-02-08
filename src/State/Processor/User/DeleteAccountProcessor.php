<?php

declare(strict_types=1);

namespace App\State\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\DeleteAccount;
use App\Entity\User;
use App\Service\Procedure\User\DeleteAccountProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * @implements ProcessorInterface<DeleteAccount, Response>
 */
readonly class DeleteAccountProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private DeleteAccountProcedure $deleteAccountProcedure,
    ) {
    }

    /**
     * @param DeleteAccount $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $this->deleteAccountProcedure->process($user);

        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $response->headers->setCookie(Cookie::create('jwt_hp', '', 1, '/', null, true, false, false, Cookie::SAMESITE_STRICT));
        $response->headers->setCookie(Cookie::create('jwt_s', '', 1, '/', null, true, true, false, Cookie::SAMESITE_STRICT));

        return $response;
    }
}
