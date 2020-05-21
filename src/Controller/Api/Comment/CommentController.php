<?php

namespace App\Controller\Api\Comment;

use App\Entity\Comment\Comment;
use App\Entity\Comment\CommentThread;
use App\Serializer\Comment\CommentArraySerializer;
use App\Serializer\Comment\ThreadArraySerializer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CommentController extends AbstractController
{
    /**
     * @Route(
     *     "/api/thread/{id}/comments",
     *     name="api_thread_comments_post",
     *     methods={"POST"},
     *     options={"expose": true}
     * )
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param CommentThread          $commentThread
     * @param Request                $request
     * @param SerializerInterface    $serializer
     * @param ValidatorInterface     $validator
     * @param CommentArraySerializer $commentArraySerializer
     *
     * @return JsonResponse
     */
    public function add(
        CommentThread $commentThread,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CommentArraySerializer $commentArraySerializer
    ) {
        /** @var Comment $comment */
        $comment = $serializer->deserialize($request->getContent(), Comment::class, 'json');
        $comment->setAuthor($this->getUser());
        $commentThread->addComment($comment);

        $errors = $validator->validate($comment);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $commentThread->setCommentNumber($commentThread->getCommentNumber() + 1);
        $this->getDoctrine()->getManager()->persist($comment);
        $this->getDoctrine()->getManager()->flush();

        return $this->json($commentArraySerializer->toArray($comment));
    }

    /**
     * @Route(
     *     "/api/thread/{id}/comments",
     *     name="api_thread_comments_list",
     *     methods={"GET"},
     *     options={"expose": true}
     * )
     *
     * @param CommentThread         $commentThread
     * @param ThreadArraySerializer $threadArraySerializer
     *
     * @return JsonResponse
     */
    public function list(CommentThread $commentThread, ThreadArraySerializer $threadArraySerializer)
    {
        // @todo : pagination
        // @todo (not related to list) : response to another comment
        return $this->json($threadArraySerializer->toArray($commentThread));
    }
}
