<?php

namespace App\Controller\Api\Comment;

use App\Entity\Comment\Comment;
use App\Entity\Comment\CommentThread;
use App\Entity\User;
use App\Serializer\Comment\CommentArraySerializer;
use App\Serializer\Comment\ThreadArraySerializer;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CommentController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    #[Route(path: '/api/thread/{id}/comments', name: 'api_thread_comments_post', options: ['expose' => true], methods: ['POST'])]
    public function add(
        CommentThread          $commentThread,
        Request                $request,
        SerializerInterface    $serializer,
        ValidatorInterface     $validator,
        CommentArraySerializer $commentArraySerializer,
        #[CurrentUser] User    $user
    ): JsonResponse {
        /** @var Comment $comment */
        $comment = $serializer->deserialize($request->getContent(), Comment::class, 'json');
        $comment->setAuthor($user);
        $commentThread->addComment($comment);
        $errors = $validator->validate($comment);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }
        $commentThread->setCommentNumber($commentThread->getCommentNumber() + 1);
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $this->json($commentArraySerializer->toArray($comment));
    }

    #[Route(path: '/api/thread/{id}/comments', name: 'api_thread_comments_list', options: ['expose' => true], methods: ['GET'])]
    public function list(CommentThread $commentThread, ThreadArraySerializer $threadArraySerializer): JsonResponse
    {
        // @todo : pagination
        // @todo (not related to list) : response to another comment
        return $this->json($threadArraySerializer->toArray($commentThread));
    }
}
