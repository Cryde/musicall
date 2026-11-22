/**
 * Each kind of service a member can offer (#683, #1406), in the order « Ajouter un service » and the menus list them:
 * its icon, its colours, its name and what it is (as full keys for the i18n check), its public page and its search.
 *
 * A search with `testerOnly` is merged but not yet announced: its menu entries show to ROLE_TESTER accounts only.
 * Announcing it is setting it to false.
 */
export const SERVICE_KINDS = {
  teaching: {
    icon: 'pi pi-graduation-cap',
    iconClass: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    labelKey: 'profile.services.kinds.teaching.label',
    descriptionKey: 'profile.services.kinds.teaching.description',
    pageRoute: 'app_user_teacher_profile',
    search: { route: 'app_search_teacher', menuKey: 'menu.search_teachers', testerOnly: false }
  },
  sound_engineering: {
    icon: 'pi pi-sliders-v',
    iconClass: 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
    labelKey: 'profile.services.kinds.sound_engineering.label',
    descriptionKey: 'profile.services.kinds.sound_engineering.description',
    pageRoute: 'app_user_sound_engineer',
    search: {
      route: 'app_search_sound_engineer',
      menuKey: 'menu.search_sound_engineers',
      testerOnly: true
    }
  },
  photography: {
    icon: 'pi pi-camera',
    iconClass: 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
    labelKey: 'profile.services.kinds.photography.label',
    descriptionKey: 'profile.services.kinds.photography.description',
    pageRoute: 'app_user_photographer',
    search: {
      route: 'app_search_photographer',
      menuKey: 'menu.search_photographers',
      testerOnly: true
    }
  },
  video: {
    icon: 'pi pi-video',
    iconClass: 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
    labelKey: 'profile.services.kinds.video.label',
    descriptionKey: 'profile.services.kinds.video.description',
    pageRoute: 'app_user_videographer',
    search: {
      route: 'app_search_videographer',
      menuKey: 'menu.search_videographers',
      testerOnly: true
    }
  }
}

/** How « Mes services » says what a service still needs before it can be published. */
export const SERVICE_PART_KEYS = {
  headline: 'profile.services.parts.headline',
  description: 'profile.services.parts.description',
  specialities: 'profile.services.parts.specialities',
  places: 'profile.services.parts.places',
  offers: 'profile.services.parts.offers'
}
