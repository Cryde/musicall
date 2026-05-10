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
    public string $id;

    #[Assert\NotBlank(message: 'Veuillez saisir un nom d\'utilisateur')]
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    #[Groups([Comment::ITEM, Comment::LIST, Publication::ITEM, Publication::LIST, MessageThreadMeta::LIST, User::ITEM, Message::LIST, Message::ITEM, Gallery::LIST])]
    public string $username;

    #[Assert\NotBlank(message: 'Veuillez saisir un email')]
    #[Assert\Email(message: 'Email invalide')]
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    public string $email;

    #[Assert\NotBlank(message: 'Veuillez saisir un mot de passe')]
    #[Assert\Length(min: 3, minMessage: 'Le mot de passe doit au moins contenir 3 caractères')]
    public ?string $plainPassword = null;

    /** @var string[] */
    #[ORM\Column(type: Types::JSON)]
    public array $roles = [];

    #[ORM\Column(type: Types::STRING, nullable: true)]
    public ?string $password = null;

    /**
     * @var Collection<int, Publication>
     */
    #[ORM\OneToMany(targetEntity: Publication::class, mappedBy: "author", orphanRemoval: true)]
    public Collection $publications;

    /**
     * @var Collection<int, SocialAccount>
     */
    #[ORM\OneToMany(targetEntity: SocialAccount::class, mappedBy: 'user', orphanRemoval: true)]
    public Collection $socialAccounts;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    public DateTime $creationDatetime;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $lastLoginDatetime = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    public ?int $oldId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $confirmationDatetime = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $token = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?DateTimeInterface $resetRequestDatetime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $usernameChangedDatetime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups([Comment::ITEM, Comment::LIST, Publication::ITEM, Publication::LIST, MessageThreadMeta::LIST, User::ITEM, Message::LIST, Message::ITEM, Gallery::LIST])]
    public ?\DateTimeImmutable $deletionDatetime = null;

    #[ORM\OneToOne(targetEntity: UserProfilePicture::class, cascade: ['persist', 'remove'])]
    #[Groups([Comment::ITEM, Comment::LIST, MessageThreadMeta::LIST, User::ITEM])]
    public ?UserProfilePicture $profilePicture = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    public UserProfile $profile;

    #[ORM\OneToOne(targetEntity: MusicianProfile::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    public ?MusicianProfile $musicianProfile = null {
        set(?MusicianProfile $value) {
            if ($value instanceof \App\Entity\Musician\MusicianProfile && $value->user !== $this) {
                $value->user = $this;
            }
            $this->musicianProfile = $value;
        }
    }

    #[ORM\OneToOne(targetEntity: UserNotificationPreference::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    public ?UserNotificationPreference $notificationPreference = null {
        set(?UserNotificationPreference $value) {
            if ($value instanceof \App\Entity\User\UserNotificationPreference) {
                $value->user = $this;
            }
            $this->notificationPreference = $value;
        }
    }

    #[ORM\OneToOne(targetEntity: TeacherProfile::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    public ?TeacherProfile $teacherProfile = null {
        set(?TeacherProfile $value) {
            if ($value instanceof \App\Entity\Teacher\TeacherProfile && $value->user !== $this) {
                $value->user = $this;
            }
            $this->teacherProfile = $value;
        }
    }

    public function __construct()
    {
        $this->publications = new ArrayCollection();
        $this->socialAccounts = new ArrayCollection();
        $this->creationDatetime = new DateTime();
    }

    public function getUserIdentifier(): string
    {
        assert($this->username !== '');

        return $this->username;
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

    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
    }

    /**
     * @return array{id: string, username: string, password: ?string, roles: array<string>}
     */
    public function __serialize(): array
    {
        // try to remove that once we remove all the normalizer / denormalizer
        return [
            'id' => $this->id,
            'username' => $this->username,
            'password' => $this->password,
            'roles' => $this->roles,
        ];
    }

    /**
     * @param array{id: string, username: string, password: ?string, roles: array<string>} $data
     */
    public function __unserialize(array $data): void
    {
        $this->id = $data['id'];
        $this->username = $data['username'];
        $this->password = $data['password'];
        $this->roles = $data['roles'];
    }

    public function addPublication(Publication $publication): self
    {
        if (!$this->publications->contains($publication)) {
            $this->publications[] = $publication;
            $publication->author = $this;
        }

        return $this;
    }

    public function removePublication(Publication $publication): self
    {
        $this->publications->removeElement($publication);

        return $this;
    }

    public function addSocialAccount(SocialAccount $socialAccount): self
    {
        if (!$this->socialAccounts->contains($socialAccount)) {
            $this->socialAccounts->add($socialAccount);
            $socialAccount->user = $this;
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
            if ($socialAccount->provider === $provider) {
                return true;
            }
        }

        return false;
    }

    public function isDeleted(): bool
    {
        return $this->deletionDatetime instanceof \DateTimeImmutable;
    }
}
