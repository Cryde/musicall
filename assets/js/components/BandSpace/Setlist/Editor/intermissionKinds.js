import { i18n } from '../../../../i18n/index.js'

/**
 * The three intermèdes of a running order (#1061), in the order the footer offers them. `label` is
 * also the default label of one created in one click, which the member renames afterwards.
 */
export const INTERMISSION_KINDS = [
  {
    type: 'break',
    labelKey: 'band_space_setlist.intermission.break_label',
    get label() {
      return i18n.global.t(this.labelKey)
    },
    get tag() {
      return i18n.global.t('band_space_setlist.intermission.break_tag')
    },
    icon: 'pi pi-pause',
    color: 'text-amber-700 dark:text-amber-400'
  },
  {
    type: 'talk',
    labelKey: 'band_space_setlist.intermission.talk_label',
    get label() {
      return i18n.global.t(this.labelKey)
    },
    get tag() {
      return i18n.global.t('band_space_setlist.intermission.talk_tag')
    },
    icon: 'pi pi-microphone',
    color: 'text-violet-700 dark:text-violet-300'
  },
  {
    type: 'interlude',
    labelKey: 'band_space_setlist.intermission.interlude_label',
    get label() {
      return i18n.global.t(this.labelKey)
    },
    get tag() {
      return i18n.global.t('band_space_setlist.intermission.interlude_tag')
    },
    icon: 'pi pi-volume-up',
    color: 'text-teal-700 dark:text-teal-300'
  }
]

export function intermissionKind(type) {
  return INTERMISSION_KINDS.find((kind) => kind.type === type) ?? INTERMISSION_KINDS[0]
}

/**
 * Whether a stored label is still the kind's default in one of the loaded catalogues. The label is
 * saved in the language of the member who added the row, and a band can mix French and English members.
 */
export function isDefaultIntermissionLabel(type, label) {
  const { labelKey } = intermissionKind(type)
  return i18n.global.availableLocales.some(
    (locale) => i18n.global.t(labelKey, {}, { locale }) === label
  )
}
