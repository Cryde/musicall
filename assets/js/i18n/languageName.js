/**
 * A language's name written in that language, as a label: « Français », "English". Readers find their own
 * language whatever the page's, which is why the language pickers and the translation links use it.
 */
export function languageName(language) {
  const name = new Intl.DisplayNames([language], { type: 'language' }).of(language) ?? language

  return name.charAt(0).toLocaleUpperCase(language) + name.slice(1)
}
