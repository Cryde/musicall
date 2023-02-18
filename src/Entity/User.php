<?php

namespace App\Entity;

use App\Entity\Message\MessageThreadMeta;
use DateTimeInterface;
use DateTime;
use ApiPlatform\Metadata\Get;
use App\Entity\Comment\Comment;
use App\Entity\Forum\ForumPost;
use App\Entity\Forum\ForumTopic;
use App\Entity\Image\UserProfilePicture;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'fos_user')]
#[UniqueEntity(fields: ['username'], message: 'Ce login est déjà pris')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private $id;

    #[Assert\NotBlank(message: 'Veuillez saisir un nom d\'utilisateur')]
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    #[Groups([Comment::ITEM, Comment::LIST, ForumTopic::LIST, ForumPost::LIST, ForumTopic::LIST, Publication::ITEM, Publication::LIST, ForumPost::ITEM, MessageThreadMeta::LIST])]
    private $username;

    #[Assert\NotBlank(message: 'Veuillez saisir un email')]
    #[Assert\Email(message: 'Email invalide')]
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private $email;

    #[Assert\NotBlank(message: 'Veuillez saisir un mot de passe')]
    #[Assert\Length(min: 3, minMessage: 'Le mot de passe doit au moins contenir 3 caractères')]
    private $plainPassword;

    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(type: Types::STRING)]
    private $password;

    #[ORM\OneToMany(mappedBy: "author", targetEntity: Publication::class, orphanRemoval: true)]
    private $publications;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private $lastLoginDatetime;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private $oldId;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $confirmationDatetime = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $token = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private $resetRequestDatetime;

    #[ORM\OneToOne(targetEntity: UserProfilePicture::class, cascade: ['persist', 'remove'])]
    #[Groups([Comment::ITEM, Comment::LIST, ForumPost::LIST, ForumPost::ITEM, MessageThreadMeta::LIST])]
    private $profilePicture;

    public function __construct()
    {
        $this->publications = new ArrayCollection();
        $this->creationDatetime = new DateTime();
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return (string)$this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return (string)$this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return Collection|Publication[]
     */
    public function getPublications(): Collection
    {
        return $this->publications;
    }

    public function addPublication(Publication $publication): self
    {
        if (!$this->publications->contains($publication)) {
            $this->publications[] = $publication;
            $publication->setAuthor($this);
        }

        return $this;
    }

    public function removePublication(Publication $publication): self
    {
        if ($this->publications->contains($publication)) {
            $this->publications->removeElement($publication);
            // set the owning side to null (unless already changed)
            if ($publication->getAuthor() === $this) {
                $publication->setAuthor(null);
            }
        }

        return $this;
    }

    public function getCreationDatetime(): ?DateTimeInterface
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(DateTimeInterface $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    public function getLastLoginDatetime(): ?DateTimeInterface
    {
        return $this->lastLoginDatetime;
    }

    public function setLastLoginDatetime(?DateTimeInterface $lastLoginDatetime): self
    {
        $this->lastLoginDatetime = $lastLoginDatetime;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPlainPassword()
    {
        return $this->plainPassword;
    }

    /**
     * @param mixed $plainPassword
     */
    public function setPlainPassword($plainPassword): self
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function getOldId(): ?int
    {
        return $this->oldId;
    }

    public function setOldId(?int $oldId): self
    {
        $this->oldId = $oldId;

        return $this;
    }

    public function getConfirmationDatetime(): ?DateTimeInterface
    {
        return $this->confirmationDatetime;
    }

    public function setConfirmationDatetime(?DateTimeInterface $confirmationDatetime): self
    {
        $this->confirmationDatetime = $confirmationDatetime;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function getResetRequestDatetime(): ?DateTimeInterface
    {
        return $this->resetRequestDatetime;
    }

    public function setResetRequestDatetime(?DateTimeInterface $resetRequestDatetime): self
    {
        $this->resetRequestDatetime = $resetRequestDatetime;

        return $this;
    }

    public function getProfilePicture(): ?UserProfilePicture
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(?UserProfilePicture $profilePicture): self
    {
        $this->profilePicture = $profilePicture;

        return $this;
    }
}
