import { intlLocale } from '../i18n/formatLocale.js'

/**
 * The spoken languages an announce can welcome (#1198), mirroring MusicianAnnounce::LANGUAGES on the
 * server: French and English, plus Dutch and German for Belgium.
 */
export const ANNOUNCE_LANGUAGES = ['fr', 'en', 'nl', 'de']

/** A language's name as the interface writes it in a sentence: « anglais », "English". */
export function languageName(code, locale = intlLocale()) {
  return new Intl.DisplayNames([locale], { type: 'language' }).of(code)
}

/** A language's name as a label on its own, capitalised: « Anglais », "English". */
export function languageLabel(code, locale = intlLocale()) {
  return capitalise(languageName(code, locale), locale)
}

/** The languages an announce welcomes, as one label: « Français et anglais ». */
export function languageListLabel(codes, locale = intlLocale()) {
  const names = codes.map((code) => languageName(code, locale))
  return capitalise(new Intl.ListFormat([locale], { type: 'conjunction' }).format(names), locale)
}

/** The options of a language picker, in the order of ANNOUNCE_LANGUAGES. */
export function languageOptions(locale = intlLocale()) {
  return ANNOUNCE_LANGUAGES.map((code) => ({ code, name: languageLabel(code, locale) }))
}

/** Whether an announce can welcome this language: a code read from an address or an account may not. */
export function isAnnounceLanguage(code) {
  return ANNOUNCE_LANGUAGES.includes(code)
}

function capitalise(text, locale) {
  return text.charAt(0).toLocaleUpperCase(locale) + text.slice(1)
}
