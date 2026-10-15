import { fr } from 'date-fns/locale'
import { DEFAULT_LOCALE, i18n } from './index.js'

// What each app locale formats dates, numbers and amounts with: the BCP 47 tag for Intl, toLocale*
// and localeCompare, and the date-fns locale. `fr-FR` and `fr` format identically, so one tag serves.
// A locale enabled without an entry here formats in French.
const FORMATS = {
  fr: { tag: 'fr-FR', dateFns: fr }
}

/** The entry of a table keyed by app locale (`fr`, ...) for the active vue-i18n locale. */
export function forActiveLocale(table) {
  return table[i18n.global.locale.value] ?? table[DEFAULT_LOCALE]
}

/** The BCP 47 tag to hand to Intl, toLocale* and localeCompare. */
export function intlLocale() {
  return forActiveLocale(FORMATS).tag
}

/** The locale object to hand to date-fns. */
export function dateFnsLocale() {
  return forActiveLocale(FORMATS).dateFns
}
