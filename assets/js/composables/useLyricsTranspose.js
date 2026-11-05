import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { transposeKey } from '../utils/chordpro.js'

/**
 * A transposition on screen, in semitones: what the − and + of a lyrics sheet move, and how it reads
 * (« Tonalité d'origine », « +2 demi-tons (Bm) »). Nothing is saved; the sheet only shows it.
 *
 * @param {import('vue').Ref<string|null>} tonality the song's key, which names the shifted one
 */
export function useLyricsTranspose(tonality) {
  const { t } = useI18n()
  const transpose = ref(0)

  const transposeLabel = computed(() => {
    if (transpose.value === 0) return t('band_space_setlist.lyrics_panel.original_key')
    const semitones = `${transpose.value > 0 ? '+' : ''}${transpose.value}`
    const count = Math.abs(transpose.value)
    const key = transposeKey(tonality.value, transpose.value)
    return key
      ? t('band_space_setlist.lyrics_panel.semitones_with_key', { semitones, key }, count)
      : t('band_space_setlist.lyrics_panel.semitones', { semitones }, count)
  })

  // Kept inside an octave: twelve semitones up is where it started.
  function shift(step) {
    const next = transpose.value + step
    transpose.value = next > 11 || next < -11 ? 0 : next
  }

  return { transpose, transposeLabel, shift }
}
