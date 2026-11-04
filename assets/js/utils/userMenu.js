/**
 * The signed-in member's own pages, as the avatar menu, the phone menu and the Band Space drawer list
 * them, so the three cannot drift apart. Labels are catalogue keys, translated where they are shown.
 */
export function userMenuEntries({ username, isAdmin }) {
  const entries = [
    {
      key: 'profile',
      labelKey: 'user_menu.profile',
      icon: 'pi pi-user',
      to: { name: 'app_user_public_profile', params: { username } }
    },
    {
      key: 'announces',
      labelKey: 'user_menu.announces',
      icon: 'pi pi-megaphone',
      to: { name: 'app_user_announces' }
    },
    {
      key: 'publications',
      labelKey: 'user_menu.publications',
      icon: 'pi pi-file-edit',
      to: { name: 'app_user_publications' }
    },
    {
      key: 'courses',
      labelKey: 'user_menu.courses',
      icon: 'pi pi-book',
      to: { name: 'app_user_courses' }
    },
    {
      key: 'photos',
      labelKey: 'user_menu.photos',
      icon: 'pi pi-images',
      to: { name: 'app_user_galleries' }
    },
    {
      key: 'settings',
      labelKey: 'user_menu.settings',
      icon: 'pi pi-cog',
      to: { name: 'app_user_settings' }
    }
  ]

  if (isAdmin) {
    entries.push({
      key: 'admin',
      labelKey: 'user_menu.admin',
      icon: 'pi pi-shield',
      to: { name: 'admin_dashboard' }
    })
  }

  return entries
}

/** A band's mark carries its first letter, so two bands side by side never look alike. */
export function bandInitial(name) {
  const [first = ''] = Array.from((name ?? '').trim())
  return first.toLocaleUpperCase()
}
