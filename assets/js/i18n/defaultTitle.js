export const DEFAULT_TITLE_KEY = 'shared.head.title'

/**
 * What to show instead of `title` when it is the default title of a locale that is no longer the active one, or
 * null. A page's useTitle puts back, as it goes, the title it found on arrival: after a switch that can be the
 * other language's default, which a next page with no title of its own would keep.
 *
 * @param {string} title
 * @param {import('vue-i18n').Composer} i18nGlobal
 */
export function refreshedDefaultTitle(title, i18nGlobal) {
  const activeDefault = i18nGlobal.t(DEFAULT_TITLE_KEY)
  const isDefault = i18nGlobal.availableLocales.some(
    (locale) => i18nGlobal.t(DEFAULT_TITLE_KEY, {}, { locale }) === title
  )

  return isDefault && title !== activeDefault ? activeDefault : null
}
