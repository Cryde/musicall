<?php declare(strict_types=1);

namespace App\Service\Bot\Provider;

use App\Entity\User;
use App\Locale\LocalizedPath;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

/**
 * What the three member pages (/u/{username}, /musician, /teacher) share in their meta: whether the page may be
 * described at all, the member as a schema.org Person, and the page's address in the current locale.
 */
readonly class ProfileMeta
{
    private const string NOINDEX = 'noindex, follow';

    public function __construct(
        private TranslatorInterface $translator,
        private LocalizedPath $localizedPath,
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
        #[Autowire('%env(DEFAULT_URI)%')]
        private string $siteOrigin,
    ) {
    }

    /**
     * A page that does not exist is still served the app shell, so it says « noindex » rather than nothing.
     *
     * @return array{robots: string}
     */
    public function missing(): array
    {
        return ['robots' => self::NOINDEX];
    }

    /**
     * Nothing about an account that is gone, a bare card for a private profile: neither a share card nor a search
     * engine ever shows what the member hid.
     *
     * @return array{title?: string, description?: string, robots: string}|null the meta to use instead, or null when the page can be described
     */
    public function withheld(User $user): ?array
    {
        if (!$user->isPubliclyVisible()) {
            return $this->missing();
        }

        if (!$user->profile->isPublic) {
            return [
                'title' => $this->translator->trans('meta.profile.private_title'),
                'description' => $this->translator->trans('meta.profile.private_description'),
                'robots' => self::NOINDEX,
            ];
        }

        return null;
    }

    public function name(User $user): string
    {
        return $user->profile->displayName ?: $user->username;
    }

    /** The absolute address of a member page, in the current locale. */
    public function url(string $defaultLocalePath): string
    {
        return $this->siteOrigin . $this->localizedPath->inLocale($defaultLocalePath, $this->translator->getLocale());
    }

    public function picture(User $user): ?string
    {
        $picture = $user->profilePicture;
        if ($picture === null || ($path = $this->uploaderHelper->asset($picture, 'imageFile')) === null) {
            return null;
        }

        return $this->cacheManager->getBrowserPath($path, 'user_profile_picture_large');
    }

    /**
     * @param list<string> $knowsAbout
     *
     * @return array<string, mixed>
     */
    public function person(User $user, array $knowsAbout = []): array
    {
        $profile = $user->profile;

        return array_filter([
            '@type' => 'Person',
            'name' => $this->name($user),
            'alternateName' => '@' . $user->username,
            'url' => $this->url('/u/' . $user->username),
            'image' => $this->picture($user),
            'description' => $profile->bio ? $this->shorten($profile->bio) : null,
            'homeLocation' => $profile->location ? ['@type' => 'Place', 'name' => $profile->location] : null,
            'knowsAbout' => $knowsAbout ?: null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /** A description stays within what search results show, on one line. A no-break space (« 25 € ») stays. */
    public function shorten(string $text): string
    {
        $text = trim(preg_replace('/[ \t\r\n\f\v]+/', ' ', $text) ?? $text);

        return mb_strlen($text) > 155 ? rtrim(mb_substr($text, 0, 152)) . '...' : $text;
    }
}
