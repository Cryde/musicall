import { i18n } from '../i18n/index.js'

/**
 * The addresses a note link may point to, the same as on the phone (`prosemirror_delta.dart`) and
 * within what the API hands back (`BandSpaceNoteBuilder::SAFE_URI_PATTERN`), so a link added here is
 * never dropped on the way out.
 */
const LINK_ADDRESS_PATTERN = /^(?:https?:\/\/|mailto:)[^\s<>"'\\]+$/i

/**
 * @param {string|null|undefined} input what the member typed
 * @returns {{ href: string } | { error: string }}
 */
export function checkLinkAddress(input) {
  const href = (input ?? '').trim()

  return LINK_ADDRESS_PATTERN.test(href)
    ? { href }
    : { error: i18n.global.t('band_space_notes.link_address_error') }
}
