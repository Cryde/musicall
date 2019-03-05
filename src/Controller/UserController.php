<?php

namespace App\Controller;

use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Repository\PublicationSubCategoryRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    /**
     * @Route("/user/publications", name="user_publications")
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param PublicationRepository            $publicationRepository
     * @param PublicationSubCategoryRepository $publicationSubCategoryRepository
     *
     * @return Response
     */
    public function publications(
        PublicationRepository $publicationRepository,
        PublicationSubCategoryRepository $publicationSubCategoryRepository
    ) {
        $drafts = $publicationRepository->findBy(['author' => $this->getUser(), 'status' => Publication::STATUS_DRAFT]);
        $subCategories = $publicationSubCategoryRepository->findAll();

        return $this->render('user/publications.html.twig', [
            'drafts'         => $drafts,
            'sub_categories' => $subCategories,
        ]);
    }
}
