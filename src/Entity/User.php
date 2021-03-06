<?php

namespace App\Entity;

use App\Entity\Image\UserProfilePicture;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 * @ORM\Table(name="fos_user")
 * @UniqueEntity(fields={"username"}, message="Ce login est déjà pris")
 * @UniqueEntity(fields={"email"}, message="Cet email est déjà utilisé")
 */
class User implements UserInterface
{
    /**
     * @ORM\Id()
     * @ORM\Column(name="id", type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    private $id;
    /**
     * @Assert\NotBlank(message="Veuillez saisir un nom d'utilisateur")
     *
     * @ORM\Column(type="string", length=180, unique=true)
     */
    private $username;
    /**
     * @Assert\NotBlank(message="Veuillez saisir un email")
     * @Assert\Email(message="Email invalide")
     *
     * @ORM\Column(type="string", length=180, unique=true)
     */
    private $email;
    /**
     * @Assert\NotBlank(message="Veuillez saisir un mot de passe")
     * @Assert\Length(min="3", minMessage="Le mot de passe doit au moins contenir 3 caractères")
     */
    private $plainPassword;
    /**
     * @ORM\Column(type="json")
     */
    private $roles = [];
    /**
     * @var string The hashed password
     * @ORM\Column(type="string")
     */
    private $password;
    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Publication", mappedBy="author", orphanRemoval=true)
     */
    private $publications;
    /**
     * @ORM\Column(type="datetime")
     */
    private $creationDatetime;
    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $lastLoginDatetime;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $oldId;

    /**
     * @var \DateTimeInterface|null
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $confirmationDatetime;

    /**
     * @var string|null
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $token;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $resetRequestDatetime;

    /**
     * @ORM\OneToOne(targetEntity=UserProfilePicture::class, cascade={"persist", "remove"})
     */
    private $profilePicture;

    public function __construct()
    {
        $this->publications = new ArrayCollection();
        $this->creationDatetime = new \DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
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

    /**
     * @see UserInterface
     */
    public function getPassword(): string
    {
        return (string)$this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getSalt()
    {
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

    public function getCreationDatetime(): ?\DateTimeInterface
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(\DateTimeInterface $creationDatetime): self
    {
        $this->creationDatetime = $creationDatetime;

        return $this;
    }

    public function getLastLoginDatetime(): ?\DateTimeInterface
    {
        return $this->lastLoginDatetime;
    }

    public function setLastLoginDatetime(?\DateTimeInterface $lastLoginDatetime): self
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
     *
     * @return User
     */
    public function setPlainPassword($plainPassword)
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

    public function getConfirmationDatetime(): ?\DateTimeInterface
    {
        return $this->confirmationDatetime;
    }

    public function setConfirmationDatetime(?\DateTimeInterface $confirmationDatetime): self
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

    public function getResetRequestDatetime(): ?\DateTimeInterface
    {
        return $this->resetRequestDatetime;
    }

    public function setResetRequestDatetime(?\DateTimeInterface $resetRequestDatetime): self
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
