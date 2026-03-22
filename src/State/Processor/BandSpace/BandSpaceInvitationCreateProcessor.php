<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Invitation\BandSpaceInvitationCreate;
use App\ApiResource\BandSpace\Invitation\BandSpaceInvitationResource;
use App\Entity\BandSpace\BandSpaceInvitation;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceInvitationRepository;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\UserRepository;
use App\Security\BandSpace\BandSpaceAdminChecker;
use App\Service\Builder\BandSpace\BandSpaceInvitationBuilder;
use App\Service\Mail\Brevo\BandSpace\BandSpaceInvitationExistingUserEmail;
use App\Service\Mail\Brevo\BandSpace\BandSpaceInvitationNewUserEmail;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @implements ProcessorInterface<BandSpaceInvitationCreate, BandSpaceInvitationResource>
 */
readonly class BandSpaceInvitationCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceAdminChecker $adminChecker,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private BandSpaceInvitationRepository $bandSpaceInvitationRepository,
        private UserRepository $userRepository,
        private BandSpaceInvitationBuilder $bandSpaceInvitationBuilder,
        private BandSpaceInvitationExistingUserEmail $existingUserEmail,
        private BandSpaceInvitationNewUserEmail $newUserEmail,
        private RouterInterface $router,
        private Security $security,
        #[Target('band_space_invitation')]
        private RateLimiterFactoryInterface $bandSpaceInvitationLimiter,
    ) {
    }

    /**
     * @param BandSpaceInvitationCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceInvitationResource
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        $this->bandSpaceInvitationLimiter->create($currentUser->getUserIdentifier())->consume()->ensureAccepted();

        $identifier = trim($data->identifier);

        [$bandSpace] = $this->adminChecker->checkAdmin((string) $uriVariables['bandSpaceId'], $currentUser);

        $isEmail = str_contains($identifier, '@');

        if ($isEmail) {
            $email = mb_strtolower($identifier);
            $existingUser = $this->userRepository->findOneBy(['email' => $email]);
        } else {
            // Username existence is guaranteed by InvitationIdentifierValidator
            $existingUser = $this->userRepository->findOneBy(['username' => $identifier]);
            \assert($existingUser !== null);
            $email = mb_strtolower($existingUser->email);
        }

        if ($existingUser && $this->bandSpaceMembershipRepository->isMember($bandSpace, $existingUser)) {
            throw new ConflictHttpException('Cet utilisateur est déjà membre de ce Band Space');
        }

        $pendingInvitation = $this->bandSpaceInvitationRepository->findPendingByEmailAndBandSpace($email, $bandSpace);
        if ($pendingInvitation) {
            throw new ConflictHttpException('Une invitation est déjà en attente pour cet utilisateur');
        }

        $invitation = new BandSpaceInvitation();
        $invitation->bandSpace = $bandSpace;
        $invitation->invitedBy = $currentUser;
        $invitation->email = $email;
        $invitation->token = bin2hex(random_bytes(32));
        $invitation->existingUser = $existingUser;

        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        $baseUrl = $this->router->generate('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);

        if ($existingUser) {
            $this->existingUserEmail->send(
                $email,
                $existingUser->username,
                $bandSpace->name,
                $baseUrl . 'band/invitation/' . $invitation->token,
            );
        } else {
            $this->newUserEmail->send(
                $email,
                $bandSpace->name,
                $baseUrl . 'register?invitation_token=' . $invitation->token,
            );
        }

        return $this->bandSpaceInvitationBuilder->buildItem($invitation);
    }
}
