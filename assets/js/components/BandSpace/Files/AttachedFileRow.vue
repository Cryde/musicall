<template>
  <div class="flex flex-col gap-1.5 min-w-0">
    <div class="flex items-center gap-3 min-w-0">
      <span
        :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-[0.95rem]', tileClass]"
        aria-hidden="true"
      >
        <i :class="fileIconClass(file.mime_type, file.original_name)" />
      </span>
      <div class="flex-1 min-w-0">
        <div class="truncate text-sm font-semibold text-surface-900 dark:text-surface-0" :title="file.original_name">{{ file.original_name }}</div>
        <div class="truncate text-xs text-surface-600 dark:text-surface-300">{{ meta }}</div>
      </div>

      <Button
        v-if="action === FILE_ACTIONS.READ"
        :label="featured && !isBelowMd ? t('band_space_files.attached_section.read_tab') : t('band_space_files.attached_section.read')"
        icon="pi pi-play"
        size="small"
        :severity="featured ? undefined : 'secondary'"
        class="shrink-0"
        @click="read"
      />
      <Button
        v-else-if="action === FILE_ACTIONS.PLAY"
        :icon="isLoading ? 'pi pi-spin pi-spinner' : isPlaying ? 'pi pi-pause' : 'pi pi-play'"
        rounded
        text
        severity="success"
        class="shrink-0"
        :disabled="isLoading || hasFailed"
        :aria-label="isPlaying ? t('band_space_files.attached_section.pause', { name: file.original_name }) : t('band_space_files.attached_section.play', { name: file.original_name })"
        @click="togglePlayback"
      />
      <Button
        v-else
        as="a"
        :href="fileUrl"
        target="_blank"
        rel="noopener"
        :label="action === FILE_ACTIONS.VIEW ? t('band_space_files.attached_section.view') : t('band_space_files.attached_section.download')"
        :icon="action === FILE_ACTIONS.VIEW ? 'pi pi-eye' : 'pi pi-download'"
        size="small"
        text
        severity="secondary"
        class="shrink-0"
      />
      <Button
        icon="pi pi-ellipsis-v"
        text
        rounded
        severity="secondary"
        size="small"
        class="shrink-0"
        aria-haspopup="true"
        :aria-label="t('band_space_files.attached_section.actions', { name: file.original_name })"
        @click="menu?.toggle($event)"
      />
      <Menu ref="menu" :model="menuItems" :pt="DANGER_MENU_PT" popup />
    </div>

    <!-- A sound plays where it is listed (#1431): the line below seeks, by pointer and keyboard. -->
    <div v-if="action === FILE_ACTIONS.PLAY && (isPlaying || currentSeconds > 0)" class="flex items-center gap-2 pl-12">
      <input
        type="range"
        class="flex-1 accent-emerald-600"
        min="0"
        :max="totalSeconds || 0"
        step="0.1"
        :value="currentSeconds"
        :aria-label="t('band_space_files.attached_section.position', { name: file.original_name })"
        :aria-valuetext="formatVoiceNoteDuration(currentSeconds)"
        @input="seek(Number($event.target.value))"
      />
      <small class="text-xs tabular-nums text-surface-600 dark:text-surface-300">
        {{ formatVoiceNoteDuration(currentSeconds) }} / {{ totalSeconds ? formatVoiceNoteDuration(totalSeconds) : noValue() }}
      </small>
    </div>
    <small v-if="hasFailed" role="alert" class="pl-12 text-xs text-red-700 dark:text-red-400">
      {{ t('band_space_files.attached_section.audio_failed') }}
    </small>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useBlobAudio } from '../../../composables/useBlobAudio.js'
import { useIsBelowMd } from '../../../composables/useIsPhone.js'
import { BAND_SPACE_ROUTES } from '../../../constants/bandSpace.js'
import { DANGER_MENU_PT } from '../../../constants/dangerMenu.js'
import { noValue } from '../../../i18n/formatLocale.js'
import { attachedFileAction, FILE_ACTIONS } from '../../../utils/attachedFileAction.js'
import { formatVoiceNoteDuration } from '../../../utils/chatVoiceNote.js'
import { formatDateCompactWithYear } from '../../../utils/date.js'
import { fileIconClass, fileTypeLabel } from '../../../utils/fileType.js'
import { withVersion } from '../../../utils/fileVersionUrl.js'
import { formatBytes } from '../../../utils/formatBytes.js'
import { tabReaderRoute } from '../../../utils/tabReaderBack.js'

/**
 * One file attached to a song, a setlist, a task or a finance entry (#1431), offering first what the
 * file is for: a tab opens in the reader, a sound plays here, a PDF or a picture opens in the browser,
 * anything else downloads. The rest sits behind ⋮.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  file: { type: Object, required: true },
  canReadTabs: { type: Boolean, required: true },
  /** Whether the file may be detached here. */
  canDetach: { type: Boolean, default: true },
  /** What the reader's way back says: the song, the task... */
  backLabel: { type: String, default: '' },
  /** The song page's tab card: « Lire la tablature », as the main button. */
  featured: { type: Boolean, default: false },
  /** A song's file can be put in one of the song's folders (#1451). */
  canMove: { type: Boolean, default: false }
})

const emit = defineEmits(['detach', 'move'])

const { t } = useI18n()
const router = useRouter()
const isBelowMd = useIsBelowMd()
const menu = ref(null)

const action = computed(() => attachedFileAction(props.file, { canReadTabs: props.canReadTabs }))
const fileUrl = computed(() => withVersion(props.file.download_url, props.file.current_version_id))

const meta = computed(() =>
  [
    fileTypeLabel(props.file.mime_type, props.file.original_name),
    props.file.size ? formatBytes(props.file.size) : null,
    props.file.created_by?.display_name ?? props.file.created_by?.username,
    props.file.creation_datetime ? formatDateCompactWithYear(props.file.creation_datetime) : null
  ]
    .filter(Boolean)
    .join(' · ')
)

const TILES = {
  [FILE_ACTIONS.READ]:
    'bg-primary-100 text-primary-800 dark:bg-primary-400/20 dark:text-primary-200',
  [FILE_ACTIONS.PLAY]:
    'bg-emerald-100 text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-300',
  [FILE_ACTIONS.VIEW]: 'bg-rose-100 text-rose-800 dark:bg-rose-400/15 dark:text-rose-300',
  [FILE_ACTIONS.DOWNLOAD]:
    'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-200'
}
const tileClass = computed(() => TILES[action.value])

// Fetched whole before it plays: the API streams without byte ranges, which Safari needs from a URL.
const { isPlaying, isLoading, hasFailed, currentSeconds, totalSeconds, togglePlayback, seek } =
  useBlobAudio(async () => {
    const response = await fetch(fileUrl.value)
    if (!response.ok) throw new Error(`HTTP ${response.status}`)
    return response.blob()
  }, 0)

function read() {
  router.push(tabReaderRoute(props.bandSpaceId, props.file.id, props.backLabel))
}

const menuItems = computed(() => {
  const items = [
    {
      label: t('band_space_files.attached_section.open_in_files'),
      icon: 'pi pi-folder-open',
      command: () =>
        router.push({
          name: BAND_SPACE_ROUTES.FILES,
          params: { id: props.bandSpaceId },
          query: { file: props.file.id }
        })
    }
  ]
  if (action.value !== FILE_ACTIONS.DOWNLOAD) {
    items.push({
      label: t('band_space_files.attached_section.download'),
      icon: 'pi pi-download',
      command: () => window.open(fileUrl.value, '_blank', 'noopener')
    })
  }
  if (props.canMove) {
    items.push({
      label: t('band_space_files.song_folders.move'),
      icon: 'pi pi-folder',
      command: () => emit('move', props.file)
    })
  }
  if (props.canDetach) {
    items.push({
      label: t('band_space_files.attached_section.detach'),
      icon: 'pi pi-times',
      danger: true,
      command: () => emit('detach', props.file)
    })
  }
  return items
})
</script>
