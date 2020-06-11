<?php

namespace App\Service\Updater\Wiki;

use App\Entity\Wiki\Artist;
use Doctrine\ORM\EntityManagerInterface;

class ArtistUpdater
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function update(Artist $artist, Artist $newArtist): void
    {
        $artist->setLabelName($newArtist->getLabelName());
        $artist->setBiography($newArtist->getBiography());
        $artist->setMembers($newArtist->getMembers());

        foreach ($artist->getSocials() as $social) {
            $artist->removeSocial($social);
            $this->entityManager->remove($social);
        }
        foreach ($newArtist->getSocials() as $social) {
            $artist->addSocial($social);
        }
    }
}
