<?php

namespace App\Serializer\Comment;

use App\Entity\Comment\Comment;
use App\Serializer\User\UserArraySerializer;
use Doctrine\Common\Collections\Collection;

class CommentArraySerializer
{
    private \HTMLPurifier $HTMLPurifier;
    private UserArraySerializer $userArraySerializer;

    public function __construct(UserArraySerializer $userArraySerializer, \HTMLPurifier $commentPurifier)
    {
        $this->HTMLPurifier = $commentPurifier;
        $this->userArraySerializer = $userArraySerializer;
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
            'author'            => $this->userArraySerializer->toArray($comment->getAuthor()),
            'content'           => $this->HTMLPurifier->purify(nl2br($comment->getContent())),
            'creation_datetime' => $comment->getCreationDatetime(),
        ];
    }
}
