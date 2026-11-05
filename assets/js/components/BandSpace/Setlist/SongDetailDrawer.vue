<template>
  <Drawer
    v-model:visible="visible"
    position="right"
    :style="{ width: 'min(40rem, 100vw)' }"
    :show-close-icon="!isBelowMd"
    :close-on-escape="!isSongSheetOpen"
    :pt="isBelowMd ? { header: { class: '!p-0' }, content: { class: '!px-0' } } : undefined"
  >
    <!-- A phone shows the song as a screen: back, its title (tapped to rename), and ⋮. -->
    <template v-if="isBelowMd" #header>
      <div class="flex h-14 min-w-0 flex-1 items-center gap-1 border-b border-surface-200 px-1 dark:border-surface-700">
        <Button
          icon="pi pi-arrow-left"
          text
          rounded
          severity="secondary"
          :aria-label="t('band_space_setlist.live.back')"
          @click="visible = false"
        />
        <InlineEditField
          v-if="song"
          ref="phoneTitleField"
          :model-value="song.title"
          :label="t('band_space_setlist.song_drawer.title')"
          required
          class="min-w-0 flex-1"
          input-class="font-semibold"
          :readonly="isLocked('title')"
          :save="(value) => saveField('title', value)"
        >
          <template #default="{ value }"><span class="text-[1.0625rem] font-semibold">{{ value }}</span></template>
        </InlineEditField>
        <Button
          v-if="!readOnly"
          icon="pi pi-ellipsis-v"
          text
          rounded
          severity="secondary"
          :aria-label="t('band_space_setlist.phone.song_actions')"
          aria-haspopup="dialog"
          @click="isSongSheetOpen = true"
        />
      </div>
    </template>
    <!-- Every value is edited where it is shown (#1067): there is no separate edit form. -->
    <template v-else #header>
      <InlineEditField
        v-if="song"
        :model-value="song.title"
        :label="t('band_space_setlist.song_drawer.title')"
        required
        class="flex-1 mr-2"
        input-class="font-semibold text-lg"
        :readonly="isLocked('title')"
        :save="(value) => saveField('title', value)"
      >
        <template #default="{ value }"><span class="text-xl font-semibold">{{ value }}</span></template>
      </InlineEditField>
    </template>

    <div v-if="song" class="flex flex-col gap-4 max-md:gap-0">
      <!-- A phone lists the fields as rows, each edited where it is, as the desktop's grid does. -->
      <div v-if="isBelowMd" class="divide-y divide-surface-200 border-b border-surface-200 dark:divide-surface-700 dark:border-surface-700">
        <div v-for="field in PHONE_FIELDS" :key="field.key" class="flex min-h-13 items-center gap-3.5 px-5 py-1.5">
          <i :class="['pi w-4.5 text-center text-surface-600 dark:text-surface-300', field.icon]" aria-hidden="true" />
          <span class="w-24 shrink-0 text-[0.9375rem] text-surface-600 dark:text-surface-300">{{ t(field.labelKey) }}</span>
          <InlineEditField
            :model-value="song[field.key]"
            :label="t(field.labelKey)"
            :kind="field.kind"
            :multiline="field.multiline"
            :placeholder="field.placeholderKey ? t(field.placeholderKey) : undefined"
            class="min-w-0 flex-1 text-[0.9375rem] [&_button]:text-right"
            :readonly="isLocked(field.key)"
            :save="(value) => saveField(field.key, value)"
          />
          <i class="pi pi-chevron-right shrink-0 text-xs text-surface-500" aria-hidden="true" />
        </div>
      </div>

      <div v-else class="grid grid-cols-3 gap-3 text-sm">
        <div>
          <div class="text-xs uppercase text-surface-600 dark:text-surface-300 mb-1">{{ t('band_space_setlist.song_drawer.tonality') }}</div>
          <InlineEditField
            :model-value="song.tonality"
            :label="t('band_space_setlist.song_drawer.tonality')"
            :placeholder="t('band_space_setlist.song_drawer.tonality_placeholder')"
            :readonly="isLocked('tonality')"
            :save="(value) => saveField('tonality', value)"
          />
        </div>
        <div>
          <div class="text-xs uppercase text-surface-600 dark:text-surface-300 mb-1">{{ t('band_space_setlist.song_drawer.tempo') }}</div>
          <InlineEditField
            :model-value="song.tempo"
            :label="t('band_space_setlist.song_drawer.tempo')"
            kind="number"
            :readonly="isLocked('tempo')"
            :save="(value) => saveField('tempo', value)"
          />
        </div>
        <div>
          <div class="text-xs uppercase text-surface-600 dark:text-surface-300 mb-1">{{ t('band_space_setlist.song_drawer.duration') }}</div>
          <InlineEditField
            :model-value="song.reference_duration"
            :label="t('band_space_setlist.song_drawer.duration')"
            kind="duration"
            :readonly="isLocked('reference_duration')"
            :save="(value) => saveField('reference_duration', value)"
          />
        </div>
      </div>

      <div v-if="!isBelowMd">
        <div class="text-xs uppercase text-surface-600 dark:text-surface-300 mb-1">{{ t('band_space_setlist.song_drawer.notes') }}</div>
        <InlineEditField
          :model-value="song.notes"
          :label="t('band_space_setlist.song_drawer.notes')"
          multiline
          :placeholder="t('band_space_setlist.song_drawer.notes_placeholder')"
          class="text-sm"
          :readonly="isLocked('notes')"
          :save="(value) => saveField('notes', value)"
        />
      </div>

      <Divider v-if="!isBelowMd" />

      <!-- A phone reads the words in their own screen, the next songs of the set after them. -->
      <template v-if="isBelowMd && song.has_lyrics">
        <h3 :class="PHONE_SECTION">{{ t('band_space_setlist.lyrics_panel.title') }}</h3>
        <button
          type="button"
          class="flex min-h-13 w-full items-center gap-3 px-5 text-left text-[0.9375rem]"
          @click="emit('read-lyrics', song)"
        >
          <i class="pi pi-align-left text-primary" aria-hidden="true" />
          {{ t('band_space_setlist.phone.read_lyrics') }}
          <i class="pi pi-chevron-right ml-auto text-xs text-surface-500" aria-hidden="true" />
        </button>
        <p class="px-5 text-[0.9375rem] text-surface-600 dark:text-surface-300">{{ t('band_space_setlist.common.lyrics_present') }}</p>
      </template>
      <SongLyricsPanel
        v-else
        :band-space-id="bandSpaceId"
        :song="song"
        :read-only="readOnly"
        :class="{ 'px-5 pt-4': isBelowMd }"
        @changed="handleLyricsChanged"
      />

      <template v-if="isBelowMd && song.setlists?.length">
        <h3 :class="PHONE_SECTION">{{ t('band_space_setlist.repertoire.column_setlists') }}</h3>
        <button
          v-for="setlist in song.setlists"
          :key="setlist.id"
          type="button"
          class="flex min-h-13 w-full items-center gap-3 border-surface-200 px-5 text-left text-[0.9375rem] not-first-of-type:border-t dark:border-surface-700"
          @click="openSetlist(setlist.id)"
        >
          <i class="pi pi-list text-surface-600 dark:text-surface-300" aria-hidden="true" />
          <span class="truncate">{{ setlist.name }}</span>
        </button>
      </template>

      <Divider v-if="!isBelowMd" />

      <div :class="{ 'px-5': isBelowMd }">
        <div class="flex items-center justify-between mb-2" :class="{ 'mt-5': isBelowMd }">
          <div class="text-xs uppercase text-surface-500" :class="{ '!text-[0.8125rem] font-semibold text-surface-600 dark:text-surface-300': isBelowMd }">{{ t('band_space_setlist.attached_files.title') }}</div>
          <span v-if="files.length > 0" class="text-xs text-surface-400 tabular-nums">
            {{ files.length }}
          </span>
        </div>

        <div v-if="isLoadingFiles && files.length === 0" class="flex flex-col gap-2 mb-2">
          <Skeleton v-for="i in 2" :key="i" height="2.25rem" borderRadius="0.5rem" />
        </div>

        <ul v-else-if="files.length > 0" class="list-none p-0 m-0 flex flex-col gap-1 mb-3">
          <li
            v-for="file in files"
            :key="file.id"
            class="flex items-center gap-2 px-2 py-1.5 rounded-lg bg-surface-50 dark:bg-surface-800 text-sm"
          >
            <i class="pi pi-file text-surface-500 shrink-0" aria-hidden="true"></i>
            <div class="flex-1 min-w-0">
              <div class="truncate">{{ file.original_name }}</div>
              <div v-if="file.size" class="text-xs text-surface-500 tabular-nums">
                {{ formatBytes(file.size) }}
              </div>
            </div>
            <Button
              icon="pi pi-times"
              severity="secondary"
              text
              rounded
              size="small"
              :aria-label="t('band_space_setlist.attached_files.detach')"
              v-tooltip.left="t('band_space_setlist.attached_files.detach_tooltip')"
              @click="confirmDetach(file)"
            />
          </li>
        </ul>

        <p v-else class="text-xs text-surface-400 italic mb-2">
          {{ t('band_space_setlist.attached_files.empty') }}
        </p>

        <input ref="fileInput" type="file" class="hidden" @change="handleFileSelected" />
        <Button
          :label="t('band_space_setlist.attached_files.upload')"
          icon="pi pi-cloud-upload"
          severity="secondary"
          size="small"
          :loading="isUploading"
          @click="fileInput?.click()"
        />
      </div>

      <Divider v-if="!isBelowMd" />

      <div v-if="!readOnly && !isBelowMd" class="flex gap-2">
        <Button :label="t('band_space_setlist.common.archive')" icon="pi pi-archive" severity="danger" outlined @click="confirmArchive" />
      </div>
    </div>

    <BottomSheet
      v-if="isBelowMd && song"
      v-model:visible="isSongSheetOpen"
      :title="song.title"
      @after-hide="songSheet.onAfterHide"
    >
      <SheetAction icon="pi-pencil" @click="songSheet.run(() => phoneTitleField?.startEditing())">
        {{ t('band_space_setlist.phone.rename') }}
      </SheetAction>
      <SheetAction icon="pi-archive" danger @click="songSheet.run(confirmArchive)">
        {{ t('band_space_setlist.common.archive') }}
      </SheetAction>
    </BottomSheet>
  </Drawer>
</template>

<script setup>
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import Drawer from 'primevue/drawer'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import bandSpaceSongsApi from '../../../api/bandSpace/band-space-songs.js'
import { useActionAfterSheetHide } from '../../../composables/useActionAfterSheetHide.js'
import { useIsBelowMd } from '../../../composables/useIsPhone.js'
import { useBandSongsStore } from '../../../store/bandSpace/bandSpaceSongs.js'
import { formatBytes } from '../../../utils/formatBytes.js'
import { recordOpenedItem } from '../../../utils/openedItemRecorder.js'
import BottomSheet from '../../Global/BottomSheet.vue'
import SheetAction from '../../Global/SheetAction.vue'
import SongLyricsPanel from './Lyrics/SongLyricsPanel.vue'
import InlineEditField from './Song/InlineEditField.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  song: { type: Object, default: null }
})

const emit = defineEmits(['archived', 'updated', 'read-lyrics'])
const visible = defineModel('visible', { type: Boolean, default: false })
const isBelowMd = useIsBelowMd()
const router = useRouter()

// The song's fields as a phone lists them, one row each.
const PHONE_FIELDS = [
  {
    key: 'tonality',
    icon: 'pi-key',
    labelKey: 'band_space_setlist.song_drawer.tonality',
    placeholderKey: 'band_space_setlist.song_drawer.tonality_placeholder'
  },
  {
    key: 'tempo',
    icon: 'pi-stopwatch',
    labelKey: 'band_space_setlist.song_drawer.tempo',
    kind: 'number'
  },
  {
    key: 'reference_duration',
    icon: 'pi-clock',
    labelKey: 'band_space_setlist.song_drawer.duration',
    kind: 'duration'
  },
  {
    key: 'notes',
    icon: 'pi-pen-to-square',
    labelKey: 'band_space_setlist.song_drawer.notes',
    multiline: true,
    placeholderKey: 'band_space_setlist.song_drawer.notes_placeholder'
  }
]
const PHONE_SECTION =
  'mx-5 mt-5 mb-1.5 text-[0.8125rem] font-semibold uppercase text-surface-600 dark:text-surface-300'

const phoneTitleField = ref(null)
const isSongSheetOpen = ref(false)
// « Archiver » asks first: the confirm waits for the sheet to be gone.
const songSheet = useActionAfterSheetHide(isSongSheetOpen)

function openSetlist(setlistId) {
  visible.value = false
  router.replace({ query: { setlist: setlistId } })
}

const { t } = useI18n()
const songsStore = useBandSongsStore()
const confirm = useConfirm()
const toast = useToast()

const fileInput = ref(null)
const isUploading = ref(false)
const files = ref([])
const isLoadingFiles = ref(false)

async function loadFiles() {
  if (!props.song) {
    files.value = []
    return
  }
  isLoadingFiles.value = true
  try {
    files.value = await bandSpaceSongsApi.getAttachedFiles(props.bandSpaceId, props.song.id)
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: t('band_space_setlist.common.error'),
      detail: e.message,
      life: 5000
    })
  } finally {
    isLoadingFiles.value = false
  }
}

watch(
  [visible, () => props.song?.id],
  ([isOpen, songId]) => {
    if (isOpen && songId) {
      // Both places that mount this drawer go through here, once per open (#1047).
      recordOpenedItem(props.bandSpaceId, 'song', songId)
      loadFiles()
    } else if (!isOpen) {
      files.value = []
    }
  },
  { immediate: true }
)

async function handleFileSelected(event) {
  const file = event.target.files?.[0]
  if (!file || !props.song) return
  isUploading.value = true
  try {
    await bandSpaceSongsApi.uploadFile(props.bandSpaceId, props.song.id, file)
    toast.add({
      severity: 'success',
      summary: t('band_space_setlist.attached_files.uploaded'),
      life: 3000
    })
    await loadFiles()
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: t('band_space_setlist.common.error'),
      detail: e.message,
      life: 5000
    })
  } finally {
    isUploading.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

function confirmDetach(file) {
  if (!props.song) return
  confirm.require({
    message: t('band_space_setlist.song_drawer.detach_confirm_message', {
      name: file.original_name
    }),
    header: t('band_space_setlist.common.confirm'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('band_space_setlist.attached_files.detach'),
    rejectLabel: t('band_space_setlist.common.cancel'),
    accept: async () => {
      try {
        await bandSpaceSongsApi.detachFile(props.bandSpaceId, props.song.id, file.id)
        toast.add({
          severity: 'success',
          summary: t('band_space_setlist.attached_files.detached'),
          life: 3000
        })
        await loadFiles()
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: t('band_space_setlist.common.error'),
          detail: e.message,
          life: 5000
        })
      }
    }
  })
}

const readOnly = computed(() => props.song?.archive_datetime != null)

/**
 * One field per PATCH, and one PATCH at a time: the server writes back every field of the song it
 * read, so two saves in flight could put the first one's field back. The other fields wait meanwhile.
 * A refusal is rethrown to the field.
 */
const savingField = ref(null)

function isLocked(field) {
  return readOnly.value || (savingField.value !== null && savingField.value !== field)
}

async function saveField(field, value) {
  savingField.value = field
  try {
    emit(
      'updated',
      await songsStore.updateSong(props.bandSpaceId, props.song.id, { [field]: value })
    )
  } finally {
    savingField.value = null
  }
}

// The lyrics live on their own resource. The lyrics store re-reads the song a write changed (has_lyrics,
// a moved key), so the drawer takes that copy.
function handleLyricsChanged() {
  const fresh = songsStore.songs.find((candidate) => candidate.id === props.song?.id)
  if (fresh) emit('updated', fresh)
}

function confirmArchive() {
  if (!props.song) return
  confirm.require({
    message: t('band_space_setlist.common.archive_song_confirm_message', {
      title: props.song.title
    }),
    header: t('band_space_setlist.common.archive_confirm_header'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('band_space_setlist.common.archive'),
    rejectLabel: t('band_space_setlist.common.cancel'),
    accept: async () => {
      try {
        await songsStore.deleteSong(props.bandSpaceId, props.song.id)
        toast.add({
          severity: 'success',
          summary: t('band_space_setlist.common.song_archived'),
          life: 3000
        })
        emit('archived')
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: t('band_space_setlist.common.error'),
          detail: e.message,
          life: 5000
        })
      }
    }
  })
}
</script>
