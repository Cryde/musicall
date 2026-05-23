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
  PARAMETERS: 'app_band_parameters'
}

export const SECTION_NAMES = {
  [BAND_SPACE_ROUTES.DASHBOARD]: 'Dashboard',
  [BAND_SPACE_ROUTES.AGENDA]: 'Agenda',
  [BAND_SPACE_ROUTES.NOTES]: 'Notes',
  [BAND_SPACE_ROUTES.FILES]: 'Fichiers',
  [BAND_SPACE_ROUTES.TASKS]: 'Tâches',
  [BAND_SPACE_ROUTES.FINANCE]: 'Finances',
  [BAND_SPACE_ROUTES.SETLIST]: 'Setlists',
  [BAND_SPACE_ROUTES.PARAMETERS]: 'Paramètres',
  [BAND_SPACE_ROUTES.INDEX]: 'Band Space'
}

export const NAVIGATION_ITEMS = Object.freeze([
  { label: 'Dashboard', route: BAND_SPACE_ROUTES.DASHBOARD, icon: 'pi-th-large' },
  { label: 'Agenda', route: BAND_SPACE_ROUTES.AGENDA, icon: 'pi-calendar' },
  { label: 'Notes', route: BAND_SPACE_ROUTES.NOTES, icon: 'pi-file-edit' },
  { label: 'Fichiers', route: BAND_SPACE_ROUTES.FILES, icon: 'pi-folder' },
  { label: 'Setlists', route: BAND_SPACE_ROUTES.SETLIST, icon: 'pi-list' },
  { label: 'Tâches', route: BAND_SPACE_ROUTES.TASKS, icon: 'pi-check-square' },
  { label: 'Finances', route: BAND_SPACE_ROUTES.FINANCE, icon: 'pi-wallet' },
  { label: 'Paramètres', route: BAND_SPACE_ROUTES.PARAMETERS, icon: 'pi-cog' }
])
