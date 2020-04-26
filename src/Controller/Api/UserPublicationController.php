<?php

namespace App\Controller\Api;

use App\Entity\Image\PublicationCover;
use App\Entity\Image\PublicationImage;
use App\Entity\Publication;
use App\Entity\User;
use App\Form\ImageUploaderType;
use App\Repository\PublicationRepository;
use App\Repository\PublicationSubCategoryRepository;
use App\Serializer\UserPublicationArraySerializer;
use App\Service\Builder\PublicationCoverDirector;
use App\Service\Builder\PublicationDirector;
use App\Service\File\Exception\CorruptedFileException;
use App\Service\File\RemoteFileDownloader;
use App\Service\Google\Exception\YoutubeAlreadyExistingVideoException;
use App\Service\Google\Exception\YoutubeVideoNotFoundException;
use App\Service\Google\Youtube;
use App\Service\Google\YoutubeUrlHelper;
use App\Service\Jsonizer;
use App\Service\Publication\PublicationSlug;
use App\Service\UserPublication\SortAndFilterFromArray;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class UserPublicationController extends AbstractController
{
    /**
     * @Route("/api/users/publications/", name="api_user_publication_list", methods={"POST"}, options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Request                        $request
     * @param PublicationRepository          $publicationRepository
     * @param UserPublicationArraySerializer $userPublicationArraySerializer
     * @param Jsonizer                       $jsonizer
     * @param SortAndFilterFromArray         $sortAndFilterFromArray
     *
     * @return JsonResponse
     */
    public function list(
        Request $request,
        PublicationRepository $publicationRepository,
        UserPublicationArraySerializer $userPublicationArraySerializer,
        Jsonizer $jsonizer,
        SortAndFilterFromArray $sortAndFilterFromArray
    ) {
        $filter = $sortAndFilterFromArray->createFromArray($jsonizer->decodeRequest($request));
        $count = $publicationRepository->count(['author' => $this->getUser()]);
        $publications = $publicationRepository->findBy(['author' => $this->getUser()], $filter['sort'], $filter['limit'], $filter['offset']);

        return $this->json([
            'publications' => $userPublicationArraySerializer->listToArray($publications),
            'meta'         => [
                'total'          => $count,
                'items_per_page' => SortAndFilterFromArray::ITEM_PER_PAGE,
            ],
        ]);
    }

    /**
     * @Route("/api/users/publications/{id}/delete", name="api_user_publication_delete", options={"expose": true}, methods={"DELETE"})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Publication $publication
     *
     * @return JsonResponse
     */
    public function remove(Publication $publication)
    {
        if ($this->getUser()->getId() !== $publication->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Ce publication ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        if ($publication->getStatus() !== Publication::STATUS_DRAFT) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Vous ne pouvez pas supprimer une publication en ligne ou en review']], Response::HTTP_FORBIDDEN);
        }

        $this->getDoctrine()->getManager()->remove($publication);
        $this->getDoctrine()->getManager()->flush();

        return $this->json(['data' => ['success' => 1]]);
    }

    /**
     * @Route("/api/users/publications/add", name="api_user_publication_add", options={"expose": true}, methods={"POST"})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Request                          $request
     * @param PublicationSubCategoryRepository $publicationSubCategoryRepository
     * @param Jsonizer                         $jsonizer
     * @param ValidatorInterface               $validator
     * @param UserPublicationArraySerializer   $userPublicationArraySerializer
     *
     * @return JsonResponse
     */
    public function add(
        Request $request,
        PublicationSubCategoryRepository $publicationSubCategoryRepository,
        Jsonizer $jsonizer,
        ValidatorInterface $validator,
        UserPublicationArraySerializer $userPublicationArraySerializer
    ) {
        $data = $jsonizer->decodeRequest($request);

        $publicationSubCategory = $data['category_id'] ? $publicationSubCategoryRepository->find($data['category_id']) : null;

        if (!$publicationSubCategory) {
            throw new \InvalidArgumentException('Catégorie inexistante');
        }

        $publication = new Publication();
        $publication->setTitle($data['title']);
        $publication->setSlug('publication-' . $this->getUser()->getId() . random_int(10, 9999999999));
        $publication->setType(Publication::TYPE_TEXT);
        $publication->setSubCategory($publicationSubCategory);
        /** @var User|null $user */
        $user = $this->getUser();
        $publication->setAuthor($user);

        $errors = $validator->validate($publication);

        if (count($errors) > 0) {
            return $this->json(['data' => ['errors' => $errors]], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->getDoctrine()->getManager()->persist($publication);
        $this->getDoctrine()->getManager()->flush();

        return $this->json(['data' => ['publication' => $userPublicationArraySerializer->toArray($publication)]]);
    }

    /**
     * @Route(
     *     "/api/users/publications/add/video",
     *     name="api_user_publication_add_video",
     *     options={"expose": true},
     *     methods={"POST"},
     *     defaults={"_format": "json"}
     * )
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Request                        $request
     * @param Jsonizer                       $jsonizer
     * @param Youtube                        $youtube
     * @param YoutubeUrlHelper               $youtubeUrlHelper
     * @param PublicationCoverDirector       $publicationCoverDirector
     * @param PublicationDirector            $publicationDirector
     * @param PublicationRepository          $publicationRepository
     * @param UserPublicationArraySerializer $userPublicationArraySerializer
     * @param RemoteFileDownloader           $remoteFileDownloader
     * @param ParameterBagInterface          $containerBag
     *
     * @return JsonResponse
     * @throws CorruptedFileException
     * @throws YoutubeAlreadyExistingVideoException
     * @throws YoutubeVideoNotFoundException
     */
    public function addVideo(
        Request $request,
        Jsonizer $jsonizer,
        Youtube $youtube,
        YoutubeUrlHelper $youtubeUrlHelper,
        PublicationCoverDirector $publicationCoverDirector,
        PublicationDirector $publicationDirector,
        PublicationRepository $publicationRepository,
        UserPublicationArraySerializer $userPublicationArraySerializer,
        RemoteFileDownloader $remoteFileDownloader,
        ParameterBagInterface $containerBag
    ) {
        $data = $jsonizer->decodeRequest($request);
        $videoUrl = $data['videoUrl'];

        $videoId = $youtubeUrlHelper->getVideoId($videoUrl);

        if($publicationRepository->findOneBy(['content' => $videoId, 'type' => Publication::TYPE_VIDEO])) {
            throw new YoutubeAlreadyExistingVideoException('This video is already sent');
        }

        // @todo : emit handle exceptions
        $youtube->getVideoInfo($videoUrl); // this to be sure the video still exist

        $file = $remoteFileDownloader->download($data['imageUrl'], $containerBag->get('file_publication_cover_destination'));

        $cover = $publicationCoverDirector->build($file);
        /** @var User $user */
        $user = $this->getUser();
        $publication = $publicationDirector->buildVideo($data, $user);
        $cover->setPublication($publication);
        $publication->setCover($cover);

        $this->getDoctrine()->getManager()->persist($publication);
        $this->getDoctrine()->getManager()->flush();

        return $this->json(['data' => $userPublicationArraySerializer->toArray($publication)]);
    }

    /**
     * @Route("/api/users/publications/preview/video", name="api_publications_video_preview", options={"expose": true}, methods={"POST"}, defaults={"_format": "json"})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Request               $request
     * @param Jsonizer              $jsonizer
     * @param Youtube               $youtube
     * @param YoutubeUrlHelper      $youtubeUrlHelper
     * @param PublicationRepository $publicationRepository
     *
     * @return JsonResponse
     * @throws YoutubeVideoNotFoundException
     */
    public function videoPreview(
        Request $request,
        Jsonizer $jsonizer,
        Youtube $youtube,
        YoutubeUrlHelper $youtubeUrlHelper,
        PublicationRepository $publicationRepository
    ) {
        $data = $jsonizer->decodeRequest($request);

        if(!isset($data['videoUrl'])) {
            throw new \InvalidArgumentException('Missing url');
        }

        $existingVideo = $publicationRepository->findOneVideo($youtubeUrlHelper->getVideoId($data['videoUrl']));
        $info = array_merge($youtube->getVideoInfo($data['videoUrl']), ['existing_video' => $existingVideo !== null]);

        return $this->json(['data' => $info]);
    }

    /**
     * @Route("/api/users/publications/{id}", name="api_user_publication_show", options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Publication                    $publication
     * @param UserPublicationArraySerializer $userPublicationArraySerializer
     *
     * @return object|JsonResponse
     */
    public function show(Publication $publication, UserPublicationArraySerializer $userPublicationArraySerializer)
    {
        if ($this->getUser()->getId() !== $publication->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Ce publication ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        return $this->json(['data' => ['publication' => $userPublicationArraySerializer->toArray($publication, true)]]);
    }

    /**
     * @Route("/api/users/publications/{id}/save", name="api_user_publication_save", options={"expose": true}, methods={"POST"})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Publication                    $publication
     * @param Request                        $request
     * @param Jsonizer                       $jsonizer
     * @param ValidatorInterface             $validator
     * @param UserPublicationArraySerializer $userPublicationArraySerializer
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function save(
        Publication $publication,
        Request $request,
        Jsonizer $jsonizer,
        ValidatorInterface $validator,
        UserPublicationArraySerializer $userPublicationArraySerializer
    ) {
        if ($this->getUser()->getId() !== $publication->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Ce publication ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        $data = $jsonizer->decodeRequest($request);

        $publication->setTitle($data['title']);
        $publication->setShortDescription($data['short_description']);
        $publication->setContent($data['content']);
        $publication->setEditionDatetime(new \DateTime());

        $errors = $validator->validate($publication);

        if (count($errors) > 0) {
            return $this->json(['data' => ['errors' => $errors]], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->getDoctrine()->getManager()->flush();

        return $this->json(['data' => ['publication' => $userPublicationArraySerializer->toArray($publication)]]);
    }

    /**
     * @Route("/api/users/publications/{id}/publish", name="api_user_publication_publish", options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Publication                    $publication
     * @param ValidatorInterface             $validator
     * @param UserPublicationArraySerializer $userPublicationArraySerializer
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function publish(
        Publication $publication,
        ValidatorInterface $validator,
        UserPublicationArraySerializer $userPublicationArraySerializer
    ) {
        if ($this->getUser()->getId() !== $publication->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Ce publication ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        $publication->setPublicationDatetime(new \DateTime());

        $errors = $validator->validate($publication, null, ['publication']);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $publication->setStatus(Publication::STATUS_PENDING);
        $this->getDoctrine()->getManager()->flush();

        return $this->json(['data' => ['publication' => $userPublicationArraySerializer->toArray($publication)]]);
    }

    /**
     * @Route("/api/users/publications/{id}/upload-image", name="api_user_publication_upload_image", options={"expose": true}, methods={"POST"})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Request        $request
     * @param Publication    $publication
     * @param UploaderHelper $uploaderHelper
     * @param CacheManager   $cacheManager
     *
     * @return JsonResponse
     */
    public function uploadImage(Request $request, Publication $publication, UploaderHelper $uploaderHelper, CacheManager $cacheManager)
    {
        if ($this->getUser()->getId() !== $publication->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Ce publication ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        $image = new PublicationImage();
        $image->setPublication($publication);
        $form = $this->createForm(ImageUploaderType::class, $image);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->persist($image);
            $this->getDoctrine()->getManager()->flush();

            $imagePath = $uploaderHelper->asset($image, 'imageFile');

            return $this->json(['data' => ['uri' => $cacheManager->generateUrl($imagePath, 'publication_image_filter')]]);
        }

        return $this->json($form->getErrors(true, true), Response::HTTP_BAD_REQUEST);
    }

    /**
     * @Route("/api/users/publications/{id}/upload-cover", name="api_user_publication_upload_cover", options={"expose": true}, methods={"POST"})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Request        $request
     * @param Publication    $publication
     * @param UploaderHelper $uploaderHelper
     * @param CacheManager   $cacheManager
     *
     * @return JsonResponse
     */
    public function uploadCover(Request $request, Publication $publication, UploaderHelper $uploaderHelper, CacheManager $cacheManager)
    {
        if ($this->getUser()->getId() !== $publication->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Ce publication ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        $previousCover = $publication->getCover() ? $publication->getCover() : null;

        $cover = new PublicationCover();
        $cover->setPublication($publication);
        $form = $this->createForm(ImageUploaderType::class, $cover);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            if($previousCover) {
                $publication->setCover(null);
                $this->getDoctrine()->getManager()->flush();
                $this->getDoctrine()->getManager()->remove($previousCover);
                $this->getDoctrine()->getManager()->flush();
            }

            $this->getDoctrine()->getManager()->persist($cover);
            $publication->setCover($cover);
            $this->getDoctrine()->getManager()->flush();

            $imagePath = $uploaderHelper->asset($cover, 'imageFile');

            return $this->json(['data' => ['uri' => $cacheManager->generateUrl($imagePath, 'publication_cover_300x300')]]);
        }

        return $this->json($form->getErrors(true, true), Response::HTTP_BAD_REQUEST);
    }
}
