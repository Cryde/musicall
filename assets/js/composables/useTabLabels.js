import { useI18n } from 'vue-i18n'
import { formatPercent } from '../i18n/formatLocale.js'

/** How the tab reader words a section and a speed, the same in the panel, the player and the head. */
export function useTabLabels() {
  const { t } = useI18n()

  /** A section's name, « Début » for the unnamed one a file without a marker on its first bar opens with. */
  function sectionName(section) {
    return section?.name ?? t('band_space_tabs.section.start')
  }

  /** « mes. 14 », or « mes. 14-22 ». */
  function sectionBars(section) {
    return section.bars === 1
      ? t('band_space_tabs.section.bar', { bar: section.start + 1 })
      : t('band_space_tabs.section.bars', { start: section.start + 1, end: section.end + 1 })
  }

  /** A speed ratio as the locale writes a percentage: « 75 % », « 75% ». */
  function speedPercent(ratio) {
    return formatPercent(Math.round(ratio * 100))
  }

  return { sectionName, sectionBars, speedPercent }
}
