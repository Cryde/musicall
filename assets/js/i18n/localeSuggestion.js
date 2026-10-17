// The banner offering a visitor their browser's language is written in the language it offers, while
// the page only has its own catalogue loaded. Fetching the offered language's whole catalogue for one
// sentence would cost every visitor shown the banner the chunk setLocale() keeps out of the page, so
// these few messages ship in the main bundle instead, one entry per locale that can be offered.
//
// Kept out of vue-i18n on purpose: once a locale has any message, setLocale() takes its catalogue as
// loaded and would never fetch the rest.
export const LOCALE_SUGGESTIONS = {
  en: {
    label: 'Language suggestion',
    message: 'MusicAll is also available in English.',
    accept: 'Switch to English',
    dismiss: 'Dismiss'
  }
}
