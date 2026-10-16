import { i18n } from '../i18n/index.js'
import { FEEDBACK_MODULES } from '../utils/feedbackModule.js'

// Labels are getters so they are translated when shown, in whatever language is active then.
function option(value, labelKey) {
  return {
    value,
    get label() {
      return i18n.global.t(labelKey)
    }
  }
}

function group(labelKey, items) {
  return {
    get label() {
      return i18n.global.t(labelKey)
    },
    items
  }
}

/**
 * Mirrors `App\ApiResource\Feedback\FeedbackResource`. The picker disables the send button below it,
 * so the user is told before the round trip rather than by a 422.
 */
export const MESSAGE_MIN_LENGTH = 10
export const MESSAGE_MAX_LENGTH = 2000

/** Mirrors `App\Enum\Feedback\FeedbackType`, labels included. */
export const FEEDBACK_TYPE_OPTIONS = Object.freeze([
  option('bug', 'shared.feedback.type.bug'),
  option('suggestion', 'shared.feedback.type.suggestion'),
  option('question', 'shared.feedback.type.question'),
  option('other', 'shared.feedback.type.other')
])

/**
 * Mirrors `App\Enum\Feedback\FeedbackModule`, split into the Band Space modules and the rest of
 * the site. The grouping is presentation only, which is why it lives here and not on the PHP enum.
 *
 * Seventeen options is a lot for a dropdown, which is why the drawer prefills it from the route:
 * most people never open this list, and the ones who do are correcting a wrong guess.
 */
export const FEEDBACK_MODULE_GROUPS = Object.freeze([
  group('shared.feedback.module_group.band_space', [
    option(FEEDBACK_MODULES.DASHBOARD, 'shared.feedback.module.dashboard'),
    option(FEEDBACK_MODULES.AGENDA, 'shared.feedback.module.agenda'),
    option(FEEDBACK_MODULES.CHAT, 'shared.feedback.module.chat'),
    option(FEEDBACK_MODULES.NOTES, 'shared.feedback.module.notes'),
    option(FEEDBACK_MODULES.FILE, 'shared.feedback.module.file'),
    option(FEEDBACK_MODULES.SETLIST, 'shared.feedback.module.setlist'),
    option(FEEDBACK_MODULES.RIDER, 'shared.feedback.module.rider'),
    option(FEEDBACK_MODULES.TASK, 'shared.feedback.module.task'),
    option(FEEDBACK_MODULES.FINANCE, 'shared.feedback.module.finance'),
    option(FEEDBACK_MODULES.SETTINGS, 'shared.feedback.module.settings')
  ]),
  group('shared.feedback.module_group.site', [
    option(FEEDBACK_MODULES.FORUM, 'shared.feedback.module.forum'),
    option(FEEDBACK_MODULES.PUBLICATION, 'shared.feedback.module.publication'),
    option(FEEDBACK_MODULES.GALLERY, 'shared.feedback.module.gallery'),
    option(FEEDBACK_MODULES.DIRECTORY, 'shared.feedback.module.directory'),
    option(FEEDBACK_MODULES.COURSE, 'shared.feedback.module.course'),
    option(FEEDBACK_MODULES.MESSAGE, 'shared.feedback.module.message'),
    option(FEEDBACK_MODULES.NOTIFICATION, 'shared.feedback.module.notification'),
    option(FEEDBACK_MODULES.ACCOUNT, 'shared.feedback.module.account'),
    option(FEEDBACK_MODULES.OTHER, 'shared.feedback.module.other')
  ])
])
