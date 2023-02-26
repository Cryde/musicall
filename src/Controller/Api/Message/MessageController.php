<?php

namespace App\Controller\Api\Message;

use App\Entity\Message\Message;
use App\Entity\User;
use App\Model\Message\MessageModel;
use App\Repository\Message\MessageParticipantRepository;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Serializer\Message\MessageArraySerializer;
use App\Serializer\Message\MessageParticipantArraySerializer;
use App\Serializer\Message\MessageThreadArraySerializer;
use App\Serializer\Message\MessageThreadMetaArraySerializer;
use App\Service\Formatter\Message\MessageUserSenderFormatter;
use App\Service\Procedure\Message\MessageSenderProcedure;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MessageController extends AbstractController
{
    public function __construct(
        private readonly MessageThreadMetaRepository       $messageThreadMetaRepository,
        private readonly MessageParticipantRepository      $messageParticipantRepository,
        private readonly MessageThreadArraySerializer      $messageThreadArraySerializer,
        private readonly MessageThreadMetaArraySerializer  $threadMetaArraySerializer,
        private readonly MessageParticipantArraySerializer $messageParticipantArraySerializer,
        private readonly MessageArraySerializer            $messageArraySerializer,
        private readonly MessageUserSenderFormatter        $messageUserSenderFormatter,
    ) {
    }

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    #[Route(path: '/api/user/{id}/message', name: 'api_message_add', options: ['expose' => true], methods: ['POST'])]
    public function add(
        Request                $request,
        User                   $user,
        SerializerInterface    $serializer,
        ValidatorInterface     $validator,
        MessageSenderProcedure $messageSenderProcedure
    ): JsonResponse {
        /** @var MessageModel $messageModel */
        $messageModel = $serializer->deserialize($request->getContent(), MessageModel::class, 'json');
        $errors = $validator->validate($messageModel);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $message = $messageSenderProcedure->process($currentUser, $user, $messageModel->getContent());

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
