<?php

namespace App\Controller;

use App\Entity\Publication;
use App\Form\PublicationType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
     * @Route("/publications/add", name="publications_add")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function add(Request $request)
    {
        $publication = (new Publication())
            ->setCategory(Publication::CATEGORY_PUBLICATION_ID)
            ->setAuthor($this->getUser());

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
     * @Route("/publications/{id}", name="publications_show")
     *
     * @return Response
     */
    public function show(Publication $publication)
    {
        return $this->render('publications/show.html.twig', ['publication' => $publication]);
    }
}
