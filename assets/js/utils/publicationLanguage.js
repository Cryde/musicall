import { languageName } from '../i18n/languageName.js'

/** More than one enabled locale, so a publication's language is worth choosing and showing. */
export function offersLanguageChoice(enabledLocales) {
  return enabledLocales.length > 1
}

/**
 * The enabled locales a publication can still be translated into, given its `language` and the
 * `translation_languages` of its other translations. None for a video: it has no language, being shown in
 * every one.
 */
export function translationLanguagesLeft(publication, enabledLocales) {
  if (!publication.language) {
    return []
  }
  const taken = [publication.language, ...(publication.translation_languages ?? [])]

  return enabledLocales.filter((locale) => !taken.includes(locale))
}

/** Select options for `languages`, those in `taken` disabled: another translation is already in it. */
export function languageOptions(languages, taken = []) {
  return languages.map((language) => ({
    value: language,
    label: languageName(language),
    disabled: taken.includes(language)
  }))
}
