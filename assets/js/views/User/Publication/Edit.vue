<template>
  <div class="py-6 md:py-10">
    <div v-if="publicationEditStore.isLoading" class="flex justify-center py-12">
      <ProgressSpinner />
    </div>

    <div v-else-if="!publicationEditStore.publication" class="flex flex-col items-center justify-center py-12">
      <i class="pi pi-exclamation-triangle text-4xl text-orange-500 mb-4" />
      <p class="text-lg font-medium text-surface-700 dark:text-surface-200">Publication introuvable</p>
      <Button
        label="Retour aux publications"
        icon="pi pi-arrow-left"
        class="mt-4"
        @click="router.push({ name: 'app_user_publications' })"
      />
    </div>

    <div v-else class="flex flex-col gap-6">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex items-center gap-4 min-w-0">
          <Button
            icon="pi pi-arrow-left"
            aria-label="Retour"
            severity="secondary"
            text
            rounded
            @click="router.push({ name: 'app_user_publications' })"
          />
          <div class="flex flex-wrap items-center gap-3 min-w-0">
            <Tag :value="statusBadge.label" :severity="statusBadge.severity" />
            <p class="text-sm text-surface-500 dark:text-surface-400 truncate">
              {{ publicationEditStore.publication.category?.title }}
            </p>
            <span
              v-if="isDirty && !isAutoSaving"
              class="inline-flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400"
            >
              <span class="w-2 h-2 rounded-full bg-amber-500" />
              Non enregistré
            </span>
            <span
              v-if="isAutoSaving"
              class="inline-flex items-center gap-1.5 text-xs text-surface-500 dark:text-surface-400"
            >
              <i class="pi pi-spin pi-spinner text-xs" />
              Enregistrement…
            </span>
          </div>
        </div>

        <div class="flex items-center gap-2 self-end md:self-auto">
          <Button
            v-tooltip.bottom="focusMode ? 'Quitter le mode focus' : 'Mode focus'"
            :icon="focusMode ? 'pi pi-window-minimize' : 'pi pi-window-maximize'"
            severity="secondary"
            text
            size="small"
            @click="focusMode = !focusMode"
          />
          <Button
            label="Enregistrer"
            icon="pi pi-save"
            severity="secondary"
            size="small"
            :loading="publicationEditStore.isSaving"
            @click="handleSave"
          />
          <Button
            label="Soumettre"
            icon="pi pi-send"
            severity="success"
            size="small"
            :loading="publicationEditStore.isSubmitting"
            @click="handleSubmit"
          />
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4" :class="!focusMode && 'lg:grid-cols-[1fr_320px]'">
        <!-- Editor column -->
        <div class="flex flex-col gap-3 min-w-0">
          <input
            v-model="title"
            type="text"
            placeholder="Titre de la publication"
            class="w-full text-3xl md:text-4xl font-semibold bg-transparent border-0 focus:outline-none focus:ring-0 text-surface-900 dark:text-surface-0 placeholder:text-surface-300 dark:placeholder:text-surface-600 px-1"
            :disabled="publicationEditStore.isSaving || publicationEditStore.isSubmitting"
          />
          <p v-if="slugPreview" class="text-xs text-surface-500 dark:text-surface-400 px-1">
            URL : <span class="font-mono">/publications/{{ slugPreview }}</span>
          </p>

      <!-- Editor Container (same styling as publication display) -->
      <div class="content publication-container bg-surface-0 dark:bg-surface-800 rounded-md min-w-0">
        <!-- Toolbar (sticky inside container) -->
        <div class="sticky top-0 z-30 bg-surface-50 dark:bg-surface-700 border-b border-surface-200 dark:border-surface-600 rounded-t-md p-2">
        <div class="flex flex-wrap items-center gap-1">
          <!-- Text formatting -->
          <div class="flex items-center gap-1 pr-2 border-r border-surface-200 dark:border-surface-700">
            <Button
              v-tooltip.bottom="'Gras'"
              :severity="editor?.isActive('bold') ? 'primary' : 'secondary'"
              text
              size="small"
              class="font-bold"
              @click="editor?.chain().focus().toggleBold().run()"
            >
              B
            </Button>
            <Button
              v-tooltip.bottom="'Italique'"
              :severity="editor?.isActive('italic') ? 'primary' : 'secondary'"
              text
              size="small"
              class="italic"
              @click="editor?.chain().focus().toggleItalic().run()"
            >
              I
            </Button>
          </div>

          <!-- Headings -->
          <div class="flex items-center gap-1 px-2 border-r border-surface-200 dark:border-surface-700">
            <Button
              v-tooltip.bottom="'Paragraphe'"
              label="P"
              :severity="editor?.isActive('paragraph') ? 'primary' : 'secondary'"
              text
              size="small"
              @click="editor?.chain().focus().setParagraph().run()"
            />
            <Button
              v-tooltip.bottom="'Titre 2'"
              label="H2"
              :severity="editor?.isActive('heading', { level: 2 }) ? 'primary' : 'secondary'"
              text
              size="small"
              @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
            />
            <Button
              v-tooltip.bottom="'Titre 3'"
              label="H3"
              :severity="editor?.isActive('heading', { level: 3 }) ? 'primary' : 'secondary'"
              text
              size="small"
              @click="editor?.chain().focus().toggleHeading({ level: 3 }).run()"
            />
          </div>

          <!-- Alignment -->
          <div class="flex items-center gap-1 px-2 border-r border-surface-200 dark:border-surface-700">
            <Button
              v-tooltip.bottom="'Aligner à gauche'"
              icon="pi pi-align-left"
              :severity="editor?.isActive({ textAlign: 'left' }) ? 'primary' : 'secondary'"
              text
              size="small"
              @click="editor?.chain().focus().setTextAlign('left').run()"
            />
            <Button
              v-tooltip.bottom="'Centrer'"
              icon="pi pi-align-center"
              :severity="editor?.isActive({ textAlign: 'center' }) ? 'primary' : 'secondary'"
              text
              size="small"
              @click="editor?.chain().focus().setTextAlign('center').run()"
            />
            <Button
              v-tooltip.bottom="'Aligner à droite'"
              icon="pi pi-align-right"
              :severity="editor?.isActive({ textAlign: 'right' }) ? 'primary' : 'secondary'"
              text
              size="small"
              @click="editor?.chain().focus().setTextAlign('right').run()"
            />
          </div>

          <!-- Lists -->
          <div class="flex items-center gap-1 px-2 border-r border-surface-200 dark:border-surface-700">
            <Button
              v-tooltip.bottom="'Liste à puces'"
              icon="pi pi-list"
              :severity="editor?.isActive('bulletList') ? 'primary' : 'secondary'"
              text
              size="small"
              @click="editor?.chain().focus().toggleBulletList().run()"
            />
            <Button
              v-tooltip.bottom="'Liste numérotée'"
              icon="pi pi-list-check"
              :severity="editor?.isActive('orderedList') ? 'primary' : 'secondary'"
              text
              size="small"
              @click="editor?.chain().focus().toggleOrderedList().run()"
            />
          </div>

          <!-- Blocks -->
          <div class="flex items-center gap-1 px-2 border-r border-surface-200 dark:border-surface-700">
            <Button
              v-tooltip.bottom="'Citation'"
              icon="pi pi-comment"
              :severity="editor?.isActive('blockquote') ? 'primary' : 'secondary'"
              text
              size="small"
              @click="editor?.chain().focus().toggleBlockquote().run()"
            />
            <Button
              v-tooltip.bottom="'Ligne horizontale'"
              icon="pi pi-minus"
              severity="secondary"
              text
              size="small"
              @click="editor?.chain().focus().setHorizontalRule().run()"
            />
            <Button
              v-tooltip.bottom="'Insérer des colonnes'"
              icon="pi pi-th-large"
              :severity="editor?.isActive('columns') ? 'primary' : 'secondary'"
              text
              size="small"
              :disabled="editor?.isActive('columns')"
              @click="toggleColumnsPopover"
            />
            <Popover ref="columnsPopover">
              <div class="flex flex-col gap-1">
                <Button
                  label="2 colonnes"
                  icon="pi pi-th-large"
                  severity="secondary"
                  text
                  size="small"
                  @click="insertColumns(2)"
                />
                <Button
                  label="3 colonnes"
                  icon="pi pi-th-large"
                  severity="secondary"
                  text
                  size="small"
                  @click="insertColumns(3)"
                />
              </div>
            </Popover>
          </div>

          <!-- Media -->
          <div class="flex items-center gap-1 px-2 border-r border-surface-200 dark:border-surface-700">
            <Button
              v-tooltip.bottom="'Ajouter une image'"
              icon="pi pi-image"
              severity="secondary"
              text
              size="small"
              :loading="publicationEditStore.isUploading"
              @click="triggerImageUpload"
            />
            <input
              ref="imageInput"
              type="file"
              accept="image/*"
              class="hidden"
              @change="handleImageUpload"
            />
            <Button
              v-tooltip.bottom="'Ajouter une vidéo YouTube'"
              icon="pi pi-youtube"
              severity="secondary"
              text
              size="small"
              @click="showYoutubeDialog = true"
            />
          </div>

          <!-- Undo/Redo -->
          <div class="flex items-center gap-1 px-2 border-r border-surface-200 dark:border-surface-700">
            <Button
              v-tooltip.bottom="'Annuler'"
              icon="pi pi-undo"
              severity="secondary"
              text
              size="small"
              :disabled="!editor?.can().undo()"
              @click="editor?.chain().focus().undo().run()"
            />
            <Button
              v-tooltip.bottom="'Rétablir'"
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

        <!-- Content area with padding -->
        <div class="p-3">
          <!-- Error messages -->
          <Message v-if="publicationEditStore.errors.length > 0" severity="error" :closable="false" class="mb-4">
            <ul class="list-disc list-inside">
              <li v-for="(error, index) in publicationEditStore.errors" :key="index">{{ error }}</li>
            </ul>
          </Message>

          <!-- Editor -->
          <EditorContent :editor="editor" class="publication-editor" />

          <!-- Columns bubble menu (only visible when cursor is inside a columns block) -->
          <ColumnsBubbleMenu ref="columnsBubbleMenuRef" :editor="editor" />
        </div>
      </div>
        </div>

        <PublicationSettingsPanel
          v-if="!focusMode"
          v-model:short-description="shortDescription"
          v-model:category-id="categoryId"
          v-model:tags="tags"
          :word-count="wordCount"
          :reading-time="readingTime"
          :disabled="publicationEditStore.isSaving || publicationEditStore.isSubmitting"
        />
      </div>
    </div>

    <!-- YouTube Dialog -->
    <Dialog
      v-model:visible="showYoutubeDialog"
      modal
      header="Ajouter une vidéo YouTube"
      :style="{ width: '450px' }"
    >
      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2">
          <label for="youtubeUrl" class="font-medium text-surface-700 dark:text-surface-200">
            URL de la vidéo
          </label>
          <InputText
            id="youtubeUrl"
            v-model="youtubeUrl"
            placeholder="https://www.youtube.com/watch?v=..."
            class="w-full"
            @keyup.enter="insertYoutubeVideo"
          />
        </div>
        <small class="text-surface-500">Collez l'URL d'une vidéo YouTube</small>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" text @click="showYoutubeDialog = false" />
        <Button label="Ajouter" icon="pi pi-plus" :disabled="!isValidYoutubeUrl" @click="insertYoutubeVideo" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { BubbleMenuPlugin } from '@tiptap/extension-bubble-menu'
import DragHandle from '@tiptap/extension-drag-handle'
import Image from '@tiptap/extension-image'
import Placeholder from '@tiptap/extension-placeholder'
import TextAlign from '@tiptap/extension-text-align'
import Youtube from '@tiptap/extension-youtube'
import StarterKit from '@tiptap/starter-kit'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Popover from 'primevue/popover'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, onMounted, onUnmounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import ColumnsBubbleMenu from '../../../components/Editor/ColumnsBubbleMenu.vue'
import { Column, Columns } from '../../../components/Editor/extensions/Columns.js'
import PublicationSettingsPanel from '../../../components/Publication/PublicationSettingsPanel.vue'
import { usePublicationEditStore } from '../../../store/publication/publicationEdit.js'

useTitle('Édition - MusicAll')

const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const publicationEditStore = usePublicationEditStore()

const imageInput = ref(null)
const showYoutubeDialog = ref(false)
const youtubeUrl = ref('')
const columnsPopover = ref(null)
const columnsBubbleMenuRef = ref(null)
let columnsBubbleMenuRegistered = false

const title = ref('')
const shortDescription = ref('')
const categoryId = ref(null)
const tags = ref([])
const currentContent = ref('')
const lastSaved = ref(null)
const focusMode = ref(false)
const isAutoSaving = ref(false)
let autosaveTimer = null

const AUTOSAVE_INTERVAL_MS = 30_000

const STATUS_BADGES = {
  0: { label: 'Brouillon', severity: 'warn' },
  1: { label: 'Publié', severity: 'success' },
  2: { label: 'En validation', severity: 'info' }
}

const statusBadge = computed(() => {
  const id = publicationEditStore.publication?.status_id
  return (
    STATUS_BADGES[id] ?? {
      label: publicationEditStore.publication?.status_label ?? '—',
      severity: 'secondary'
    }
  )
})

const wordCount = computed(() => {
  const plain = (currentContent.value || '').replace(/<[^>]+>/g, ' ').trim()
  if (!plain) return 0
  return plain.split(/\s+/).filter((w) => w.length > 0).length
})

const readingTime = computed(() =>
  wordCount.value === 0 ? 0 : Math.max(1, Math.ceil(wordCount.value / 200))
)

const slugPreview = computed(() => {
  const fromTitle = slugify(title.value)
  return fromTitle || publicationEditStore.publication?.slug || ''
})

const isDirty = computed(() => {
  if (!lastSaved.value) return false
  return (
    title.value !== lastSaved.value.title ||
    shortDescription.value !== lastSaved.value.shortDescription ||
    categoryId.value !== lastSaved.value.categoryId ||
    JSON.stringify(tags.value) !== JSON.stringify(lastSaved.value.tags) ||
    currentContent.value !== lastSaved.value.content
  )
})

function slugify(s) {
  return (s || '')
    .toString()
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

function takeSnapshot() {
  lastSaved.value = {
    title: title.value,
    shortDescription: shortDescription.value,
    categoryId: categoryId.value,
    tags: [...tags.value],
    content: currentContent.value
  }
}

function syncSettingsFromStore() {
  const p = publicationEditStore.publication
  if (!p) return
  title.value = p.title || ''
  shortDescription.value = p.short_description || ''
  categoryId.value = p.category?.id ?? null
  tags.value = Array.isArray(p.tags) ? [...p.tags] : []
  currentContent.value = p.content || ''
  takeSnapshot()
}

function handleBeforeUnload(e) {
  if (isDirty.value) {
    e.preventDefault()
    e.returnValue = ''
    return ''
  }
}

async function silentAutoSave() {
  if (isAutoSaving.value || publicationEditStore.isSaving || publicationEditStore.isSubmitting)
    return
  isAutoSaving.value = true
  try {
    const success = await publicationEditStore.save({
      title: title.value.trim(),
      shortDescription: shortDescription.value.trim(),
      categoryId: categoryId.value,
      content: currentContent.value,
      tags: tags.value
    })
    if (success) takeSnapshot()
  } finally {
    isAutoSaving.value = false
  }
}

onBeforeRouteLeave(() => {
  if (isDirty.value) {
    return window.confirm('Vous avez des modifications non enregistrées. Quitter quand même ?')
  }
  return true
})

const isValidYoutubeUrl = computed(() => {
  const pattern = /^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be|music\.youtube\.com)\/.+/
  return pattern.test(youtubeUrl.value)
})

const editor = useEditor({
  extensions: [
    StarterKit.configure({
      heading: {
        levels: [2, 3]
      }
    }),
    TextAlign.configure({
      types: ['heading', 'paragraph']
    }),
    Image,
    Youtube.configure({
      controls: true,
      nocookie: true,
      modestBranding: true,
      allowFullscreen: true
    }),
    Placeholder.configure({
      placeholder: 'Commencez à écrire votre publication...'
    }),
    DragHandle.configure({
      render: () => {
        const el = document.createElement('div')
        el.classList.add('drag-handle')
        el.innerHTML = '⠿'
        return el
      }
    }),
    Columns,
    Column
  ],
  content: '',
  onUpdate: ({ editor }) => {
    const html = editor.getHTML()
    currentContent.value = html
    publicationEditStore.updateContent(html)
  }
})

onMounted(async () => {
  const id = route.params.id
  if (id) {
    await publicationEditStore.loadPublication(id)
    syncSettingsFromStore()
    if (publicationEditStore.publication?.content) {
      editor.value?.commands.setContent(publicationEditStore.publication.content)
    }
  }
  window.addEventListener('beforeunload', handleBeforeUnload)
  autosaveTimer = setInterval(() => {
    if (isDirty.value) silentAutoSave()
  }, AUTOSAVE_INTERVAL_MS)
})

onUnmounted(() => {
  publicationEditStore.clear()
})

onBeforeUnmount(() => {
  editor.value?.destroy()
  window.removeEventListener('beforeunload', handleBeforeUnload)
  if (autosaveTimer) {
    clearInterval(autosaveTimer)
    autosaveTimer = null
  }
})

watch(
  [editor, columnsBubbleMenuRef],
  ([currentEditor, menuComponent]) => {
    if (columnsBubbleMenuRegistered) return
    if (!currentEditor || !menuComponent?.rootEl) return

    currentEditor.registerPlugin(
      BubbleMenuPlugin({
        editor: currentEditor,
        element: menuComponent.rootEl,
        pluginKey: 'columnsBubbleMenu',
        shouldShow: ({ editor: e }) => e.isActive('columns'),
        options: { placement: 'top', offset: 8 }
      })
    )
    columnsBubbleMenuRegistered = true
  },
  { immediate: true }
)

watch(
  () => publicationEditStore.publication?.content,
  (newContent) => {
    if (newContent && editor.value && editor.value.getHTML() !== newContent) {
      editor.value.commands.setContent(newContent)
    }
  }
)

function triggerImageUpload() {
  imageInput.value?.click()
}

async function handleImageUpload(event) {
  const file = event.target.files?.[0]
  if (!file) return

  const uri = await publicationEditStore.uploadImage(file)
  if (uri) {
    editor.value?.chain().focus().setImage({ src: uri }).run()
    toast.add({
      severity: 'success',
      summary: 'Image ajoutée',
      life: 3000
    })
  } else {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: "Impossible d'ajouter l'image",
      life: 3000
    })
  }

  event.target.value = ''
}

function toggleColumnsPopover(event) {
  columnsPopover.value?.toggle(event)
}

function insertColumns(count) {
  editor.value?.chain().focus().insertColumns(count).run()
  columnsPopover.value?.hide()
}

function insertYoutubeVideo() {
  if (isValidYoutubeUrl.value) {
    editor.value?.chain().focus().setYoutubeVideo({ src: youtubeUrl.value }).run()
    showYoutubeDialog.value = false
    youtubeUrl.value = ''
  }
}

async function handleSave() {
  const success = await publicationEditStore.save({
    title: title.value.trim(),
    shortDescription: shortDescription.value.trim(),
    categoryId: categoryId.value,
    content: currentContent.value,
    tags: tags.value
  })

  if (success) {
    syncSettingsFromStore()
    toast.add({
      severity: 'success',
      summary: 'Enregistré',
      detail: 'Votre publication a été enregistrée',
      life: 3000
    })
  } else {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: "Impossible d'enregistrer la publication",
      life: 3000
    })
  }
}

function handleSubmit() {
  confirm.require({
    message:
      'Une fois soumise, vous ne pourrez plus modifier la publication. Voulez-vous continuer ?',
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Soumettre',
    acceptClass: 'p-button-success',
    accept: async () => {
      // Save first
      await handleSave()

      // Then submit
      const success = await publicationEditStore.submit()
      if (success) {
        toast.add({
          severity: 'success',
          summary: 'Publication soumise',
          detail: 'Votre publication a été soumise pour validation',
          life: 3000
        })
        setTimeout(() => {
          router.push({ name: 'app_user_publications' })
        }, 2000)
      } else {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Impossible de soumettre la publication',
          life: 5000
        })
      }
    }
  })
}
</script>

<style>
.publication-editor .tiptap {
  min-height: 400px;
  outline: none;
}

.publication-editor .tiptap p.is-editor-empty:first-child::before {
  content: attr(data-placeholder);
  float: left;
  color: #adb5bd;
  pointer-events: none;
  height: 0;
}

.publication-editor .tiptap ul,
.publication-editor .tiptap ol {
  padding-left: 1.5rem;
  margin-bottom: 0.75rem;
}

.publication-editor .tiptap blockquote {
  border-left: 4px solid #e5e7eb;
  padding-left: 1rem;
  margin-left: 0;
  margin-right: 0;
  font-style: italic;
  color: #6b7280;
}

.publication-editor .tiptap hr {
  border: none;
  border-top: 2px solid #e5e7eb;
  margin: 1.5rem 0;
}

/* Drag Handle */
.drag-handle {
  cursor: grab;
  padding: 0.25rem 0.35rem;
  border-radius: 0.25rem;
  background: var(--p-surface-100);
  border: 1px solid var(--p-surface-200);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--p-surface-500);
  font-size: 0.875rem;
}

.drag-handle:hover {
  background: var(--p-surface-200);
  color: var(--p-surface-700);
}

.drag-handle:active {
  cursor: grabbing;
}

:root.dark .drag-handle {
  background: var(--p-surface-700);
  border-color: var(--p-surface-600);
  color: var(--p-surface-400);
}

:root.dark .drag-handle:hover {
  background: var(--p-surface-600);
  color: var(--p-surface-200);
}
</style>
