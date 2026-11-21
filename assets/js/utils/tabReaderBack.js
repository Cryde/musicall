import { BAND_SPACE_ROUTES } from '../constants/bandSpace.js'

/**
 * What the reader's way back says. A page that opens a tab names itself in the history state (`backLabel`:
 * a song page gives the song's title); otherwise the label follows the module the reader goes back to,
 * Files when it has nowhere to go back to, as goBack() does.
 *
 * @param {{backLabel?: unknown, previousRouteName?: ?string}} from
 * @param {(key: string) => string} t
 * @returns {string}
 */
export function tabReaderBackLabel({ backLabel, previousRouteName }, t) {
  if (typeof backLabel === 'string' && backLabel.trim() !== '') return backLabel
  if (previousRouteName === BAND_SPACE_ROUTES.TABS)
    return t('band_space_tabs.head.back_to_tabs_label')
  return t('band_space_tabs.head.files')
}

/**
 * The reader for one file, told what its way back is called (a song page passes the song's title).
 *
 * @param {string} bandSpaceId
 * @param {string} fileId
 * @param {string} [backLabel]
 */
export function tabReaderRoute(bandSpaceId, fileId, backLabel = '') {
  return {
    name: BAND_SPACE_ROUTES.TAB_READER,
    params: { id: bandSpaceId, fileId },
    state: { backLabel }
  }
}
