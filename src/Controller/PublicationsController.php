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
     * @Route("/publications/{id}/add", name="publications_add")
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
     * @Route("/publications/{id}/edit", name="publications_edit")
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Publication $publication
     * @param Request     $request
     *
     * @return RedirectResponse|Response
     */
    public function edit(Publication $publication, Request $request)
    {
        if($this->getUser()->getId() !== $publication->getAuthor()->getId()) {
            // todo: add flash message
            return $this->redirectToRoute('user_publications');
        }

        $form = $this->createForm(PublicationType::class, $publication);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->persist($publication);
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('user_publications');
        }

        return $this->render('publications/add.html.twig', ['form' => $form->createView()]);
    }

    /**
     * @Route("/publications/{id}/remove", name="publications_remove")
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Publication $publication
     *
     * @return RedirectResponse
     */
    public function remove(Publication $publication)
    {
        if($this->getUser()->getId() !== $publication->getAuthor()->getId()) {
            // todo add flash message
            return $this->redirectToRoute('user_publications');
        }

        if($publication->getStatus() !== Publication::STATUS_DRAFT) {
            // todo add flash message
            return $this->redirectToRoute('user_publications');
        }

        $this->getDoctrine()->getManager()->remove($publication);
        $this->getDoctrine()->getManager()->flush();

        return $this->redirectToRoute('user_publications');
    }

    /**
     * @Route("/publications/{id}", name="publications_show")
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
