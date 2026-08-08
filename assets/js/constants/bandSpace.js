export const LAST_BAND_SPACE_KEY = 'lastBandSpaceId'
export const CREATE_ACTION_ID = '__create__'

export const BAND_SPACE_ROUTES = {
  INDEX: 'app_band_index',
  DASHBOARD: 'app_band_dashboard',
  AGENDA: 'app_band_agenda',
  NOTES: 'app_band_notes',
  FILES: 'app_band_files',
  TASKS: 'app_band_tasks',
  FINANCE: 'app_band_finance',
  SETLIST: 'app_band_setlist',
  RIDER: 'app_band_rider',
  PARAMETERS: 'app_band_parameters'
}

/** Per band space, so each band comes back to the rider it was last looking at. */
export const LAST_TECH_RIDER_KEY = 'lastTechRiderId'

/**
 * Tech riders are merged but not yet announced, so the module is hidden from everyone
 * except super admins.
 *
 * RELEASING THE MODULE IS THIS ONE FLIP: set it to false. Both the sidebar entry and the
 * route guard read it, so nothing else needs touching. It is presentation only, the API
 * has always been open, so this is a curtain rather than a permission.
 */
export const RIDER_SUPER_ADMIN_ONLY = true

export const SECTION_NAMES = {
  [BAND_SPACE_ROUTES.DASHBOARD]: 'Dashboard',
  [BAND_SPACE_ROUTES.AGENDA]: 'Agenda',
  [BAND_SPACE_ROUTES.NOTES]: 'Notes',
  [BAND_SPACE_ROUTES.FILES]: 'Fichiers',
  [BAND_SPACE_ROUTES.TASKS]: 'Tâches',
  [BAND_SPACE_ROUTES.FINANCE]: 'Finances',
  [BAND_SPACE_ROUTES.SETLIST]: 'Setlists',
  [BAND_SPACE_ROUTES.RIDER]: 'Tech riders',
  [BAND_SPACE_ROUTES.PARAMETERS]: 'Paramètres',
  [BAND_SPACE_ROUTES.INDEX]: 'Band Space'
}

/**
 * The « Paramètres » page tabs, in display order. `adminOnly` is the only gate, and nothing carries
 * it today: leaving the space, the stage name and instruments dialog, the roster, the activity log
 * and the storage quota are all member level, which is why the sidebar entry is shown to every
 * member and not only to admins.
 */
export const BAND_SPACE_SETTINGS_SECTIONS = Object.freeze([
  { key: 'members', label: 'Membres', adminOnly: false },
  { key: 'activity', label: "Journal d'activité", adminOnly: false },
  { key: 'shares', label: 'Partages actifs', adminOnly: false },
  { key: 'storage', label: 'Stockage', adminOnly: false },
  { key: 'general', label: 'Général', adminOnly: false },
  { key: 'danger', label: 'Zone de danger', adminOnly: false }
])

/**
 * Takes the sections rather than reading the constant directly, so the exclusion branch stays
 * provable the day a section is finally flagged `adminOnly`. Filtering the frozen constant here
 * would leave an inverted predicate undetectable for as long as nothing carries the flag.
 */
export function filterSectionsByRole(sections, isAdmin) {
  return sections.filter((section) => !section.adminOnly || isAdmin)
}

export function visibleSettingsSections(isAdmin) {
  return filterSectionsByRole(BAND_SPACE_SETTINGS_SECTIONS, isAdmin)
}

/**
 * The section to show for a `?section=` value: the requested one when the role may see it, the
 * first visible one otherwise. Every entry point goes through here (the deep links from the
 * deletion banner and the dashboard activity widget, and the re-check when the role finally
 * arrives with the space list), so a member can never land on a tab reserved for admins.
 */
export function resolveSettingsSection(requestedKey, isAdmin) {
  const sections = visibleSettingsSections(isAdmin)

  return sections.find((section) => section.key === requestedKey)?.key ?? sections[0]?.key ?? null
}

export const NAVIGATION_ITEMS = Object.freeze([
  { label: 'Dashboard', route: BAND_SPACE_ROUTES.DASHBOARD, icon: 'pi-th-large' },
  { label: 'Agenda', route: BAND_SPACE_ROUTES.AGENDA, icon: 'pi-calendar' },
  { label: 'Notes', route: BAND_SPACE_ROUTES.NOTES, icon: 'pi-file-edit' },
  { label: 'Fichiers', route: BAND_SPACE_ROUTES.FILES, icon: 'pi-folder' },
  { label: 'Setlists', route: BAND_SPACE_ROUTES.SETLIST, icon: 'pi-list' },
  {
    label: 'Tech riders',
    route: BAND_SPACE_ROUTES.RIDER,
    icon: 'pi-sliders-h',
    superAdminOnly: RIDER_SUPER_ADMIN_ONLY
  },
  { label: 'Tâches', route: BAND_SPACE_ROUTES.TASKS, icon: 'pi-check-square' },
  { label: 'Finances', route: BAND_SPACE_ROUTES.FINANCE, icon: 'pi-wallet' },
  { label: 'Paramètres', route: BAND_SPACE_ROUTES.PARAMETERS, icon: 'pi-cog' }
])
