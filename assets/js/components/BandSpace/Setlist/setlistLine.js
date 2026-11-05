import { formatDuration } from '../../../utils/setlistDuration.js'
import { programmeSummary } from '../../../utils/setlistProgramme.js'

/**
 * « 7 titres · 27:17 », « 7 titres » without any duration known, « Vide » without a song: what a
 * setlist holds, as its list entry says it in the sidebar and on the phone's home.
 *
 * @param {{ items?: Array }} setlist
 * @param {Function} t vue-i18n's translate
 */
export function setlistLine(setlist, t) {
  const { songs, total } = programmeSummary(setlist.items ?? [])
  if (songs === 0) return t('band_space_setlist.sidebar.setlist_empty')
  return total > 0
    ? t(
        'band_space_setlist.sidebar.song_count_with_duration',
        { duration: formatDuration(total) },
        songs
      )
    : t('band_space_setlist.sidebar.song_count', songs)
}
