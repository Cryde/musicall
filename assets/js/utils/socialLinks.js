// PrimeIcons has no Spotify icon.
const PLATFORM_ICONS = {
  youtube: 'pi-youtube',
  soundcloud: 'pi-cloud',
  instagram: 'pi-instagram',
  facebook: 'pi-facebook',
  twitter: 'pi-twitter',
  tiktok: 'pi-tiktok',
  spotify: 'pi-headphones',
  bandcamp: 'pi-headphones',
  website: 'pi-globe'
}

/** The icon of a member's link elsewhere, to put after `pi`. */
export function socialPlatformIcon(platform) {
  return PLATFORM_ICONS[platform] ?? 'pi-link'
}
