<?php

namespace App\Model\Contact;

use Symfony\Component\Validator\Constraints as Assert;

class ContactModel
{
    /**
     * @Assert\NotBlank()
     * @Assert\Length(min="2", minMessage="Votre nom doit contenir minimum {{ limit }} caractères.")
     */
    private string $name;
    /**
     * @Assert\NotBlank()
     * @Assert\Email(message="L'email est invalide")
     */
    private string $email;
    /**
     * @Assert\NotBlank()
     * @Assert\Length(min="10", minMessage="Votre message doit être de minimum {{ limit }} caractères.")
     */
    private string $message;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return ContactModel
     */
    public function setName(string $name): ContactModel
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param string $email
     *
     * @return ContactModel
     */
    public function setEmail(string $email): ContactModel
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     *
     * @return ContactModel
     */
    public function setMessage(string $message): ContactModel
    {
        $this->message = $message;

        return $this;
    }
}
