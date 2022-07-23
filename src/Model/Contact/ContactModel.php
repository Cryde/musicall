<?php

namespace App\Model\Contact;

use Symfony\Component\Validator\Constraints as Assert;

class ContactModel
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, minMessage: 'Votre nom doit contenir minimum {{ limit }} caractères.')]
    private string $name;
    #[Assert\NotBlank]
    #[Assert\Email(message: "L'email est invalide")]
    private string $email;
    #[Assert\NotBlank]
    #[Assert\Length(min: 10, minMessage: 'Votre message doit être de minimum {{ limit }} caractères.')]
    private string $message;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): ContactModel
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): ContactModel
    {
        $this->email = $email;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): ContactModel
    {
        $this->message = $message;

        return $this;
    }
}
