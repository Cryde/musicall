<template>
  <section
    class="relative flex flex-col gap-3"
    :aria-label="heading"
    @dragenter.prevent="handleDragEnter"
    @dragover.prevent
    @dragleave="handleDragLeave"
    @drop.prevent="handleDrop"
  >
    <div class="flex flex-wrap items-center justify-between gap-2">
      <component :is="headingTag" :class="['m-0', headingClass]">
        {{ heading }}
        <span v-if="attachedFiles.length > 0" class="ml-1 text-xs text-surface-500 dark:text-surface-400">({{ attachedFiles.length }})</span>
      </component>
      <div v-if="canAttach && attachedFiles.length > 0" class="flex flex-wrap gap-2">
        <Button :label="t('band_space_files.attached_section.import')" icon="pi pi-cloud-upload" size="small" severity="secondary" @click="openUpload()" />
        <Button :label="t('band_space_files.attached_section.choose')" icon="pi pi-link" size="small" severity="secondary" @click="linkVisible = true" />
      </div>
    </div>

    <div v-if="isLoading && attachedFiles.length === 0" class="flex flex-col gap-2">
      <Skeleton v-for="i in 2" :key="i" width="100%" height="2.75rem" border-radius="0.5rem" />
    </div>

    <template v-else-if="attachedFiles.length > 0">
      <div v-if="tab" class="overflow-hidden rounded-xl border border-surface-200 dark:border-surface-700">
        <TabScorePreview :file="tab" @read="readTab" />
        <div class="border-t border-surface-200 bg-surface-50 px-3 py-2.5 dark:border-surface-700 dark:bg-surface-800/60">
          <AttachedFileRow
            :band-space-id="bandSpaceId"
            :file="tab"
            :can-read-tabs="canReadTabs"
            :can-detach="mayDetach"
            :back-label="sourceLabel"
            featured
            @detach="confirmDetach"
          />
        </div>
      </div>
      <ul v-if="rows.length > 0" class="m-0 flex list-none flex-col gap-1.5 p-0">
        <li
          v-for="file in rows"
          :key="file.id"
          class="rounded-xl border border-surface-200 bg-surface-50 px-2.5 py-2 dark:border-surface-700 dark:bg-surface-800/60"
        >
          <AttachedFileRow
            :band-space-id="bandSpaceId"
            :file="file"
            :can-read-tabs="canReadTabs"
            :can-detach="mayDetach"
            :back-label="sourceLabel"
            @detach="confirmDetach"
          />
        </li>
      </ul>
      <p v-if="canAttach" class="m-0 text-xs text-surface-600 dark:text-surface-300">{{ t('band_space_files.attached_section.drop_hint') }}</p>
    </template>

    <div
      v-else-if="canAttach"
      class="flex flex-col items-center gap-3 rounded-xl border-2 border-dashed border-surface-300 px-4 py-6 text-center dark:border-surface-600"
    >
      <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary-100 text-primary-800 dark:bg-primary-400/20 dark:text-primary-200" aria-hidden="true">
        <i class="pi pi-cloud-upload text-lg" />
      </span>
      <p class="m-0 max-w-sm text-sm text-surface-600 dark:text-surface-300">
        <b class="block text-surface-900 dark:text-surface-0">{{ emptyTitle ?? t('band_space_files.attached_section.empty_title') }}</b>
        {{ emptyHint ?? t('band_space_files.attached_section.empty_hint') }}
      </p>
      <div class="flex flex-wrap justify-center gap-2">
        <Button :label="t('band_space_files.attached_section.import')" icon="pi pi-cloud-upload" size="small" @click="openUpload()" />
        <Button :label="t('band_space_files.attached_section.choose')" icon="pi pi-link" size="small" severity="secondary" @click="linkVisible = true" />
      </div>
    </div>

    <p v-else class="m-0 text-xs italic text-surface-500 dark:text-surface-400">
      {{ t('band_space_files.attached_section.empty') }}
    </p>

    <!-- Shown while files are dragged over the section: dropping them opens the import with them queued. -->
    <div
      v-if="isDragging"
      class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-xl border-2 border-dashed border-primary bg-primary-50/90 text-sm font-semibold text-primary-800 dark:bg-primary-950/90 dark:text-primary-200"
    >
      {{ t('band_space_files.attached_section.drop_here') }}
    </div>

    <template v-if="canAttach">
      <FileUploadDialog
        v-model:visible="uploadVisible"
        :band-space-id="bandSpaceId"
        :attach-to="{ type: sourceType, id: sourceId, label: sourceLabel || heading }"
        :initial-files="droppedFiles"
        :auto-start="startsAtOnce"
        @saved="handleUploaded"
      />
      <AttachFileDialog
        v-model:visible="linkVisible"
        :band-space-id="bandSpaceId"
        :source-type="sourceType"
        :source-id="sourceId"
        @attached="handleLinked"
      />
    </template>

    <FileDetachConfirmDialog ref="detachDialog" :band-space-id="bandSpaceId" />
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import Skeleton from 'primevue/skeleton'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import bandSpaceFilesApi from '../../../api/bandSpace/band-space-files.js'
import { useFileUploadToasts } from '../../../composables/useFileUploadToasts.js'
import { TABS_TESTER_ONLY } from '../../../constants/bandSpace.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { featuredTab } from '../../../utils/attachedFileAction.js'
import { tabReaderRoute } from '../../../utils/tabReaderBack.js'
import AttachedFileRow from './AttachedFileRow.vue'
import AttachFileDialog from './AttachFileDialog.vue'
import FileDetachConfirmDialog from './FileDetachConfirmDialog.vue'
import FileUploadDialog from './FileUploadDialog.vue'
import TabScorePreview from './TabScorePreview.vue'

/**
 * The files attached to a song, a setlist, a task or a finance entry (#1431): one list for the four, each
 * file offering what it is for (AttachedFileRow). Files go up from the device, several at once and by
 * drag and drop, or come from Files, where they stay.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  sourceType: {
    type: String,
    required: true,
    validator: (v) => ['task', 'finance', 'song', 'setlist'].includes(v)
  },
  sourceId: { type: String, required: true },
  /** What the files belong to, by name: the import dialog's title and the reader's way back. */
  sourceLabel: { type: String, default: '' },
  canAttach: { type: Boolean, default: true },
  /** Detaching follows canAttach, unless said otherwise: an archived setlist still lets a file go. */
  canDetach: { type: Boolean, default: null },
  /** Defaults to « Pièces jointes ». */
  title: { type: String, default: null },
  /** A song's page labels its sections the way its lyrics are labelled. */
  headingClass: {
    type: String,
    default: 'text-sm font-semibold text-surface-700 dark:text-surface-200'
  },
  /** The heading's level in the page around it: a section of a drawer by default, h2 on a song's page. */
  headingTag: { type: String, default: 'h4' },
  emptyTitle: { type: String, default: null },
  emptyHint: { type: String, default: null },
  /** A song's page draws its latest tab first, its first bars showing. */
  featureTab: { type: Boolean, default: false },
  /** Files to send as the section opens, picked before it existed (« Nouveau morceau »). */
  uploadOnMount: { type: Array, default: () => [] }
})

const emit = defineEmits(['attached', 'detached', 'count', 'tab'])

const { t } = useI18n()
const router = useRouter()
const toast = useToast()
const userSecurityStore = useUserSecurityStore()
const { announceUpload } = useFileUploadToasts()
const detachDialog = useTemplateRef('detachDialog')

const attachedFiles = ref([])
const isLoading = ref(false)
const uploadVisible = ref(false)
const linkVisible = ref(false)
const droppedFiles = ref([])
const startsAtOnce = ref(false)
const isDragging = ref(false)
// dragenter and dragleave fire for every child crossed: the count says when the pointer really left.
let dragDepth = 0

const DETACH_CONFIRM_KEYS = {
  task: 'band_space_files.attached_section.detach_confirm_task',
  finance: 'band_space_files.attached_section.detach_confirm_finance',
  song: 'band_space_files.attached_section.detach_confirm_song',
  setlist: 'band_space_files.attached_section.detach_confirm_setlist'
}

const heading = computed(() => props.title ?? t('band_space_files.attached_section.title'))
const mayDetach = computed(() => props.canDetach ?? props.canAttach)
const canReadTabs = computed(() => !TABS_TESTER_ONLY || userSecurityStore.isTester)
const tab = computed(() =>
  props.featureTab && canReadTabs.value ? featuredTab(attachedFiles.value) : null
)
const rows = computed(() => attachedFiles.value.filter((file) => file !== tab.value))

watch(
  () => [props.bandSpaceId, props.sourceType, props.sourceId],
  () => {
    loadFiles()
  },
  { immediate: true }
)

// Watched rather than emitted from loadFiles(): detaching splices the list in place without reloading,
// and a parent warning "n fichiers seront détachés" has to follow every path, not just the reloads.
watch(
  () => attachedFiles.value.length,
  (count) => emit('count', count),
  { immediate: true }
)
// The song page puts « Lire la tablature » in its own head too.
watch(tab, (file) => emit('tab', file), { immediate: true })

async function loadFiles() {
  if (!props.sourceId) return
  isLoading.value = true
  try {
    attachedFiles.value = await bandSpaceFilesApi.getAttachedFiles(
      props.bandSpaceId,
      props.sourceType,
      props.sourceId
    )
  } catch {
    attachedFiles.value = []
  } finally {
    isLoading.value = false
  }
}

function openUpload(files = [], { autoStart = false } = {}) {
  droppedFiles.value = files
  startsAtOnce.value = autoStart
  uploadVisible.value = true
}

onMounted(() => {
  if (props.canAttach && props.uploadOnMount.length > 0)
    openUpload(props.uploadOnMount, { autoStart: true })
})

function handleDragEnter(event) {
  if (!props.canAttach || !event.dataTransfer?.types?.includes('Files')) return
  dragDepth += 1
  isDragging.value = true
}

function handleDragLeave() {
  if (!isDragging.value) return
  dragDepth = Math.max(0, dragDepth - 1)
  if (dragDepth === 0) isDragging.value = false
}

function handleDrop(event) {
  dragDepth = 0
  isDragging.value = false
  const files = [...(event.dataTransfer?.files ?? [])]
  if (props.canAttach && files.length > 0) openUpload(files)
}

function handleUploaded(result) {
  announceUpload(result)
  loadFiles()
  for (const file of result.uploadedFiles) emit('attached', file.id)
}

function handleLinked(file) {
  loadFiles()
  emit('attached', file?.id ?? null)
}

function readTab() {
  router.push(tabReaderRoute(props.bandSpaceId, tab.value.id, props.sourceLabel))
}

function confirmDetach(file) {
  detachDialog.value.open({
    file,
    source: { type: props.sourceType, id: props.sourceId },
    message: t(DETACH_CONFIRM_KEYS[props.sourceType], { name: file.original_name }),
    header: t('band_space_files.attached_section.detach_confirm_header'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('band_space_files.attached_section.detach'),
    rejectLabel: t('band_space_files.common.cancel'),
    onDetach: async (archive) => {
      try {
        await bandSpaceFilesApi.detachFromSource(
          props.bandSpaceId,
          props.sourceType,
          props.sourceId,
          file.id,
          { archive }
        )
        attachedFiles.value = attachedFiles.value.filter((f) => f.id !== file.id)
        emit('detached', file.id)
        toast.add({
          severity: 'success',
          summary: archive
            ? t('band_space_files.detach_dialog.trashed')
            : t('band_space_files.attached_section.detached'),
          life: 3000
        })
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: t('band_space_files.attached_section.detach_failed'),
          detail: e.message,
          life: 5000
        })
      }
    }
  })
}

defineExpose({ reload: loadFiles })
</script>
