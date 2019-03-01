<?php

namespace App\Controller;

use App\Entity\Publication;
use App\Repository\PublicationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    /**
     * @Route("/user/publications", name="user_publications")
     *
     * @param PublicationRepository $publicationRepository
     *
     * @return Response
     */
    public function publications(PublicationRepository $publicationRepository)
    {
        $drafts = $publicationRepository->findBy(['author' => $this->getUser(), 'status' => Publication::STATUS_DRAFT]);

        return $this->render('user/publications.html.twig', ['drafts' => $drafts]);
    }
}
