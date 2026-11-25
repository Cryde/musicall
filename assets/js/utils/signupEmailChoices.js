import { NOTIFICATION_DEFAULTS } from '../constants/notificationSettings.js'
import { SIGNUP_EMAIL_GROUPS } from '../constants/signupEmailChoices.js'

const SIGNUP_EMAIL_SETTINGS = SIGNUP_EMAIL_GROUPS.flatMap((group) =>
  group.rows.map((row) => row.setting)
)

/** The choice a new member starts with, which is what they get without ever opening the box. */
export function defaultEmailChoices() {
  return Object.fromEntries(
    SIGNUP_EMAIL_SETTINGS.map((setting) => [setting, NOTIFICATION_DEFAULTS[setting]])
  )
}

/**
 * What the sign-up box says about a choice: `default`, `all`, `none`, or `some` with how many of how
 * many are checked. The defaults win over a count, as they are what most members keep.
 */
export function emailChoicesSummary(choices) {
  const entries = Object.entries(choices)
  const checked = entries.filter(([, value]) => value).length
  const total = entries.length

  return { kind: summaryKind(entries, checked, total), checked, total }
}

function summaryKind(entries, checked, total) {
  if (entries.every(([setting, value]) => value === NOTIFICATION_DEFAULTS[setting])) {
    return 'default'
  }
  if (checked === total) {
    return 'all'
  }
  if (checked === 0) {
    return 'none'
  }

  return 'some'
}

/** The same emails, every one of them checked or unchecked. */
export function withEveryEmail(choices, checked) {
  return Object.fromEntries(Object.keys(choices).map((setting) => [setting, checked]))
}
