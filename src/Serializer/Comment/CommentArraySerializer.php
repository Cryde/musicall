<?php

namespace App\Serializer\Comment;

use App\Entity\Comment\Comment;
use App\Serializer\UserAppArraySerializer;
use Doctrine\Common\Collections\Collection;

class CommentArraySerializer
{
    /**
     * @var UserAppArraySerializer
     */
    private UserAppArraySerializer $userAppArraySerializer;
    private \HTMLPurifier $HTMLPurifier;

    public function __construct(UserAppArraySerializer $userAppArraySerializer, \HTMLPurifier $commentPurifier)
    {
        $this->userAppArraySerializer = $userAppArraySerializer;
        $this->HTMLPurifier = $commentPurifier;
    }

    /**
     * @param Collection|Comment[] $comments
     *
     * @return array
     */
    public function listToArray($comments): array
    {
        $list = [];
        foreach ($comments as $comment) {
            $list[] = $this->toArray($comment);
        }

        return $list;
    }

    /**
     * @param Comment $comment
     *
     * @return array
     */
    public function toArray(Comment $comment): array
    {
        return [
            'id'                => $comment->getId(),
            'author'            => $this->userAppArraySerializer->toArray($comment->getAuthor()),
            'content'           => $this->HTMLPurifier->purify(nl2br($comment->getContent())),
            'creation_datetime' => $comment->getCreationDatetime(),
        ];
    }
}
