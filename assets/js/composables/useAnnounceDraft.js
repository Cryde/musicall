import { useI18n } from 'vue-i18n'
import { useInstrumentStore } from '../store/attribute/instrument.js'
import { useStyleStore } from '../store/attribute/style.js'
import {
  ANNOUNCE_CREATE,
  announceDraftPayload,
  announceModalProps
} from '../utils/announceDraft.js'
import { usePendingAction, usePendingActionResumer } from './usePendingAction.js'

/**
 * Keeps the announce a visitor filled in when « Publier » stops them (#1306).
 *
 * @returns {(values: object, resumeOn?: string) => boolean} whether it was kept; `resumeOn` is the path of the page
 *   that reopens it after sign-in, when it is not the current one
 */
export function useKeepAnnounceDraft() {
  const pendingAction = usePendingAction()

  return (values, resumeOn = undefined) =>
    pendingAction.keep({
      kind: ANNOUNCE_CREATE,
      payload: announceDraftPayload(values),
      stoppedOnSubmit: true,
      route: resumeOn
    })
}

/**
 * Offers the kept announce back on the calling page after sign-in, ready to publish.
 *
 * @param {(initialProps: object) => void} open  opens `AddAnnounceModal` with these props and `isResumed`
 */
export function useAnnounceDraftResumer(open) {
  const { t } = useI18n()
  const instrumentStore = useInstrumentStore()
  const styleStore = useStyleStore()

  usePendingActionResumer(ANNOUNCE_CREATE, async (payload) => {
    if (instrumentStore.instruments.length === 0 || styleStore.styles.length === 0) {
      await Promise.all([instrumentStore.loadInstruments(), styleStore.loadStyles()])
    }
    const initialProps = announceModalProps(payload, {
      instruments: instrumentStore.instruments,
      styles: styleStore.styles
    })

    return { label: t('search.pending.announce_label'), resume: () => open(initialProps) }
  })
}
