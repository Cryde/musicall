<template>
  <Drawer
    v-model:visible="visible"
    position="right"
    class="w-full! md:w-[36rem]!"
    :show-close-icon="!isBelowMd"
    :close-on-escape="!isActionsSheetOpen"
    @hide="emit('close')"
  >
    <template #header>
      <!-- A phone shows the file as a screen of its own: back, what it is, and more. The name moves
           under the preview, where it has the width to be read. -->
      <PhoneBackBar
        v-if="isBelowMd"
        :back-label="t('band_space_files.detail.back')"
        :title="t('band_space_files.detail.title')"
        @back="visible = false"
      >
        <template #actions>
          <Button
            icon="pi pi-ellipsis-v"
            text
            rounded
            severity="secondary"
            :aria-label="t('band_space_files.detail.more_actions')"
            aria-haspopup="dialog"
            :disabled="!file"
            @click="isActionsSheetOpen = true"
          />
        </template>
      </PhoneBackBar>
      <div v-else class="flex items-center gap-2 min-w-0 flex-1">
        <i :class="fileIconClass(file?.mime_type)" class="text-xl text-surface-500"></i>
        <FileNameHeading
          v-model:editing="isRenaming"
          :name="file?.original_name"
          :saving="filesStore.isSavingFile"
          :disabled="!file"
          @rename="commitRename"
        />
      </div>
    </template>

    <div v-if="filesStore.isLoadingActiveFile && !file" class="flex flex-col gap-3">
      <Skeleton width="100%" height="20rem" borderRadius="0.5rem" />
      <Skeleton width="60%" height="1rem" />
      <Skeleton width="80%" height="1rem" />
    </div>

    <div v-else-if="filesStore.activeFileError" class="flex flex-col items-center gap-4 py-12">
      <i class="pi pi-exclamation-circle text-4xl text-red-500"></i>
      <p class="text-sm text-surface-500">{{ filesStore.activeFileError }}</p>
    </div>

    <div v-else-if="file" class="flex flex-col gap-6">
      <div class="rounded-lg overflow-hidden border border-surface-200 dark:border-surface-700">
        <!-- A phone browser draws nothing inside an <embed>: the PDF opens in its own viewer instead. -->
        <div
          v-if="isPdf && isBelowMd"
          class="flex flex-col items-center justify-center p-6 gap-3 bg-surface-50 dark:bg-surface-950"
        >
          <i class="pi pi-file-pdf text-5xl! text-surface-400" aria-hidden="true"></i>
          <Button
            :label="t('band_space_files.detail.open_pdf')"
            icon="pi pi-external-link"
            size="small"
            @click="openPreview"
          />
          <p class="m-0 text-xs text-surface-500 dark:text-surface-400">
            {{ t('band_space_files.detail.open_pdf_hint') }}
          </p>
        </div>
        <component
          :is="previewComponent.tag"
          v-bind="previewComponent.attrs"
          v-else-if="previewComponent"
          class="block w-full bg-surface-50 dark:bg-surface-950"
        />
        <div v-else class="flex flex-col items-center justify-center p-8 gap-3 bg-surface-50 dark:bg-surface-950">
          <i :class="fileIconClass(file.mime_type)" class="text-5xl! text-surface-400" aria-hidden="true"></i>
          <p class="text-sm text-surface-500 italic">
            {{ t('band_space_files.detail.preview_unavailable') }}
          </p>
        </div>
      </div>

      <FileNameHeading
        v-if="isBelowMd"
        v-model:editing="isRenaming"
        tag="h2"
        name-class="text-lg"
        :name="file.original_name"
        :saving="filesStore.isSavingFile"
        @rename="commitRename"
      />

      <div class="grid grid-cols-2 gap-3 text-sm">
        <div>
          <p class="text-xs text-surface-400 uppercase tracking-wide">
            {{ t('band_space_files.detail.uploaded_by') }}
          </p>
          <div class="flex items-center gap-2 mt-1">
            <Avatar
              v-if="file.created_by"
              :username="file.created_by.username"
              :display-name="file.created_by.display_name"
              :picture-url="file.created_by.profile_picture_url"
              size="sm"
            />
            <span><UserName v-if="file.created_by" :username="file.created_by.username" :display-name="file.created_by.display_name" :picture-url="file.created_by.profile_picture_url" /><template v-else>{{ noValue() }}</template></span>
          </div>
        </div>
        <div>
          <p class="text-xs text-surface-400 uppercase tracking-wide">
            {{ t('band_space_files.file_list.column.size') }}
          </p>
          <p class="mt-1 tabular-nums">{{ formatBytes(file.size) }}</p>
        </div>
        <div>
          <p class="text-xs text-surface-400 uppercase tracking-wide">
            {{ t('band_space_files.detail.type') }}
          </p>
          <p class="mt-1 truncate" :title="file.mime_type">{{ fileTypeLabel(file.mime_type) ?? noValue() }}</p>
        </div>
        <div>
          <p class="text-xs text-surface-400 uppercase tracking-wide">
            {{ t('band_space_files.file_list.column.added_at') }}
          </p>
          <p class="mt-1">{{ formatDate(file.creation_datetime) }}</p>
        </div>
        <div v-if="file.update_datetime">
          <p class="text-xs text-surface-400 uppercase tracking-wide">
            {{ t('band_space_files.detail.updated_at') }}
          </p>
          <p class="mt-1">{{ formatDate(file.update_datetime) }}</p>
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-xs text-surface-400 uppercase tracking-wide">
          {{ t('band_space_files.file_list.column.tags') }}
        </label>
        <MultiSelect
          v-model="selectedTagIds"
          :aria-label="t('band_space_files.file_list.column.tags')"
          :options="filesStore.tags"
          option-label="name"
          option-value="id"
          :placeholder="t('band_space_files.detail.no_tag')"
          :disabled="filesStore.isSavingFile"
          @hide="commitTagsIfChanged"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-xs text-surface-400 uppercase tracking-wide">
          {{ t('band_space_files.detail.folder') }}
        </label>
        <Select
          :model-value="selectedFolderId"
          :aria-label="t('band_space_files.detail.folder')"
          :options="folderOptions"
          option-label="label"
          option-value="value"
          option-disabled="disabled"
          :placeholder="t('band_space_files.folder.root')"
          :disabled="filesStore.isSavingFile"
          @update:model-value="commitFolder"
        />
        <small v-if="rootRefusal" class="text-xs text-surface-500 dark:text-surface-400">
          {{ rootRefusal }}
        </small>
      </div>

      <div v-if="attachments.length > 0" class="flex flex-col gap-1">
        <label class="text-xs text-surface-400 uppercase tracking-wide">
          {{ t('band_space_files.detail.attached_to', { count: attachments.length }) }}
        </label>
        <div class="flex flex-col gap-1">
          <component
            :is="att.route ? RouterLink : 'div'"
            v-for="att in attachmentLinks"
            :key="`${att.source_type}-${att.source_id}`"
            :to="att.route"
            class="flex items-center gap-2 p-2 rounded-md border border-surface-200 dark:border-surface-700 text-sm"
            :class="
              att.route
                ? 'transition-colors hover:border-primary-500 hover:bg-surface-50 dark:hover:bg-surface-800'
                : ''
            "
          >
            <i :class="fileSourceIcon(att.source_type)"></i>
            <div class="flex-1 min-w-0">
              <div class="text-xs text-surface-400">{{ fileSourceLabel(att.source_type) }}</div>
              <div class="truncate font-medium">{{ att.source_label }}</div>
            </div>
            <i
              v-if="att.route"
              class="pi pi-arrow-right text-xs text-surface-400"
              aria-hidden="true"
            ></i>
          </component>
        </div>
        <small class="text-xs text-surface-400 italic">
          {{ t('band_space_files.detail.detach_hint') }}
        </small>
      </div>

      <FileActivityFeed :activities="filesStore.fileActivities" />
    </div>

    <BottomSheet
      v-if="isBelowMd"
      v-model:visible="isActionsSheetOpen"
      :title="file?.original_name ?? t('band_space_files.detail.title')"
    >
      <SheetAction icon="pi-pencil" @click="runSheetAction(() => (isRenaming = true))">
        {{ t('band_space_files.file_list.menu.rename') }}
      </SheetAction>
      <SheetAction icon="pi-arrows-h" @click="runSheetAction(() => emit('move', file))">
        {{ t('band_space_files.file_move.submit') }}
      </SheetAction>
    </BottomSheet>

    <template #footer>
      <!-- Four equal buttons across a phone, a label under each icon; a row of buttons on the right on a
           computer. « Télécharger » is always offered: a preview is no download. -->
      <div class="flex gap-2 max-md:-mx-2 max-md:gap-0 md:flex-wrap md:justify-end">
        <Button
          v-for="action in footerActions"
          :key="action.key"
          :label="action.label"
          :icon="action.icon"
          size="small"
          :severity="action.severity"
          :text="isBelowMd"
          :disabled="action.disabled"
          :loading="action.loading"
          v-tooltip.top="action.tooltip"
          class="max-md:flex-1 max-md:flex-col! max-md:gap-1! max-md:px-1! max-md:text-xs!"
          @click="action.run"
        />
      </div>
    </template>
  </Drawer>
</template>

<script setup>
import Button from 'primevue/button'
import Drawer from 'primevue/drawer'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { useIsBelowMd } from '../../../composables/useIsPhone.js'
import {
  deleteBlockingAttachments,
  fileSourceAttachedMessage,
  fileSourceIcon,
  fileSourceLabel,
  fileSourceRoute
} from '../../../constants/fileSources.js'
import { intlLocale, noValue, treeIndent } from '../../../i18n/formatLocale.js'
import { useBandSpaceStore } from '../../../store/bandSpace/bandSpace.js'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { isFileCreatorOrAdmin } from '../../../utils/bandSpaceFilePermissions.js'
import { formatDateWithWeekday } from '../../../utils/date.js'
import { rootDestinationRefusal } from '../../../utils/fileListing.js'
import { fileIconClass, fileTypeLabel } from '../../../utils/fileType.js'
import { formatBytes } from '../../../utils/formatBytes.js'
import BottomSheet from '../../Global/BottomSheet.vue'
import PhoneBackBar from '../../Global/PhoneBackBar.vue'
import SheetAction from '../../Global/SheetAction.vue'
import Avatar from '../../User/Avatar.vue'
import UserName from '../../User/UserName.vue'
import FileActivityFeed from './FileActivityFeed.vue'
import FileNameHeading from './FileNameHeading.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  autoStartRename: { type: Boolean, default: false }
})

const emit = defineEmits(['close', 'share', 'versions', 'move', 'deleted'])
const { t } = useI18n()

const visible = defineModel('visible', { type: Boolean, default: false })

const filesStore = useBandFilesStore()
const userSecurityStore = useUserSecurityStore()
const bandSpaceStore = useBandSpaceStore()
const confirm = useConfirm()

const file = computed(() => filesStore.activeFile)
const isAdmin = computed(() => bandSpaceStore.getById(props.bandSpaceId)?.role === 'admin')

const isBelowMd = useIsBelowMd()
const isRenaming = ref(false)
// The phone's ⋮ sheet. Escape closes it alone rather than the file under it.
const isActionsSheetOpen = ref(false)

watch(visible, (isVisible) => {
  if (!isVisible) isActionsSheetOpen.value = false
})
const initialTagIds = ref([])
const selectedTagIds = ref([])

const selectedFolderId = computed(() => file.value?.folder_id ?? null)

const canDelete = computed(() =>
  isFileCreatorOrAdmin(file.value, userSecurityStore.userProfile?.id, isAdmin.value)
)

const attachments = computed(() => file.value?.attachments ?? [])

/** Each attachment paired with where it sends the member, null when there is nowhere to go. */
const attachmentLinks = computed(() =>
  attachments.value.map((att) => ({
    ...att,
    route: fileSourceRoute(att.source_type, props.bandSpaceId, att.source_id)
  }))
)

const blockingAttachments = computed(() => deleteBlockingAttachments(attachments.value))
const isAttachedToSource = computed(() => blockingAttachments.value.length > 0)

const attachedSourceMessage = computed(() => {
  const list = blockingAttachments.value
  if (list.length === 0) return null
  if (list.length > 1) {
    return t('band_space_files.detail.attached_to_several', { count: list.length })
  }

  return fileSourceAttachedMessage(list[0].source_type)
})

const deleteDisabledReason = computed(() => {
  if (isAttachedToSource.value) return attachedSourceMessage.value
  if (!canDelete.value) return t('band_space_files.detail.delete_not_owner')
  return null
})

/** Why the root is not a destination for this file, or null when it is one. */
const rootRefusal = computed(() => rootDestinationRefusal(file.value, filesStore.virtualFolders))

const folderOptions = computed(() => {
  const out = [
    {
      label: t('band_space_files.folder.root'),
      value: null,
      disabled: rootRefusal.value !== null
    }
  ]
  const walk = (nodes, depth) => {
    for (const node of nodes) {
      out.push({ label: treeIndent(depth) + node.name, value: node.id })
      if (Array.isArray(node.children) && node.children.length > 0) {
        walk(node.children, depth + 1)
      }
    }
  }
  walk(filesStore.folders, 0)
  return out
})

// Cache-bust on current_version_id so preview/audio/PDF refresh when a new
// version is uploaded or a previous one is restored.
const previewUrl = computed(() => {
  const f = file.value
  if (!f?.download_url) return null
  const sep = f.download_url.includes('?') ? '&' : '?'

  return f.current_version_id ? `${f.download_url}${sep}v=${f.current_version_id}` : f.download_url
})

const previewComponent = computed(() => {
  const f = file.value
  if (!f?.mime_type) return null
  const url = previewUrl.value
  if (f.mime_type === 'application/pdf') {
    return {
      tag: 'embed',
      attrs: { src: url, type: 'application/pdf', style: 'height: 60vh; width: 100%;' }
    }
  }
  if (f.mime_type.startsWith('image/')) {
    return {
      tag: 'img',
      attrs: {
        src: url,
        alt: f.original_name,
        style: 'max-height: 60vh; width: 100%; object-fit: contain;'
      }
    }
  }
  if (f.mime_type.startsWith('audio/')) {
    return {
      tag: 'audio',
      attrs: { src: url, controls: true, preload: 'metadata', style: 'width: 100%; padding: 1rem;' }
    }
  }
  return null
})

watch(
  file,
  (f) => {
    if (f) {
      initialTagIds.value = (f.tags || []).map((t) => t.id)
      selectedTagIds.value = [...initialTagIds.value]
      if (props.autoStartRename) {
        isRenaming.value = true
      }
    }
  },
  { immediate: true }
)

const isPdf = computed(() => file.value?.mime_type === 'application/pdf')

const footerActions = computed(() => {
  const actions = [
    {
      key: 'download',
      label: t('band_space_files.file_list.menu.download'),
      icon: 'pi pi-download',
      severity: 'secondary',
      disabled: !file.value,
      run: openDownload
    }
  ]
  if (isAdmin.value) {
    actions.push({
      key: 'share',
      label: t('band_space_files.file_list.menu.share'),
      icon: 'pi pi-share-alt',
      severity: 'secondary',
      disabled: !file.value,
      run: () => emit('share', file.value)
    })
  }
  actions.push(
    {
      key: 'versions',
      label: t('band_space_files.file_list.menu.versions'),
      icon: 'pi pi-history',
      severity: 'secondary',
      disabled: !file.value,
      run: () => emit('versions', file.value)
    },
    {
      key: 'delete',
      label: t('band_space_files.common.delete'),
      icon: 'pi pi-trash',
      severity: 'danger',
      disabled: !canDelete.value || isAttachedToSource.value,
      loading: filesStore.isDeletingFile,
      tooltip: deleteDisabledReason.value,
      run: confirmDelete
    }
  )

  return actions
})

function runSheetAction(action) {
  isActionsSheetOpen.value = false
  action()
}

async function commitRename(trimmed) {
  if (!file.value) return
  await filesStore.updateFile(props.bandSpaceId, file.value.id, { original_name: trimmed })
  isRenaming.value = false
  filesStore.fetchFileActivities(props.bandSpaceId, file.value.id)
}

async function commitTagsIfChanged() {
  if (!file.value) return
  const before = [...initialTagIds.value].sort().join(',')
  const after = [...selectedTagIds.value].sort().join(',')
  if (before === after) return
  await filesStore.updateFile(props.bandSpaceId, file.value.id, { tag_ids: selectedTagIds.value })
  initialTagIds.value = [...selectedTagIds.value]
  filesStore.fetchFileActivities(props.bandSpaceId, file.value.id)
}

async function commitFolder(value) {
  if (!file.value) return
  if (value === file.value.folder_id) return
  // Same guard as the move dialog: the root is not a place an attached file can be in, whatever the
  // field in front of it offers.
  if (value === null && rootRefusal.value !== null) return
  await filesStore.updateFile(props.bandSpaceId, file.value.id, { folder_id: value })
  filesStore.fetchFileActivities(props.bandSpaceId, file.value.id)
}

function confirmDelete() {
  if (!file.value) return
  const name = file.value.original_name
  const fileId = file.value.id
  confirm.require({
    message: t('band_space_files.file_list.trash_confirm.message', {
      name,
      days: filesStore.trashRetentionDays
    }),
    header: t('band_space_files.file_list.trash_confirm.header'),
    icon: 'pi pi-trash',
    acceptLabel: t('band_space_files.common.delete'),
    rejectLabel: t('band_space_files.common.cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      await filesStore.deleteFile(props.bandSpaceId, fileId)
      emit('deleted', fileId)
      visible.value = false
    }
  })
}

/** The PDF in the browser's own viewer, at its current version as the preview would show it. */
function openPreview() {
  if (previewUrl.value) window.open(previewUrl.value, '_blank', 'noopener')
}

function openDownload() {
  if (file.value?.download_url) {
    window.open(file.value.download_url, '_blank', 'noopener')
  }
}

/** « mer. 4 nov. 2026 à 08:56 »: the day placed in its week, then the time. */
function formatDate(iso) {
  if (!iso) return noValue()
  const time = new Date(iso).toLocaleTimeString(intlLocale(), {
    hour: '2-digit',
    minute: '2-digit'
  })

  return t('band_space_files.detail.date_at', { date: formatDateWithWeekday(iso), time })
}
</script>
