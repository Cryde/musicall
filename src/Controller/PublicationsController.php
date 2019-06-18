<?php

namespace App\Controller;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use App\Form\PublicationType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PublicationsController extends AbstractController
{
    /**
     * @Route("/publications/", name="publications_index")
     *
     * @return Response
     */
    public function index()
    {
        return $this->render('publications/index.html.twig');
    }


    /**
     * @Route("/publications/{id}/add", name="publications_add", options={"expose": true})
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param PublicationSubCategory $subCategory
     *
     * @return RedirectResponse
     */
    public function add(PublicationSubCategory $subCategory)
    {
        $publication = (new Publication())
            ->setTitle('Votre titre ici')
            ->setCategory(Publication::CATEGORY_PUBLICATION_ID)
            ->setAuthor($this->getUser())
            ->setSubCategory($subCategory);

        $this->getDoctrine()->getManager()->persist($publication);
        $this->getDoctrine()->getManager()->flush();

        return $this->redirectToRoute('publications_edit', ['id' => $publication->getId()]);
    }

    /**
     * @Route("/publications/{id}/edit", name="publications_edit", options={"expose":true})
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Publication $publication
     *
     * @return RedirectResponse|Response
     */
    public function edit(Publication $publication)
    {
        if($this->getUser()->getId() !== $publication->getAuthor()->getId()) {
            // todo: add flash message
            return $this->redirectToRoute('user_publications');
        }

        return $this->render('publications/edit.html.twig', ['publication' => $publication]);
    }

    /**
     * @Route("/publications/{slug}", name="publications_show", options={"expose":true})
     *
     * @param Publication $publication
     *
     * @return Response
     */
    public function show(Publication $publication)
    {
        return $this->render('publications/show.html.twig', ['publication' => $publication]);
    }
}
