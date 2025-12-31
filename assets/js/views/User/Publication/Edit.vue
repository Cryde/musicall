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
        <div class="flex items-center gap-4">
          <Button
            icon="pi pi-arrow-left"
            severity="secondary"
            text
            rounded
            @click="router.push({ name: 'app_user_publications' })"
          />
          <div>
            <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
              {{ publicationEditStore.publication.title }}
            </h1>
            <p class="text-sm text-surface-500 dark:text-surface-400">
              {{ publicationEditStore.publication.category?.title }}
            </p>
          </div>
        </div>
      </div>

      <!-- Editor Container (same styling as publication display) -->
      <div class="content publication-container bg-surface-0 dark:bg-surface-800 rounded-md">
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

          <!-- Spacer -->
          <div class="flex-1" />

          <!-- Actions -->
          <div class="flex items-center gap-2">
            <Button
              v-tooltip.bottom="'Paramètres'"
              icon="pi pi-cog"
              severity="secondary"
              text
              size="small"
              @click="showSettingsModal = true"
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
        </div>
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

    <!-- Settings Modal -->
    <PublicationSettingsModal
      v-model="showSettingsModal"
      @saved="handleSettingsSaved"
    />

    <!-- Submit Confirmation -->
    <ConfirmDialog />
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import DragHandle from '@tiptap/extension-drag-handle'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import TextAlign from '@tiptap/extension-text-align'
import Image from '@tiptap/extension-image'
import Placeholder from '@tiptap/extension-placeholder'
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Youtube from '@tiptap/extension-youtube'
import { usePublicationEditStore } from '../../../store/publication/publicationEdit.js'
import PublicationSettingsModal from '../../../components/publication/PublicationSettingsModal.vue'

useTitle('Édition - MusicAll')

const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const publicationEditStore = usePublicationEditStore()

const imageInput = ref(null)
const showYoutubeDialog = ref(false)
const youtubeUrl = ref('')
const showSettingsModal = ref(false)

const isValidYoutubeUrl = computed(() => {
  const pattern = /^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be|music\.youtube\.com)\/.+/
  return pattern.test(youtubeUrl.value)
})

const editor = useEditor({
  extensions: [
    StarterKit.configure({
      heading: {
        levels: [2, 3],
      },
    }),
    TextAlign.configure({
      types: ['heading', 'paragraph'],
    }),
    Image,
    Youtube.configure({
      controls: true,
      nocookie: true,
      modestBranding: true,
      allowFullscreen: true,
    }),
    Placeholder.configure({
      placeholder: 'Commencez à écrire votre publication...',
    }),
    DragHandle.configure({
      render: () => {
        const el = document.createElement('div')
        el.classList.add('drag-handle')
        el.innerHTML = '⠿'
        return el
      },
    }),
  ],
  content: '',
  onUpdate: ({ editor }) => {
    publicationEditStore.updateContent(editor.getHTML())
  },
})

onMounted(async () => {
  const id = route.params.id
  if (id) {
    await publicationEditStore.loadPublication(id)
    if (publicationEditStore.publication?.content) {
      editor.value?.commands.setContent(publicationEditStore.publication.content)
    }
  }
})

onUnmounted(() => {
  publicationEditStore.clear()
})

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
      life: 3000,
    })
  } else {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: "Impossible d'ajouter l'image",
      life: 3000,
    })
  }

  event.target.value = ''
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
    title: publicationEditStore.publication.title,
    shortDescription: publicationEditStore.publication.short_description,
    categoryId: publicationEditStore.publication.category?.id,
    content: editor.value?.getHTML() || '',
  })

  if (success) {
    toast.add({
      severity: 'success',
      summary: 'Enregistré',
      detail: 'Votre publication a été enregistrée',
      life: 3000,
    })
  } else {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: "Impossible d'enregistrer la publication",
      life: 3000,
    })
  }
}

function handleSubmit() {
  confirm.require({
    message: 'Une fois soumise, vous ne pourrez plus modifier la publication. Voulez-vous continuer ?',
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
          life: 3000,
        })
        setTimeout(() => {
          router.push({ name: 'app_user_publications' })
        }, 2000)
      } else {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Impossible de soumettre la publication',
          life: 5000,
        })
      }
    },
  })
}

function handleSettingsSaved() {
  toast.add({
    severity: 'success',
    summary: 'Paramètres enregistrés',
    life: 3000,
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
