<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\BandSpace as BandSpaceDto;
use App\ApiResource\BandSpace\BandSpaceCreate;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\User;
use App\Enum\BandSpace\Role;
use App\Service\Builder\BandSpace\BandSpaceBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<BandSpaceCreate, BandSpaceDto>
 */
readonly class BandSpaceCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceBuilder $bandSpaceBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param BandSpaceCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceDto
    {
        /** @var User $user */
        $user = $this->security->getUser();

        // Create BandSpace entity
        $bandSpace = new BandSpace();
        $bandSpace->name = $data->name;

        // Create creator membership
        $creatorMembership = new BandSpaceMembership();
        $creatorMembership->bandSpace = $bandSpace;
        $creatorMembership->user = $user;
        $creatorMembership->role = Role::Admin;

        // Add membership to band space (bidirectional relationship)
        $bandSpace->memberships->add($creatorMembership);

        // Persist entities
        $this->entityManager->persist($bandSpace);
        $this->entityManager->persist($creatorMembership);
        $this->entityManager->flush();

        return $this->bandSpaceBuilder->buildItem($bandSpace);
    }
}
