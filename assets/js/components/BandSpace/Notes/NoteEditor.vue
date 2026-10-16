<template>
  <div class="flex flex-col h-full">
    <!-- Title -->
    <div class="p-4 border-b border-surface-200 dark:border-surface-700">
      <div class="flex items-center justify-between">
        <div class="flex items-center flex-1 min-w-0">
          <div ref="emojiPickerRef" class="relative">
            <button
              class="text-2xl w-10 h-10 flex items-center justify-center rounded-lg hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors cursor-pointer"
              @click="showEmojiPicker = !showEmojiPicker"
            >
              {{ note.emoji || '📄' }}
            </button>
            <div v-if="showEmojiPicker" class="absolute top-12 left-0 z-50">
              <EmojiPicker
                :native="true"
                :disable-skin-tones="true"
                theme="auto"
                @select="handleEmojiSelect"
              />
            </div>
          </div>
          <InputText
            v-model="editableTitle"
            class="w-full text-xl font-semibold border-0 shadow-none bg-transparent"
            :placeholder="t('band_space_notes.editor.title_placeholder')"
            @blur="handleTitleBlur"
            @keydown.enter="$event.target.blur()"
          />
        </div>
        <span v-if="saveStatus" class="text-xs text-surface-500 whitespace-nowrap ml-3">
          <template v-if="saveStatus === 'saving'">
            <i class="pi pi-spin pi-spinner mr-1"></i>{{ t('band_space_notes.editor.save_status.saving') }}
          </template>
          <template v-else-if="saveStatus === 'saved'">
            <i class="pi pi-check mr-1 text-green-500"></i>{{ t('band_space_notes.editor.save_status.saved') }}
          </template>
          <template v-else-if="saveStatus === 'conflict'">
            <i class="pi pi-exclamation-triangle mr-1 text-orange-500"></i>{{ t('band_space_notes.editor.save_status.conflict') }}
          </template>
          <template v-else-if="saveStatus === 'error'">
            <i class="pi pi-times mr-1 text-red-500"></i>{{ t('band_space_notes.editor.save_status.error') }}
          </template>
        </span>
      </div>
      <i18n-t keypath="band_space_notes.editor.created_by" tag="p" class="mt-1 ml-10 pl-2 text-xs text-surface-500 dark:text-surface-400">
        <template #author>
          <UserName :username="note.created_by.username" :display-name="note.created_by.display_name" />
        </template>
      </i18n-t>
    </div>

    <!-- Conflict notice: stays until the member acts on it, autosave is over for this editor -->
    <Message v-if="saveStatus === 'conflict'" severity="warn" :closable="false" class="m-4">
      <div class="flex flex-col gap-3">
        <p class="m-0">
          {{ t('band_space_notes.editor.conflict_notice.message') }}
        </p>
        <div class="flex flex-wrap gap-2">
          <Button
            :label="t('band_space_notes.editor.conflict_notice.copy_my_text')"
            icon="pi pi-copy"
            size="small"
            @click="handleCopyMyText"
          />
          <Button
            :label="t('band_space_notes.editor.conflict_notice.reload_note')"
            icon="pi pi-refresh"
            severity="secondary"
            size="small"
            :loading="isReloading"
            @click="emit('reload')"
          />
        </div>
      </div>
    </Message>

    <!-- Toolbar -->
    <div class="sticky top-0 z-30 bg-surface-50 dark:bg-surface-700 border-b border-surface-200 dark:border-surface-600 p-2">
      <div class="flex flex-wrap items-center gap-1">
        <!-- Text formatting -->
        <div class="flex items-center gap-1 pr-2 border-r border-surface-200 dark:border-surface-700">
          <Button
            v-tooltip.bottom="t('band_space_notes.editor.toolbar.bold')"
            :severity="editor?.isActive('bold') ? 'primary' : 'secondary'"
            text
            size="small"
            class="font-bold"
            @click="editor?.chain().focus().toggleBold().run()"
          >
            {{ t('band_space_notes.editor.toolbar.bold_glyph') }}
          </Button>
          <Button
            v-tooltip.bottom="t('band_space_notes.editor.toolbar.italic')"
            :severity="editor?.isActive('italic') ? 'primary' : 'secondary'"
            text
            size="small"
            class="italic"
            @click="editor?.chain().focus().toggleItalic().run()"
          >
            {{ t('band_space_notes.editor.toolbar.italic_glyph') }}
          </Button>
          <Button
            v-tooltip.bottom="t('band_space_notes.editor.toolbar.link')"
            :aria-label="t('band_space_notes.editor.toolbar.link')"
            icon="pi pi-link"
            :severity="editor?.isActive('link') ? 'primary' : 'secondary'"
            text
            size="small"
            @click="toggleLinkPopover"
          />
          <Popover ref="linkPopover">
            <form class="flex flex-col gap-2 w-72" @submit.prevent="handleApplyLink">
              <label for="note-link-address" class="text-sm font-medium">{{ t('band_space_notes.editor.link_popover.address_label') }}</label>
              <InputText
                id="note-link-address"
                v-model="linkAddress"
                :placeholder="t('band_space_notes.editor.link_popover.address_placeholder')"
                size="small"
                autofocus
                :invalid="!!linkError"
                :aria-describedby="linkError ? 'note-link-address-error' : undefined"
              />
              <small v-if="linkError" id="note-link-address-error" class="text-red-600 dark:text-red-400">{{ linkError }}</small>
              <div class="flex justify-end gap-2">
                <Button
                  v-if="isEditingLink"
                  :label="t('band_space_notes.editor.link_popover.remove')"
                  severity="danger"
                  text
                  size="small"
                  @click="handleRemoveLink"
                />
                <Button type="submit" :label="t('band_space_notes.editor.link_popover.apply')" size="small" />
              </div>
            </form>
          </Popover>
        </div>

        <!-- Headings -->
        <div class="flex items-center gap-1 px-2 border-r border-surface-200 dark:border-surface-700">
          <Button
            v-tooltip.bottom="t('band_space_notes.editor.toolbar.heading_1')"
            :aria-label="t('band_space_notes.editor.toolbar.heading_1')"
            :label="t('band_space_notes.editor.toolbar.heading_1_short')"
            :severity="editor?.isActive('heading', { level: 1 }) ? 'primary' : 'secondary'"
            text
            size="small"
            @click="editor?.chain().focus().toggleHeading({ level: 1 }).run()"
          />
          <Button
            v-tooltip.bottom="t('band_space_notes.editor.toolbar.heading_2')"
            :aria-label="t('band_space_notes.editor.toolbar.heading_2')"
            :label="t('band_space_notes.editor.toolbar.heading_2_short')"
            :severity="editor?.isActive('heading', { level: 2 }) ? 'primary' : 'secondary'"
            text
            size="small"
            @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
          />
          <Button
            v-tooltip.bottom="t('band_space_notes.editor.toolbar.heading_3')"
            :aria-label="t('band_space_notes.editor.toolbar.heading_3')"
            :label="t('band_space_notes.editor.toolbar.heading_3_short')"
            :severity="editor?.isActive('heading', { level: 3 }) ? 'primary' : 'secondary'"
            text
            size="small"
            @click="editor?.chain().focus().toggleHeading({ level: 3 }).run()"
          />
        </div>

        <!-- Image -->
        <div class="flex items-center gap-1 px-2 border-r border-surface-200 dark:border-surface-700">
          <Button
            v-tooltip.bottom="t('band_space_notes.editor.toolbar.insert_image')"
            :aria-label="t('band_space_notes.editor.toolbar.insert_image')"
            icon="pi pi-image"
            severity="secondary"
            text
            size="small"
            :disabled="isUploadingImage"
            @click="triggerImagePicker"
          />
          <input
            ref="imageInputRef"
            type="file"
            accept="image/png,image/jpeg,image/gif,image/webp"
            class="hidden"
            @change="handleImageInput"
          />
        </div>

        <!-- Lists -->
        <div class="flex items-center gap-1 px-2 border-r border-surface-200 dark:border-surface-700">
          <Button
            v-tooltip.bottom="t('band_space_notes.editor.toolbar.bullet_list')"
            :aria-label="t('band_space_notes.editor.toolbar.bullet_list')"
            icon="pi pi-list"
            :severity="editor?.isActive('bulletList') ? 'primary' : 'secondary'"
            text
            size="small"
            @click="editor?.chain().focus().toggleBulletList().run()"
          />
          <Button
            v-tooltip.bottom="t('band_space_notes.editor.toolbar.ordered_list')"
            :aria-label="t('band_space_notes.editor.toolbar.ordered_list')"
            icon="pi pi-sort-numeric-down"
            :severity="editor?.isActive('orderedList') ? 'primary' : 'secondary'"
            text
            size="small"
            @click="editor?.chain().focus().toggleOrderedList().run()"
          />
          <Button
            v-tooltip.bottom="t('band_space_notes.editor.toolbar.checklist')"
            :aria-label="t('band_space_notes.editor.toolbar.checklist')"
            icon="pi pi-list-check"
            :severity="editor?.isActive('taskList') ? 'primary' : 'secondary'"
            text
            size="small"
            @click="editor?.chain().focus().toggleTaskList().run()"
          />
        </div>

        <!-- Blocks -->
        <div class="flex items-center gap-1 px-2 border-r border-surface-200 dark:border-surface-700">
          <Button
            v-tooltip.bottom="t('band_space_notes.editor.toolbar.quote')"
            :aria-label="t('band_space_notes.editor.toolbar.quote')"
            icon="pi pi-comment"
            :severity="editor?.isActive('blockquote') ? 'primary' : 'secondary'"
            text
            size="small"
            @click="editor?.chain().focus().toggleBlockquote().run()"
          />
          <Button
            v-tooltip.bottom="t('band_space_notes.editor.toolbar.horizontal_rule')"
            :aria-label="t('band_space_notes.editor.toolbar.horizontal_rule')"
            icon="pi pi-minus"
            severity="secondary"
            text
            size="small"
            @click="editor?.chain().focus().setHorizontalRule().run()"
          />
        </div>

        <!-- Table -->
        <div class="flex items-center gap-1 px-2 border-r border-surface-200 dark:border-surface-700">
          <Button
            v-tooltip.bottom="t('band_space_notes.editor.toolbar.insert_table')"
            :aria-label="t('band_space_notes.editor.toolbar.insert_table')"
            icon="pi pi-table"
            severity="secondary"
            text
            size="small"
            @click="editor?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: false }).run()"
          />
        </div>

        <!-- Undo/Redo -->
        <div class="flex items-center gap-1 px-2">
          <Button
            v-tooltip.bottom="t('band_space_notes.editor.toolbar.undo')"
            :aria-label="t('band_space_notes.editor.toolbar.undo')"
            icon="pi pi-undo"
            severity="secondary"
            text
            size="small"
            :disabled="!editor?.can().undo()"
            @click="editor?.chain().focus().undo().run()"
          />
          <Button
            v-tooltip.bottom="t('band_space_notes.editor.toolbar.redo')"
            :aria-label="t('band_space_notes.editor.toolbar.redo')"
            icon="pi pi-refresh"
            severity="secondary"
            text
            size="small"
            :disabled="!editor?.can().redo()"
            @click="editor?.chain().focus().redo().run()"
          />
        </div>
      </div>
    </div>

    <!-- Table bubble menu -->
    <BubbleMenu
      v-if="editor"
      :editor="editor"
      :tippy-options="{ duration: 150, placement: 'top' }"
      :should-show="shouldShowTableMenu"
    >
      <div class="flex items-center gap-0.5 bg-surface-0 dark:bg-surface-800 border border-surface-200 dark:border-surface-600 rounded-lg shadow-lg p-1">
        <Button
          v-tooltip.top="t('band_space_notes.editor.table_menu.column_before')"
          :aria-label="t('band_space_notes.editor.table_menu.column_before')"
          icon="pi pi-chevron-left"
          severity="secondary"
          text
          size="small"
          class="!w-7 !h-7"
          @click="editor?.chain().focus().addColumnBefore().run()"
        />
        <Button
          v-tooltip.top="t('band_space_notes.editor.table_menu.column_after')"
          :aria-label="t('band_space_notes.editor.table_menu.column_after')"
          icon="pi pi-chevron-right"
          severity="secondary"
          text
          size="small"
          class="!w-7 !h-7"
          @click="editor?.chain().focus().addColumnAfter().run()"
        />
        <Button
          v-tooltip.top="t('band_space_notes.editor.table_menu.delete_column')"
          :aria-label="t('band_space_notes.editor.table_menu.delete_column')"
          icon="pi pi-minus"
          severity="danger"
          text
          size="small"
          class="!w-7 !h-7"
          @click="editor?.chain().focus().deleteColumn().run()"
        />
        <div class="w-px h-5 bg-surface-200 dark:bg-surface-600 mx-0.5" />
        <Button
          v-tooltip.top="t('band_space_notes.editor.table_menu.row_before')"
          :aria-label="t('band_space_notes.editor.table_menu.row_before')"
          icon="pi pi-chevron-up"
          severity="secondary"
          text
          size="small"
          class="!w-7 !h-7"
          @click="editor?.chain().focus().addRowBefore().run()"
        />
        <Button
          v-tooltip.top="t('band_space_notes.editor.table_menu.row_after')"
          :aria-label="t('band_space_notes.editor.table_menu.row_after')"
          icon="pi pi-chevron-down"
          severity="secondary"
          text
          size="small"
          class="!w-7 !h-7"
          @click="editor?.chain().focus().addRowAfter().run()"
        />
        <Button
          v-tooltip.top="t('band_space_notes.editor.table_menu.delete_row')"
          :aria-label="t('band_space_notes.editor.table_menu.delete_row')"
          icon="pi pi-minus-circle"
          severity="danger"
          text
          size="small"
          class="!w-7 !h-7"
          @click="editor?.chain().focus().deleteRow().run()"
        />
        <div class="w-px h-5 bg-surface-200 dark:bg-surface-600 mx-0.5" />
        <Button
          v-tooltip.top="t('band_space_notes.editor.table_menu.delete_table')"
          :aria-label="t('band_space_notes.editor.table_menu.delete_table')"
          icon="pi pi-trash"
          severity="danger"
          text
          size="small"
          class="!w-7 !h-7"
          @click="editor?.chain().focus().deleteTable().run()"
        />
      </div>
    </BubbleMenu>

    <!-- Editor content -->
    <div class="flex-1 p-4 overflow-y-auto">
      <EditorContent :editor="editor" class="note-editor" />
    </div>
  </div>
</template>

<script setup>
import Image from '@tiptap/extension-image'
import { TaskItem, TaskList } from '@tiptap/extension-list'
import { EditorContent } from '@tiptap/vue-3'
import { BubbleMenu } from '@tiptap/vue-3/menus'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Popover from 'primevue/popover'
import { useToast } from 'primevue/usetoast'
import EmojiPicker from 'vue3-emoji-picker'
import UserName from '../../User/UserName.vue'
import 'vue3-emoji-picker/css'
import { onClickOutside } from '@vueuse/core'
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import bandSpaceNoteImagesApi from '../../../api/bandSpace/band-space-note-images.js'
import { useRichTextEditor } from '../../../composables/useRichTextEditor.js'
import { useBandSpaceNotesStore } from '../../../store/bandSpace/bandSpaceNotes.js'
import { checkLinkAddress } from '../../../utils/noteLinkAddress.js'

const props = defineProps({
  note: { type: Object, required: true },
  bandSpaceId: { type: String, required: true },
  saveStatus: { type: String, default: null },
  isReloading: { type: Boolean, default: false }
})

const emit = defineEmits(['update-title', 'update-emoji', 'reload', 'pending-edits-change'])

const toast = useToast()
const { t } = useI18n()
const notesStore = useBandSpaceNotesStore()

const showEmojiPicker = ref(false)
const emojiPickerRef = ref(null)

onClickOutside(emojiPickerRef, () => {
  showEmojiPicker.value = false
})

const editableTitle = ref(props.note.title)

// Track image src URLs so we can fire detach for any image removed from the
// editor. The download URL embeds the file's UUID after `/files/`. Matches
// both the legacy current-version path (`/files/{uuid}/download`) and the
// pinned per-version path (`/files/{uuid}/versions/{n}/download`).
const trackedImageSrcs = ref(new Set())
const FILE_ID_RE = /\/files\/([0-9a-f-]{36})(?:\/versions\/\d+)?\/download/i

function collectImageSrcs(json) {
  const out = new Set()
  const walk = (node) => {
    if (!node) return
    if (node.type === 'image' && node.attrs?.src) {
      out.add(node.attrs.src)
    }
    if (Array.isArray(node.content)) {
      node.content.forEach(walk)
    }
  }
  walk(json)
  return out
}

function detachRemovedImages(json) {
  const current = collectImageSrcs(json)
  const removed = [...trackedImageSrcs.value].filter((src) => !current.has(src))
  trackedImageSrcs.value = current
  for (const src of removed) {
    const m = src.match(FILE_ID_RE)
    if (!m) continue
    bandSpaceNoteImagesApi.detach(props.bandSpaceId, props.note.id, m[1]).catch(() => {
      // Silent: file may already be detached, or race with another tab.
    })
  }
}

onMounted(() => {
  trackedImageSrcs.value = collectImageSrcs(props.note.content)
})

/** TipTap's own label is English, and names the nested items too: only the item's own line here. */
function checklistItemLabel(node) {
  const text = node.firstChild?.textContent.trim()

  return text
    ? t('band_space_notes.editor.checklist_item', { text })
    : t('band_space_notes.editor.checklist_item_empty')
}

// Shared with the tech rider section editor. Images are Notes only, and so is the detach
// pass on save, which is why it lives in the callback rather than in the composable.
const { editor, stopSaving, hasPendingEdits } = useRichTextEditor({
  content: props.note.content,
  placeholder: t('band_space_notes.editor.content_placeholder'),
  // « Titre 1 » and checklists are a note's only, as on the phone (#1175).
  headingLevels: [1, 2, 3],
  extensions: [
    Image.configure({ inline: false, allowBase64: false }),
    TaskList,
    TaskItem.configure({ nested: true, a11y: { checkboxLabel: checklistItemLabel } })
  ],
  onSave: (json) => {
    detachRemovedImages(json)

    // The revision travels with the save because this component is the only place that still knows
    // it on the unmount flush: by then the store has already moved on to whichever note was clicked.
    //
    // Returned rather than fired and forgotten, because that is what holds the next save until this
    // one has answered. Two saves in flight both name the revision they started from, so the second
    // comes back refused as though another member had written, when nobody but this editor has.
    return saveContent(props.note.id, json, props.note.content_version)
  }
})

/**
 * Nobody pressed save here, a timer did, so a failure has to say so out loud. The status in the
 * header is eleven pixels wide, and once the note has been left it is not on screen at all, which
 * is exactly when the last save of a writing session goes out.
 */
async function saveContent(noteId, content, contentVersion) {
  const { status, noteTitle } = await notesStore.updateNoteContent(
    props.bandSpaceId,
    noteId,
    content,
    contentVersion
  )
  if (status === 'saved') return

  if (status === 'conflict') {
    // Ends the loop here rather than from a watcher on the status, and that placement is the whole
    // guarantee: the saver only re-checks `stopped` once this callback has been awaited, so a save
    // queued behind this one while the request was in flight can no longer go out. Stopping from a
    // watcher instead would put that behind Vue's scheduler, one flush option or one extra await
    // away from letting the refused document be sent again. See "is stopped in time by a save that
    // stops the loop itself" in debouncedSaver.test.js.
    //
    // The editor stays writable so the member can select and copy what they wrote; the notice
    // above says plainly that none of it is being saved any more.
    stopSaving()

    toast.add({
      severity: 'warn',
      summary: t('band_space_notes.editor.save_conflict.summary'),
      detail: noteTitle
        ? t('band_space_notes.editor.save_conflict.detail_titled', { title: noteTitle })
        : t('band_space_notes.editor.save_conflict.detail_untitled'),
      life: 8000
    })

    return
  }

  toast.add({
    severity: 'error',
    summary: t('band_space_notes.editor.save_error.summary'),
    detail: noteTitle
      ? t('band_space_notes.editor.save_error.detail_titled', { title: noteTitle })
      : t('band_space_notes.editor.save_error.detail_untitled'),
    life: 8000
  })
}

// The guard that protects this text lives one level up, on the route and on the tab, because both
// destroy this component without asking.
watch(hasPendingEdits, (pending) => emit('pending-edits-change', pending))

// Unmounting flushes what was waiting, so the save owns it from here and the guard must not go on
// warning about typing that is on its way to the server.
onBeforeUnmount(() => emit('pending-edits-change', false))

async function handleCopyMyText() {
  try {
    await navigator.clipboard.writeText(editor.value?.getText() ?? '')
    toast.add({
      severity: 'success',
      summary: t('band_space_notes.editor.copy.success_summary'),
      detail: t('band_space_notes.editor.copy.success_detail'),
      life: 4000
    })
  } catch {
    toast.add({
      severity: 'error',
      summary: t('band_space_notes.editor.copy.error_summary'),
      detail: t('band_space_notes.editor.copy.error_detail'),
      life: 6000
    })
  }
}

const linkPopover = ref(null)
const linkAddress = ref('')
const linkError = ref('')
const isEditingLink = ref(false)

function toggleLinkPopover(event) {
  isEditingLink.value = editor.value?.isActive('link') ?? false
  linkAddress.value = editor.value?.getAttributes('link').href ?? ''
  linkError.value = ''
  linkPopover.value?.toggle(event)
}

function handleApplyLink() {
  // A link pasted or written on the phone may hold an address the button would refuse (`tel:`, a
  // relative path): leaving it as it was is not a new address, so it is not checked.
  if (
    isEditingLink.value &&
    linkAddress.value.trim() === editor.value?.getAttributes('link').href
  ) {
    linkPopover.value?.hide()
    return
  }

  const result = checkLinkAddress(linkAddress.value)
  if (result.error) {
    linkError.value = result.error
    return
  }

  const currentEditor = editor.value
  if (!currentEditor) return

  // With nothing selected and no link under the cursor there is no text to put the link on, so the
  // address becomes the text. The stored mark is dropped so what is typed next is not part of it.
  if (currentEditor.state.selection.empty && !currentEditor.isActive('link')) {
    currentEditor
      .chain()
      .focus()
      .insertContent({
        type: 'text',
        text: result.href,
        marks: [{ type: 'link', attrs: { href: result.href } }]
      })
      .unsetMark('link')
      .run()
  } else if (currentEditor.state.selection.empty) {
    // The cursor sits in a link: the whole link takes the new address.
    currentEditor.chain().focus().extendMarkRange('link').setLink({ href: result.href }).run()
  } else {
    currentEditor.chain().focus().setLink({ href: result.href }).run()
  }
  linkPopover.value?.hide()
}

function handleRemoveLink() {
  editor.value?.chain().focus().extendMarkRange('link').unsetLink().run()
  linkPopover.value?.hide()
}

function shouldShowTableMenu({ editor }) {
  return editor.isActive('table')
}

function handleEmojiSelect(emoji) {
  showEmojiPicker.value = false
  emit('update-emoji', emoji.i)
}

function handleTitleBlur() {
  const trimmed = editableTitle.value.trim()
  if (trimmed && trimmed !== props.note.title) {
    emit('update-title', trimmed)
  }
}

const imageInputRef = ref(null)
const isUploadingImage = ref(false)

function triggerImagePicker() {
  if (isUploadingImage.value) return
  imageInputRef.value?.click()
}

async function handleImageInput(event) {
  const file = event.target.files?.[0]
  if (file) {
    await uploadAndInsertImage(file)
  }
  if (imageInputRef.value) imageInputRef.value.value = ''
}

function buildPinnedImageSrc(uploaded) {
  if (!uploaded?.id) return null
  if (uploaded.current_version_number) {
    return Routing.generate('api_band_space_files_version_download', {
      bandSpaceId: props.bandSpaceId,
      id: uploaded.id,
      versionNumber: uploaded.current_version_number
    })
  }
  return uploaded.download_url ?? null
}

async function uploadAndInsertImage(file) {
  isUploadingImage.value = true
  try {
    const result = await bandSpaceNoteImagesApi.upload(props.bandSpaceId, props.note.id, file)
    const pinnedSrc = buildPinnedImageSrc(result?.file)
    if (pinnedSrc) {
      editor.value?.chain().focus().setImage({ src: pinnedSrc }).run()
    }
    if (result?.quotaApproaching) {
      toast.add({
        severity: 'warn',
        summary: t('band_space_notes.editor.image.quota_summary'),
        detail: t('band_space_notes.editor.image.quota_detail'),
        life: 6000
      })
    }
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: t('band_space_notes.editor.image.import_error_summary'),
      detail: e.message,
      life: 5000
    })
  } finally {
    isUploadingImage.value = false
  }
}
</script>

<style>
.note-editor .tiptap {
  min-height: 300px;
  outline: none;
}

.note-editor .tiptap h1 {
  font-size: 1.875rem;
  font-weight: 700;
  margin: 1.5rem 0 0.75rem;
  line-height: 1.25;
}

.note-editor .tiptap h2 {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 1.25rem 0 0.5rem;
  line-height: 1.3;
}

.note-editor .tiptap h3 {
  font-size: 1.25rem;
  font-weight: 600;
  margin: 1rem 0 0.5rem;
  line-height: 1.4;
}

.note-editor .tiptap a {
  color: var(--p-primary-color);
  text-decoration: underline;
}

.note-editor .tiptap p.is-editor-empty:first-child::before {
  content: attr(data-placeholder);
  float: left;
  color: var(--p-surface-400);
  pointer-events: none;
  height: 0;
}

.note-editor .tiptap ul {
  padding-left: 1.5rem;
  margin-bottom: 0.75rem;
  list-style-type: disc;
}

.note-editor .tiptap ol {
  padding-left: 1.5rem;
  margin-bottom: 0.75rem;
  list-style-type: decimal;
}

.note-editor .tiptap li {
  margin-bottom: 0.25rem;
}

.note-editor .tiptap li p {
  margin: 0;
}

.note-editor .tiptap ul[data-type='taskList'] {
  padding-left: 0.25rem;
  list-style: none;
}

.note-editor .tiptap ul[data-type='taskList'] li {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
}

.note-editor .tiptap ul[data-type='taskList'] li > label {
  flex: 0 0 auto;
  margin-top: 0.2rem;
}

.note-editor .tiptap ul[data-type='taskList'] li > div {
  flex: 1 1 auto;
}

.note-editor .tiptap ul[data-type='taskList'] li[data-checked='true'] > div > p {
  color: var(--p-surface-500);
  text-decoration: line-through;
}

.note-editor .tiptap ul[data-type='taskList'] ul[data-type='taskList'] {
  margin: 0.25rem 0 0;
  padding-left: 1rem;
}

.note-editor .tiptap blockquote {
  border-left: 4px solid var(--p-surface-300);
  padding-left: 1rem;
  margin-left: 0;
  margin-right: 0;
  font-style: italic;
  color: var(--p-surface-500);
}

.note-editor .tiptap hr {
  border: none;
  border-top: 2px solid var(--p-surface-300);
  margin: 1.5rem 0;
}

.note-editor .tiptap table {
  border-collapse: collapse;
  width: 100%;
  margin: 1rem 0;
  overflow: hidden;
}

.note-editor .tiptap table td {
  border: 1px solid var(--p-surface-300);
  padding: 0.5rem 0.75rem;
  position: relative;
  min-width: 80px;
  vertical-align: top;
}

.note-editor .tiptap table .selectedCell::after {
  content: '';
  position: absolute;
  inset: 0;
  background: rgba(59, 130, 246, 0.1);
  pointer-events: none;
}

.note-editor .tiptap table .column-resize-handle {
  position: absolute;
  right: -2px;
  top: 0;
  bottom: -2px;
  width: 4px;
  background-color: #3b82f6;
  pointer-events: none;
}

</style>
