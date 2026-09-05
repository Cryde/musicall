import { FEEDBACK_MODULES } from '../utils/feedbackModule.js'

/**
 * Mirrors `App\ApiResource\Feedback\FeedbackResource`. The picker disables the send button below it,
 * so the user is told before the round trip rather than by a 422.
 */
export const MESSAGE_MIN_LENGTH = 10
export const MESSAGE_MAX_LENGTH = 2000

/** Mirrors `App\Enum\Feedback\FeedbackType`, labels included. */
export const FEEDBACK_TYPE_OPTIONS = Object.freeze([
  { value: 'bug', label: 'Bug' },
  { value: 'suggestion', label: 'Suggestion' },
  { value: 'question', label: 'Question' },
  { value: 'other', label: 'Autre' }
])

/**
 * Mirrors `App\Enum\Feedback\FeedbackModule`, split into the Band Space modules and the rest of
 * the site. The grouping is presentation only, which is why it lives here and not on the PHP enum.
 *
 * Seventeen options is a lot for a dropdown, which is why the drawer prefills it from the route:
 * most people never open this list, and the ones who do are correcting a wrong guess.
 */
export const FEEDBACK_MODULE_GROUPS = Object.freeze([
  {
    label: 'Band Space',
    items: [
      { value: FEEDBACK_MODULES.DASHBOARD, label: 'Dashboard du Band Space' },
      { value: FEEDBACK_MODULES.AGENDA, label: 'Agenda' },
      { value: FEEDBACK_MODULES.NOTES, label: 'Notes' },
      { value: FEEDBACK_MODULES.FILE, label: 'Fichiers' },
      { value: FEEDBACK_MODULES.SETLIST, label: 'Setlists' },
      { value: FEEDBACK_MODULES.RIDER, label: 'Tech riders' },
      { value: FEEDBACK_MODULES.TASK, label: 'Tâches' },
      { value: FEEDBACK_MODULES.FINANCE, label: 'Finances' },
      { value: FEEDBACK_MODULES.SETTINGS, label: 'Paramètres du Band Space' }
    ]
  },
  {
    label: 'Le site',
    items: [
      { value: FEEDBACK_MODULES.FORUM, label: 'Forum' },
      { value: FEEDBACK_MODULES.PUBLICATION, label: 'Publications' },
      { value: FEEDBACK_MODULES.GALLERY, label: 'Galeries photo' },
      { value: FEEDBACK_MODULES.DIRECTORY, label: 'Annuaire et recherche' },
      { value: FEEDBACK_MODULES.COURSE, label: 'Cours' },
      { value: FEEDBACK_MODULES.MESSAGE, label: 'Messagerie' },
      { value: FEEDBACK_MODULES.NOTIFICATION, label: 'Notifications' },
      { value: FEEDBACK_MODULES.ACCOUNT, label: 'Mon compte' },
      { value: FEEDBACK_MODULES.OTHER, label: 'Autre' }
    ]
  }
])
