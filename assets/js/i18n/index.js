import { createI18n } from 'vue-i18n'
import fr from './fr.json' with { type: 'json' }

// French is the only catalogue until English is enabled (#1194). The resolved user locale (#1180)
// and the switcher (#1193) set `i18n.global.locale.value`, nothing else needs to change.
export const DEFAULT_LOCALE = 'fr'

export const i18n = createI18n({
  legacy: false,
  locale: DEFAULT_LOCALE,
  fallbackLocale: DEFAULT_LOCALE,
  messages: { fr }
})
