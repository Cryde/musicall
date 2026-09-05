import { BAND_SPACE_ROUTES } from '../constants/bandSpace.js'

/**
 * Mirrors `App\Enum\Feedback\FeedbackModule`. The API refuses anything else, so a value added here
 * without its PHP counterpart is a 422 rather than a silent miss.
 */
export const FEEDBACK_MODULES = Object.freeze({
  AGENDA: 'agenda',
  NOTES: 'notes',
  FILE: 'file',
  TASK: 'task',
  FINANCE: 'finance',
  SETLIST: 'setlist',
  RIDER: 'rider',
  SETTINGS: 'settings',
  DASHBOARD: 'dashboard',
  FORUM: 'forum',
  PUBLICATION: 'publication',
  GALLERY: 'gallery',
  DIRECTORY: 'directory',
  COURSE: 'course',
  MESSAGE: 'message',
  NOTIFICATION: 'notification',
  ACCOUNT: 'account',
  OTHER: 'other'
})

/**
 * Route name to module. The Band Space half reads from BAND_SPACE_ROUTES rather than retyping the
 * names, so renaming a route there cannot silently drop a mapping here.
 *
 * A route absent from this map falls back to OTHER, which is why the map does not have to enumerate
 * every route in the app: only the ones whose module is worth prefilling.
 */
const ROUTE_TO_MODULE = Object.freeze({
  [BAND_SPACE_ROUTES.AGENDA]: FEEDBACK_MODULES.AGENDA,
  [BAND_SPACE_ROUTES.NOTES]: FEEDBACK_MODULES.NOTES,
  [BAND_SPACE_ROUTES.FILES]: FEEDBACK_MODULES.FILE,
  [BAND_SPACE_ROUTES.TASKS]: FEEDBACK_MODULES.TASK,
  [BAND_SPACE_ROUTES.FINANCE]: FEEDBACK_MODULES.FINANCE,
  [BAND_SPACE_ROUTES.SETLIST]: FEEDBACK_MODULES.SETLIST,
  app_band_setlist_live: FEEDBACK_MODULES.SETLIST,
  [BAND_SPACE_ROUTES.RIDER]: FEEDBACK_MODULES.RIDER,
  [BAND_SPACE_ROUTES.PARAMETERS]: FEEDBACK_MODULES.SETTINGS,
  [BAND_SPACE_ROUTES.DASHBOARD]: FEEDBACK_MODULES.DASHBOARD,
  [BAND_SPACE_ROUTES.INDEX]: FEEDBACK_MODULES.DASHBOARD,

  app_forum_index: FEEDBACK_MODULES.FORUM,
  app_forum_my_topics: FEEDBACK_MODULES.FORUM,
  forum_topic_list: FEEDBACK_MODULES.FORUM,
  forum_topic_item: FEEDBACK_MODULES.FORUM,

  app_publications: FEEDBACK_MODULES.PUBLICATION,
  app_publications_by_category: FEEDBACK_MODULES.PUBLICATION,
  app_publication_show: FEEDBACK_MODULES.PUBLICATION,
  app_publication_tag: FEEDBACK_MODULES.PUBLICATION,
  app_user_publications: FEEDBACK_MODULES.PUBLICATION,
  app_user_publication_edit: FEEDBACK_MODULES.PUBLICATION,
  app_user_publication_preview: FEEDBACK_MODULES.PUBLICATION,

  app_gallery_show: FEEDBACK_MODULES.GALLERY,
  app_user_galleries: FEEDBACK_MODULES.GALLERY,
  app_user_gallery_edit: FEEDBACK_MODULES.GALLERY,
  app_user_gallery_preview: FEEDBACK_MODULES.GALLERY,

  app_search_musician: FEEDBACK_MODULES.DIRECTORY,
  app_search_guitarist: FEEDBACK_MODULES.DIRECTORY,
  app_search_bassist: FEEDBACK_MODULES.DIRECTORY,
  app_search_drummer: FEEDBACK_MODULES.DIRECTORY,
  app_search_pianist: FEEDBACK_MODULES.DIRECTORY,
  app_search_singer: FEEDBACK_MODULES.DIRECTORY,
  app_search_teacher: FEEDBACK_MODULES.DIRECTORY,
  app_user_announces: FEEDBACK_MODULES.DIRECTORY,
  app_user_public_profile: FEEDBACK_MODULES.DIRECTORY,
  app_user_musician_profile: FEEDBACK_MODULES.DIRECTORY,
  app_user_teacher_profile: FEEDBACK_MODULES.DIRECTORY,

  app_course: FEEDBACK_MODULES.COURSE,
  app_course_by_category: FEEDBACK_MODULES.COURSE,
  app_course_show: FEEDBACK_MODULES.COURSE,
  app_user_courses: FEEDBACK_MODULES.COURSE,

  app_messages: FEEDBACK_MODULES.MESSAGE,
  app_notifications_index: FEEDBACK_MODULES.NOTIFICATION,

  app_login: FEEDBACK_MODULES.ACCOUNT,
  app_register: FEEDBACK_MODULES.ACCOUNT,
  app_forgot_password: FEEDBACK_MODULES.ACCOUNT,
  app_reset_password: FEEDBACK_MODULES.ACCOUNT,
  app_verify_email: FEEDBACK_MODULES.ACCOUNT
})

/**
 * The module to preselect for the page the user is on.
 *
 * Settings routes are matched by prefix rather than listed: there are eight of them, they all share
 * one module, and new ones keep appearing.
 */
export function resolveFeedbackModule(routeName) {
  if (typeof routeName !== 'string' || routeName === '') {
    return FEEDBACK_MODULES.OTHER
  }
  if (routeName.startsWith('app_user_settings')) {
    return FEEDBACK_MODULES.ACCOUNT
  }
  return ROUTE_TO_MODULE[routeName] ?? FEEDBACK_MODULES.OTHER
}
