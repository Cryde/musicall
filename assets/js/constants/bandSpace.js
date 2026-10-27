import { i18n } from '../i18n/index.js'

export const LAST_BAND_SPACE_KEY = 'lastBandSpaceId'
export const CREATE_ACTION_ID = '__create__'

export const BAND_SPACE_ROUTES = {
  INDEX: 'app_band_index',
  DASHBOARD: 'app_band_dashboard',
  AGENDA: 'app_band_agenda',
  CHAT: 'app_band_chat',
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

/** Whether the tech rider PDF preview stays docked open on a wide screen. */
export const TECH_RIDER_PREVIEW_KEY = 'techRiderPreview'

/**
 * Tech riders are merged but not yet announced, so the module is hidden from everyone except
 * accounts carrying ROLE_TESTER.
 *
 * RELEASING THE MODULE IS THIS ONE FLIP: set it to false. Both the sidebar entry and the
 * route guard read it, so nothing else needs touching. It is presentation only, the API
 * has always been open, so this is a curtain rather than a permission.
 *
 * A tester is an ordinary user with a flag, not a rank, so the preview can go to a band member
 * without also handing them the back office. An admin does not see the module unless they are also
 * a tester (#942).
 */
export const RIDER_TESTER_ONLY = true

/**
 * The modules a Band Space is described by, shared between the public presentation page and the
 * empty state a member lands on with no space, so the pitch cannot drift between the two.
 *
 * Distinct from NAVIGATION_ITEMS, which is the in-app sidebar: this list sells the module, that one
 * routes to it. Dashboard is absent because nobody joins for a dashboard, and tech riders are absent
 * because RIDER_TESTER_ONLY still hides the module from members. Never advertise what a visitor
 * would not find after signing up.
 *
 * Each description says what stops being scattered, which is the whole argument for the module.
 */
export const BAND_SPACE_MODULES = Object.freeze([
  {
    key: 'agenda',
    icon: 'pi pi-calendar',
    get label() {
      return i18n.global.t('band_space.module_name.agenda')
    },
    get description() {
      return i18n.global.t('band_space.module_description.agenda')
    }
  },
  {
    key: 'taches',
    icon: 'pi pi-check-square',
    get label() {
      return i18n.global.t('band_space.module_name.tasks')
    },
    get description() {
      return i18n.global.t('band_space.module_description.tasks')
    }
  },
  {
    key: 'notes',
    icon: 'pi pi-file-edit',
    get label() {
      return i18n.global.t('band_space.module_name.notes')
    },
    get description() {
      return i18n.global.t('band_space.module_description.notes')
    }
  },
  {
    key: 'setlists',
    icon: 'pi pi-list',
    get label() {
      return i18n.global.t('band_space.module_name.setlist')
    },
    get description() {
      return i18n.global.t('band_space.module_description.setlists')
    }
  },
  {
    key: 'files',
    icon: 'pi pi-folder-open',
    get label() {
      return i18n.global.t('band_space.module_name.files')
    },
    get description() {
      return i18n.global.t('band_space.module_description.files')
    }
  },
  {
    key: 'finances',
    icon: 'pi pi-wallet',
    get label() {
      return i18n.global.t('band_space.module_name.finance')
    },
    get description() {
      return i18n.global.t('band_space.module_description.finances')
    }
  }
])

export const SECTION_NAMES = {
  get [BAND_SPACE_ROUTES.DASHBOARD]() {
    return i18n.global.t('band_space.module_name.dashboard')
  },
  get [BAND_SPACE_ROUTES.AGENDA]() {
    return i18n.global.t('band_space.module_name.agenda')
  },
  get [BAND_SPACE_ROUTES.CHAT]() {
    return i18n.global.t('band_space.module_name.chat')
  },
  get [BAND_SPACE_ROUTES.NOTES]() {
    return i18n.global.t('band_space.module_name.notes')
  },
  get [BAND_SPACE_ROUTES.FILES]() {
    return i18n.global.t('band_space.module_name.files')
  },
  get [BAND_SPACE_ROUTES.TASKS]() {
    return i18n.global.t('band_space.module_name.tasks')
  },
  get [BAND_SPACE_ROUTES.FINANCE]() {
    return i18n.global.t('band_space.module_name.finance')
  },
  get [BAND_SPACE_ROUTES.SETLIST]() {
    return i18n.global.t('band_space.module_name.setlist')
  },
  get [BAND_SPACE_ROUTES.RIDER]() {
    return i18n.global.t('band_space.module_name.rider')
  },
  get [BAND_SPACE_ROUTES.PARAMETERS]() {
    return i18n.global.t('band_space.module_name.parameters')
  },
  get [BAND_SPACE_ROUTES.INDEX]() {
    return i18n.global.t('band_space.module_name.band_space')
  }
}

/**
 * The « Paramètres » page tabs, in display order. `adminOnly` is the only gate, and nothing carries
 * it today: leaving the space, the stage name and instruments dialog, the roster, the activity log
 * and the storage quota are all member level, which is why the sidebar entry is shown to every
 * member and not only to admins.
 */
export const BAND_SPACE_SETTINGS_SECTIONS = Object.freeze([
  {
    key: 'members',
    get label() {
      return i18n.global.t('band_space.settings.section.members')
    },
    adminOnly: false
  },
  {
    key: 'activity',
    get label() {
      return i18n.global.t('band_space.settings.section.activity')
    },
    adminOnly: false
  },
  {
    key: 'shares',
    get label() {
      return i18n.global.t('band_space.settings.section.shares')
    },
    adminOnly: false
  },
  {
    key: 'calendar',
    get label() {
      return i18n.global.t('band_space.settings.section.calendar')
    },
    adminOnly: false
  },
  {
    key: 'storage',
    get label() {
      return i18n.global.t('band_space.settings.section.storage')
    },
    adminOnly: false
  },
  {
    key: 'general',
    get label() {
      return i18n.global.t('band_space.settings.section.general')
    },
    adminOnly: false
  },
  {
    key: 'danger',
    get label() {
      return i18n.global.t('band_space.settings.section.danger')
    },
    adminOnly: false
  }
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
  {
    get label() {
      return i18n.global.t('band_space.module_name.dashboard')
    },
    route: BAND_SPACE_ROUTES.DASHBOARD,
    icon: 'pi-th-large'
  },
  {
    get label() {
      return i18n.global.t('band_space.module_name.agenda')
    },
    route: BAND_SPACE_ROUTES.AGENDA,
    icon: 'pi-calendar'
  },
  {
    get label() {
      return i18n.global.t('band_space.module_name.chat')
    },
    route: BAND_SPACE_ROUTES.CHAT,
    icon: 'pi-comments'
  },
  {
    get label() {
      return i18n.global.t('band_space.module_name.notes')
    },
    route: BAND_SPACE_ROUTES.NOTES,
    icon: 'pi-file-edit'
  },
  {
    get label() {
      return i18n.global.t('band_space.module_name.files')
    },
    route: BAND_SPACE_ROUTES.FILES,
    icon: 'pi-folder'
  },
  {
    get label() {
      return i18n.global.t('band_space.module_name.setlist')
    },
    route: BAND_SPACE_ROUTES.SETLIST,
    icon: 'pi-list'
  },
  {
    get label() {
      return i18n.global.t('band_space.module_name.rider')
    },
    route: BAND_SPACE_ROUTES.RIDER,
    icon: 'pi-sliders-h',
    testerOnly: RIDER_TESTER_ONLY
  },
  {
    get label() {
      return i18n.global.t('band_space.module_name.tasks')
    },
    route: BAND_SPACE_ROUTES.TASKS,
    icon: 'pi-check-square'
  },
  {
    get label() {
      return i18n.global.t('band_space.module_name.finance')
    },
    route: BAND_SPACE_ROUTES.FINANCE,
    icon: 'pi-wallet'
  },
  {
    get label() {
      return i18n.global.t('band_space.module_name.parameters')
    },
    route: BAND_SPACE_ROUTES.PARAMETERS,
    icon: 'pi-cog'
  }
])
