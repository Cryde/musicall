/**
 * Each kind of service a member can offer (#683, #1406), in the order « Ajouter un service » lists them: its icon,
 * its colours, its name and what it is (as full keys for the i18n check), and its public page.
 */
export const SERVICE_KINDS = {
  teaching: {
    icon: 'pi pi-graduation-cap',
    iconClass: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    labelKey: 'profile.services.kinds.teaching.label',
    descriptionKey: 'profile.services.kinds.teaching.description',
    page: (username) => ({ name: 'app_user_teacher_profile', params: { username } })
  }
}

/** How « Mes services » says what a service still needs before it can be published. */
export const SERVICE_PART_KEYS = {
  headline: 'profile.services.parts.headline',
  description: 'profile.services.parts.description',
  places: 'profile.services.parts.places',
  offers: 'profile.services.parts.offers'
}
