<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Comment\Comment;
use App\Entity\Forum\ForumPost;
use App\Entity\Forum\ForumTopic;
use App\Entity\Image\UserProfilePicture;
use App\Entity\Message\Message;
use App\Entity\Musician\MusicianProfile;
use App\Entity\Teacher\TeacherProfile;
use App\Entity\User\UserNotificationPreference;
use App\Entity\User\UserProfile;
use App\Entity\Message\MessageThreadMeta;
use App\Repository\UserRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\SocialAccount;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'fos_user')]
#[UniqueEntity(fields: ['username'], message: 'Ce login est déjà pris')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé')]
#[ApiResource(
    operations: [
        new Get(
            openapi: new Operation(tags: ['Users']),
            normalizationContext: ['groups' => [User::ITEM], 'skip_null_values' => false],
            name: 'api_users_get_item',
        ),
    ]
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    const ITEM = 'USER_ITEM';

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups([User::ITEM, Message::LIST, MessageThreadMeta::LIST, Message::ITEM])]
    private string $id;

    #[Assert\NotBlank(message: 'Veuillez saisir un nom d\'utilisateur')]
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    #[Groups([Comment::ITEM, Comment::LIST, Publication::ITEM, Publication::LIST, MessageThreadMeta::LIST, User::ITEM, Message::LIST, Message::ITEM, Gallery::LIST])]
    private string $username;

    #[Assert\NotBlank(message: 'Veuillez saisir un email')]
    #[Assert\Email(message: 'Email invalide')]
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private string $email;

    #[Assert\NotBlank(message: 'Veuillez saisir un mot de passe')]
    #[Assert\Length(min: 3, minMessage: 'Le mot de passe doit au moins contenir 3 caractères')]
    private string $plainPassword;

    /** @var string[] */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $password = null;

    /**
     * @var Collection<int, Publication>
     */
    #[ORM\OneToMany(mappedBy: "author", targetEntity: Publication::class, orphanRemoval: true)]
    private Collection $publications;

    /**
     * @var Collection<int, SocialAccount>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: SocialAccount::class, orphanRemoval: true)]
    private Collection $socialAccounts;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTime $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $lastLoginDatetime;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $oldId;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $confirmationDatetime = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $token = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $resetRequestDatetime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $usernameChangedDatetime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([Comment::ITEM, Comment::LIST, Publication::ITEM, Publication::LIST, MessageThreadMeta::LIST, User::ITEM, Message::LIST, Message::ITEM, Gallery::LIST])]
    private ?\DateTimeImmutable $deletionDatetime = null;

    #[ORM\OneToOne(targetEntity: UserProfilePicture::class, cascade: ['persist', 'remove'])]
    #[Groups([Comment::ITEM, Comment::LIST, MessageThreadMeta::LIST, User::ITEM])]
    private ?UserProfilePicture $profilePicture = null;
    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private UserProfile $profile;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: MusicianProfile::class, cascade: ['persist', 'remove'])]
    private ?MusicianProfile $musicianProfile = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: UserNotificationPreference::class, cascade: ['persist', 'remove'])]
    private ?UserNotificationPreference $notificationPreference = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: TeacherProfile::class, cascade: ['persist', 'remove'])]
    private ?TeacherProfile $teacherProfile = null;

    public function __construct()
    {
        $this->publications = new ArrayCollection();
        $this->socialAccounts = new ArrayCollection();
        $this->creationDatetime = new DateTime();
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        assert($this->username !== '');

        return $this->username;
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

    /**
     * @param string[] $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
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
    public function eraseCredentials(): void
    {
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return Collection<int, Publication>
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
        $this->publications->removeElement($publication);

        return $this;
    }

    public function getCreationDatetime(): DateTime
    {
        return $this->creationDatetime;
    }

    public function setCreationDatetime(DateTime $creationDatetime): self
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

    /**
     * @return Collection<int, SocialAccount>
     */
    public function getSocialAccounts(): Collection
    {
        return $this->socialAccounts;
    }

    public function addSocialAccount(SocialAccount $socialAccount): self
    {
        if (!$this->socialAccounts->contains($socialAccount)) {
            $this->socialAccounts->add($socialAccount);
            $socialAccount->setUser($this);
        }

        return $this;
    }

    public function removeSocialAccount(SocialAccount $socialAccount): self
    {
        $this->socialAccounts->removeElement($socialAccount);

        return $this;
    }

    public function hasSocialAccount(string $provider): bool
    {
        foreach ($this->socialAccounts as $socialAccount) {
            if ($socialAccount->getProvider() === $provider) {
                return true;
            }
        }

        return false;
    }

    public function getUsernameChangedDatetime(): ?\DateTimeImmutable
    {
        return $this->usernameChangedDatetime;
    }

    public function setUsernameChangedDatetime(?\DateTimeImmutable $usernameChangedDatetime): self
    {
        $this->usernameChangedDatetime = $usernameChangedDatetime;

        return $this;
    }

    public function getProfile(): UserProfile
    {
        return $this->profile;
    }

    public function setProfile(UserProfile $profile): self
    {
        $this->profile = $profile;

        return $this;
    }

    public function getMusicianProfile(): ?MusicianProfile
    {
        return $this->musicianProfile;
    }

    public function setMusicianProfile(?MusicianProfile $musicianProfile): self
    {
        if ($musicianProfile !== null && $musicianProfile->getUser() !== $this) {
            $musicianProfile->setUser($this);
        }

        $this->musicianProfile = $musicianProfile;

        return $this;
    }

    public function getNotificationPreference(): ?UserNotificationPreference
    {
        return $this->notificationPreference;
    }

    public function setNotificationPreference(?UserNotificationPreference $notificationPreference): self
    {
        if ($notificationPreference !== null) {
            $notificationPreference->setUser($this);
        }

        $this->notificationPreference = $notificationPreference;

        return $this;
    }

    public function getTeacherProfile(): ?TeacherProfile
    {
        return $this->teacherProfile;
    }

    public function setTeacherProfile(?TeacherProfile $teacherProfile): self
    {
        if ($teacherProfile !== null && $teacherProfile->getUser() !== $this) {
            $teacherProfile->setUser($this);
        }

        $this->teacherProfile = $teacherProfile;

        return $this;
    }

    public function getDeletionDatetime(): ?\DateTimeImmutable
    {
        return $this->deletionDatetime;
    }

    public function setDeletionDatetime(?\DateTimeImmutable $deletionDatetime): self
    {
        $this->deletionDatetime = $deletionDatetime;

        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deletionDatetime !== null;
    }
}
