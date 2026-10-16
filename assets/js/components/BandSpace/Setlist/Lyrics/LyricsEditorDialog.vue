<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="t('band_space_setlist.lyrics_editor.header', { title: song?.title ?? '' })"
    :style="{ width: 'min(72rem, 100vw)' }"
    :breakpoints="{ '768px': '100vw' }"
    :content-style="{ paddingBottom: '0.5rem' }"
    :closable="false"
    :close-on-escape="false"
    @hide="handleHide"
  >
    <div class="flex flex-col gap-3">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <SelectButton
          v-model="mode"
          :options="modeOptions"
          option-label="label"
          option-value="value"
          :allow-empty="false"
          :aria-label="t('band_space_setlist.lyrics_editor.mode_aria')"
        />
        <a
          href="https://www.chordpro.org/chordpro/chordpro-introduction/"
          target="_blank"
          rel="noopener"
          class="text-xs text-surface-600 dark:text-surface-300 underline"
          v-if="mode === 'advanced'"
        >{{ t('band_space_setlist.lyrics_editor.chordpro_link') }}</a>
      </div>

      <Message v-if="conflict" severity="warn" :closable="false">
        <div class="flex flex-col gap-2">
          <span>{{ conflict }}</span>
          <div class="flex flex-wrap gap-2">
            <Button :label="t('band_space_setlist.lyrics_editor.load_theirs')" size="small" severity="secondary" @click="reloadLatest" />
            <Button :label="t('band_space_setlist.lyrics_editor.copy_mine')" icon="pi pi-copy" size="small" severity="secondary" text @click="copyMine" />
          </div>
        </div>
      </Message>

      <LyricsVisualEditor
        v-if="mode === 'simple'"
        v-model="source"
        :members="members"
        :name-of="nameOf"
      />

      <div v-else-if="mode === 'advanced'" class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <Textarea
          v-model="source"
          class="font-mono text-sm min-h-[24rem] w-full"
          spellcheck="false"
          :aria-label="t('band_space_setlist.lyrics_editor.source_aria')"
          @paste="handleRawPaste"
        />
        <div class="border border-surface-200 dark:border-surface-700 rounded-lg p-3 overflow-auto max-h-[36rem]">
          <LyricsSheet :lyrics="source" :singers="knownSingers" :tonality="song?.tonality" />
        </div>
      </div>

      <div v-else class="border border-surface-200 dark:border-surface-700 rounded-lg p-4 overflow-auto max-h-[70vh]">
        <LyricsSheet v-if="source.trim()" :lyrics="source" :singers="knownSingers" :tonality="song?.tonality" large />
        <p v-else class="text-surface-600 dark:text-surface-300 italic m-0">{{ t('band_space_setlist.lyrics_editor.empty') }}</p>
      </div>
    </div>

    <template #footer>
      <span class="mr-auto text-xs text-surface-600 dark:text-surface-300 tabular-nums">
        {{ source.length }} / {{ MAX_LENGTH }}
      </span>
      <Button :label="t('band_space_setlist.common.cancel')" severity="secondary" text :disabled="isSaving" @click="requestClose" />
      <Button :label="t('band_space_setlist.common.save')" icon="pi pi-check" :loading="isSaving" :disabled="source.length > MAX_LENGTH" @click="save" />
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import SelectButton from 'primevue/selectbutton'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import bandSpaceSettingsApi from '../../../../api/bandSpace/band-space-settings.js'
import bandSpaceSongsApi from '../../../../api/bandSpace/band-space-songs.js'
import { fromChordSite, SINGER_ALL } from '../../../../utils/chordpro.js'
import LyricsSheet from './LyricsSheet.vue'
import LyricsVisualEditor from './LyricsVisualEditor.vue'

// Mirrors SongLyrics::MAX_LENGTH.
const MAX_LENGTH = 20000

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  song: { type: Object, default: null },
  /** The lyrics as last read: { lyrics, lyrics_version, singers }. */
  lyrics: { type: Object, default: null }
})

/** `saved` after a save, `reloaded` when a conflict pulled in someone else's newer version. */
const emit = defineEmits(['saved', 'reloaded'])
const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const toast = useToast()

const modeOptions = computed(() => [
  { label: t('band_space_setlist.lyrics_editor.mode_simple'), value: 'simple' },
  { label: t('band_space_setlist.lyrics_editor.mode_advanced'), value: 'advanced' },
  { label: t('band_space_setlist.lyrics_editor.mode_preview'), value: 'preview' }
])
const confirm = useConfirm()

const mode = ref('simple')
const source = ref('')
const startedFrom = ref('')
const version = ref(1)
const savedSingers = ref([])
const roster = ref([])
const isSaving = ref(false)
const conflict = ref(null)

/** The band now, to pick from. */
const members = computed(() =>
  roster.value.map((member) => ({ id: member.user_id, name: member.display_name }))
)

/** Everyone a name can be shown for: the band now, and whoever the saved lyrics already named. */
const knownSingers = computed(() => {
  const known = members.value.map((member) => ({ ...member, is_former_member: false }))
  for (const singer of savedSingers.value) {
    if (!known.some((member) => member.id === singer.id)) known.push(singer)
  }
  return known
})

function nameOf(id) {
  if (id === SINGER_ALL) return t('band_space_setlist.singer.everyone')
  return (
    knownSingers.value.find((singer) => singer.id === id)?.name ??
    t('band_space_setlist.singer.unknown_member')
  )
}

function startFrom(lyrics) {
  source.value = lyrics?.lyrics ?? ''
  startedFrom.value = source.value
  version.value = lyrics?.lyrics_version ?? 1
  savedSingers.value = lyrics?.singers ?? []
  conflict.value = null
}

watch(
  visible,
  async (isOpen) => {
    if (!isOpen) return
    startFrom(props.lyrics)
    mode.value = 'simple'
    try {
      roster.value = await bandSpaceSettingsApi.getMembers(props.bandSpaceId)
    } catch (error) {
      toast.add({
        severity: 'error',
        summary: t('band_space_setlist.common.error'),
        detail: error.message,
        life: 5000
      })
    }
  },
  { immediate: true }
)

// In the raw text a chord-site paste is converted too, so both modes read a paste the same way.
function handleRawPaste(event) {
  const text = event.clipboardData?.getData('text/plain') ?? ''
  const converted = fromChordSite(text)
  if (converted === text) return
  event.preventDefault()
  const field = event.target
  field.setRangeText(converted, field.selectionStart, field.selectionEnd, 'end')
  source.value = field.value
}

async function save() {
  isSaving.value = true
  try {
    const saved = await bandSpaceSongsApi.updateLyrics(
      props.bandSpaceId,
      props.song.id,
      source.value,
      version.value
    )
    toast.add({
      severity: 'success',
      summary: t('band_space_setlist.lyrics_editor.saved'),
      life: 3000
    })
    emit('saved', saved)
    visible.value = false
  } catch (error) {
    if (error.status === 409) {
      conflict.value = error.message
    } else {
      toast.add({
        severity: 'error',
        summary: t('band_space_setlist.lyrics_editor.save_failed'),
        detail: error.message,
        life: 6000
      })
    }
  } finally {
    isSaving.value = false
  }
}

async function reloadLatest() {
  try {
    const latest = await bandSpaceSongsApi.getLyrics(props.bandSpaceId, props.song.id)
    startFrom(latest)
    emit('reloaded', latest)
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('band_space_setlist.common.error'),
      detail: error.message,
      life: 5000
    })
  }
}

async function copyMine() {
  try {
    await navigator.clipboard.writeText(source.value)
    toast.add({
      severity: 'info',
      summary: t('band_space_setlist.lyrics_editor.copied'),
      life: 2500
    })
  } catch {
    toast.add({
      severity: 'warn',
      summary: t('band_space_setlist.lyrics_editor.copy_failed_summary'),
      detail: t('band_space_setlist.lyrics_editor.copy_failed_detail'),
      life: 5000
    })
  }
}

// No close button and no Escape: lyrics are minutes of work, a stray key must not throw them away.
function requestClose() {
  if (source.value === startedFrom.value) {
    visible.value = false
    return
  }
  confirm.require({
    message: t('band_space_setlist.lyrics_editor.discard_confirm_message'),
    header: t('band_space_setlist.lyrics_editor.discard_confirm_header'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('band_space_setlist.lyrics_editor.discard_confirm_accept'),
    rejectLabel: t('band_space_setlist.lyrics_editor.discard_confirm_reject'),
    accept: () => {
      visible.value = false
    }
  })
}

function handleHide() {
  conflict.value = null
}
</script>
