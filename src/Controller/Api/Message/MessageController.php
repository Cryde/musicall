<?php

namespace App\Controller\Api\Message;

use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Model\Message\MessageModel;
use App\Repository\Message\MessageParticipantRepository;
use App\Repository\Message\MessageRepository;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Serializer\Message\MessageArraySerializer;
use App\Serializer\Message\MessageParticipantArraySerializer;
use App\Serializer\Message\MessageThreadArraySerializer;
use App\Serializer\Message\MessageThreadMetaArraySerializer;
use App\Service\Access\ThreadAccess;
use App\Service\Formatter\Message\MessageUserSenderFormatter;
use App\Service\Procedure\Message\MessageSenderProcedure;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MessageController extends AbstractController
{
    /**
     * @var MessageThreadArraySerializer
     */
    private MessageThreadArraySerializer $messageThreadArraySerializer;
    /**
     * @var MessageThreadMetaArraySerializer
     */
    private MessageThreadMetaArraySerializer $threadMetaArraySerializer;
    /**
     * @var MessageParticipantArraySerializer
     */
    private MessageParticipantArraySerializer $messageParticipantArraySerializer;
    /**
     * @var MessageArraySerializer
     */
    private MessageArraySerializer $messageArraySerializer;
    /**
     * @var MessageThreadMetaRepository
     */
    private MessageThreadMetaRepository $messageThreadMetaRepository;
    /**
     * @var MessageParticipantRepository
     */
    private MessageParticipantRepository $messageParticipantRepository;
    /**
     * @var MessageUserSenderFormatter
     */
    private MessageUserSenderFormatter $messageUserSenderFormatter;

    public function __construct(
        MessageThreadMetaRepository $messageThreadMetaRepository,
        MessageParticipantRepository $messageParticipantRepository,
        MessageThreadArraySerializer $messageThreadArraySerializer,
        MessageThreadMetaArraySerializer $threadMetaArraySerializer,
        MessageParticipantArraySerializer $messageParticipantArraySerializer,
        MessageArraySerializer $messageArraySerializer,
        MessageUserSenderFormatter $messageUserSenderFormatter
    ) {
        $this->messageThreadArraySerializer = $messageThreadArraySerializer;
        $this->threadMetaArraySerializer = $threadMetaArraySerializer;
        $this->messageParticipantArraySerializer = $messageParticipantArraySerializer;
        $this->messageArraySerializer = $messageArraySerializer;
        $this->messageThreadMetaRepository = $messageThreadMetaRepository;
        $this->messageParticipantRepository = $messageParticipantRepository;
        $this->messageUserSenderFormatter = $messageUserSenderFormatter;
    }

    /**
     * @Route("/api/user/{id}/message", name="api_message_add", methods={"POST"}, options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Request                $request
     * @param User                   $user
     * @param SerializerInterface    $serializer
     * @param ValidatorInterface     $validator
     * @param MessageSenderProcedure $messageSenderProcedure
     *
     * @return JsonResponse
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function add(
        Request $request,
        User $user,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        MessageSenderProcedure $messageSenderProcedure
    ) {
        /** @var MessageModel $messageModel */
        $messageModel = $serializer->deserialize($request->getContent(), MessageModel::class, 'json');
        $errors = $validator->validate($messageModel);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }
        /** @var User $user */
        $user = $this->getUser();
        $message = $messageSenderProcedure->process($user, $user, $messageModel->getContent());

        return $this->getMessageResponse($message);
    }

    /**
     * @Route("/api/thread", name="api_thread_list", methods={"GET"}, options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param MessageThreadMetaRepository      $messageThreadMetaRepository
     * @param MessageThreadMetaArraySerializer $threadMetaArraySerializer
     *
     * @return JsonResponse
     */
    public function listThread(
        MessageThreadMetaRepository $messageThreadMetaRepository,
        MessageThreadMetaArraySerializer $threadMetaArraySerializer
    ) {
        /** @var User $user */
        $user = $this->getUser();

        $metaThreads = $messageThreadMetaRepository->findByUserAndNotDeleted($user);

        return $this->json($threadMetaArraySerializer->listToArray($metaThreads, true));
    }

    /**
     * @Route("/api/thread/{id}/messages", name="api_thread_message_list", methods={"GET"}, options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param MessageThread              $thread
     * @param MessageRepository          $messageRepository
     * @param ThreadAccess               $threadAccess
     * @param MessageArraySerializer     $messageArraySerializer
     *
     * @return JsonResponse
     */
    public function listMessageByThread(
        MessageThread $thread,
        MessageRepository $messageRepository,
        ThreadAccess $threadAccess,
        MessageArraySerializer $messageArraySerializer
    ) {
        /** @var User $user */
        $user = $this->getUser();

        if (!$messages = $messageRepository->findBy(['thread' => $thread])) {
            throw new \UnexpectedValueException('Ce thread n\'existe pas.');
        }

        if(!$threadAccess->isOneOfParticipant($thread, $user)) {
            throw new \UnexpectedValueException('Vous n\'avez pas accès à ce thread.');
        }

        $formattedMessages = $this->messageUserSenderFormatter->formatList(
            $messageArraySerializer->listToArray($messages),
            $user
        );

        return $this->json($formattedMessages);
    }

    /**
     * @Route("/api/thread/{id}/read", name="api_thread_message_mark_read", methods={"PATCH"}, options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param MessageThread               $thread
     * @param MessageThreadMetaRepository $messageThreadMetaRepository
     *
     * @return JsonResponse
     */
    public function markThreadAsRead(
        MessageThread $thread,
        MessageThreadMetaRepository $messageThreadMetaRepository
    ) {
        /** @var User $user */
        $user = $this->getUser();

        if(!$meta = $messageThreadMetaRepository->findOneBy(['thread' => $thread, 'user' => $user])) {
            throw new \UnexpectedValueException('Quelque chose d\'anormal s\'est passé');
        }

        $meta->setIsRead(true);
        $this->getDoctrine()->getManager()->flush();

        return $this->json([], Response::HTTP_ACCEPTED);
    }

    /**
     * @Route("/api/thread/{id}/messages", name="api_thread_message_add", methods={"POST"}, options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param MessageThread          $thread
     * @param Request                $request
     * @param MessageRepository      $messageRepository
     * @param ThreadAccess           $threadAccess
     * @param SerializerInterface    $serializer
     * @param ValidatorInterface     $validator
     * @param MessageSenderProcedure $messageSenderProcedure
     *
     * @return JsonResponse
     */
    public function postByThread(
        MessageThread $thread,
        Request $request,
        MessageRepository $messageRepository,
        ThreadAccess $threadAccess,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        MessageSenderProcedure $messageSenderProcedure
    )
    {
        if (!$messageRepository->findBy(['thread' => $thread])) {
            throw new \UnexpectedValueException('Ce thread n\'existe pas.');
        }

        /** @var User $user */
        $user = $this->getUser();

        if(!$threadAccess->isOneOfParticipant($thread, $user)) {
            throw new \UnexpectedValueException('Vous n\'avez pas accès à ce thread.');
        }

        /** @var MessageModel $messageModel */
        $messageModel = $serializer->deserialize($request->getContent(), MessageModel::class, 'json');
        $errors = $validator->validate($messageModel);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $message = $messageSenderProcedure->processByThread($thread, $user, $messageModel->getContent());

        return $this->getMessageResponse($message);
    }

    private function getMessageResponse(Message $message): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $metaThread = $this->messageThreadMetaRepository->findOneBy(['user' => $user, 'thread' => $message->getThread()]);
        $messageParticipant = $this->messageParticipantRepository->findBy(['thread' => $message->getThread()]);

        return $this->json([
            'thread'       => $this->messageThreadArraySerializer->toArray($message->getThread()),
            'meta'         => $this->threadMetaArraySerializer->toArray($metaThread),
            'participants' => $this->messageParticipantArraySerializer->listToArray($messageParticipant),
            'message'      => $this->messageUserSenderFormatter->format($this->messageArraySerializer->toArray($message), $user),
        ]);
    }
}
