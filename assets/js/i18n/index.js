import { createI18n } from 'vue-i18n'
import fr from './fr/index.js'

// French is bundled here. Switching locale goes through setLocale() in locale.js, which loads any
// other catalogue on demand: the page's own locale at boot, then the switcher (#1193).
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
  // Falling back to French is expected while English is incomplete, so no warning per key.
  fallbackWarn: false,
  messages: { fr },
  pluralRules: { fr: frenchPluralIndex }
})
