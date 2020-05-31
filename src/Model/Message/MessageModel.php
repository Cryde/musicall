<?php

namespace App\Model\Message;

use Symfony\Component\Validator\Constraints as Assert;

class MessageModel
{
    /**
     * @var string|null
     *
     * @Assert\NotBlank()
     */
    public ?string $content;

    /**
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * @param string|null $content
     *
     * @return MessageModel
     */
    public function setContent(?string $content): MessageModel
    {
        $this->content = $content;

        return $this;
    }
}
