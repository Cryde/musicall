import { createI18n } from 'vue-i18n'
import fr from './fr/index.js'

// French is the only catalogue until English is enabled (#1194). The resolved user locale (#1180)
// and the switcher (#1193) set `i18n.global.locale.value`, nothing else needs to change.
export const DEFAULT_LOCALE = 'fr'

// vue-i18n's default rule puts 0 in the plural form (« 0 membres »); in French 0 and 1 are singular.
// A message with three forms (zero | one | many) is picked by count, capped at the last one.
export function frenchPluralIndex(choice, choicesLength) {
  const count = Math.abs(choice)
  if (choicesLength === 2) {
    return count < 2 ? 0 : 1
  }

  return Math.min(count, choicesLength - 1)
}

export const i18n = createI18n({
  legacy: false,
  locale: DEFAULT_LOCALE,
  fallbackLocale: DEFAULT_LOCALE,
  messages: { fr },
  pluralRules: { fr: frenchPluralIndex }
})
