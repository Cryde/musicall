<template>
  <div>
    <div v-if="editor" class="border border-surface-200 dark:border-surface-700 rounded-lg overflow-hidden">
      <div class="flex flex-wrap gap-1 p-2 bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
        <Button
          v-tooltip.bottom="'Gras'"
          :severity="editor.isActive('bold') ? 'info' : 'secondary'"
          text
          size="small"
          @click="editor.chain().focus().toggleBold().run()"
        >
          <span class="font-bold text-base">B</span>
        </Button>
        <Button
          v-tooltip.bottom="'Italique'"
          :severity="editor.isActive('italic') ? 'info' : 'secondary'"
          text
          size="small"
          @click="editor.chain().focus().toggleItalic().run()"
        >
          <span class="italic text-base">I</span>
        </Button>
        <Divider layout="vertical" class="mx-1" />
        <Button
          v-tooltip.bottom="'Liste à puces'"
          icon="pi pi-list"
          :severity="editor.isActive('bulletList') ? 'info' : 'secondary'"
          text
          size="small"
          @click="editor.chain().focus().toggleBulletList().run()"
        />
        <Button
          v-tooltip.bottom="'Liste numérotée'"
          :severity="editor.isActive('orderedList') ? 'info' : 'secondary'"
          text
          size="small"
          @click="editor.chain().focus().toggleOrderedList().run()"
        >
          <i class="pi pi-list" />
        </Button>
        <Divider layout="vertical" class="mx-1" />
        <Button
          v-tooltip.bottom="'Citation'"
          icon="pi pi-comment"
          :severity="editor.isActive('blockquote') ? 'info' : 'secondary'"
          text
          size="small"
          @click="editor.chain().focus().toggleBlockquote().run()"
        />
        <Divider layout="vertical" class="mx-1" />
        <Button
          v-tooltip.bottom="'Ajouter une image'"
          icon="pi pi-image"
          severity="secondary"
          text
          size="small"
          :loading="isUploadingImage"
          @click="triggerImageUpload"
        />
        <input
          ref="imageInputRef"
          type="file"
          accept="image/*"
          class="hidden"
          @change="handleImageUpload"
        >
        <Button
          v-tooltip.bottom="'Ajouter une vidéo YouTube'"
          icon="pi pi-youtube"
          severity="secondary"
          text
          size="small"
          @click="showYoutubeDialog = true"
        />
        <Divider layout="vertical" class="mx-1" />
        <Button
          v-tooltip.bottom="'Annuler'"
          icon="pi pi-undo"
          severity="secondary"
          text
          size="small"
          :disabled="!editor.can().undo()"
          @click="editor.chain().focus().undo().run()"
        />
        <Button
          v-tooltip.bottom="'Rétablir'"
          icon="pi pi-refresh"
          severity="secondary"
          text
          size="small"
          :disabled="!editor.can().redo()"
          @click="editor.chain().focus().redo().run()"
        />
      </div>

      <EditorContent :editor="editor" class="p-3 min-h-[150px] prose dark:prose-invert max-w-none" />
    </div>

    <Dialog
      v-model:visible="showYoutubeDialog"
      modal
      header="Ajouter une vidéo YouTube"
      :style="{ width: '32rem' }"
    >
      <div class="flex flex-col gap-3">
        <InputText
          v-model="youtubeUrl"
          placeholder="https://www.youtube.com/watch?v=…"
          autofocus
          @keyup.enter="insertYoutubeVideo"
        />
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
import Image from '@tiptap/extension-image'
import Placeholder from '@tiptap/extension-placeholder'
import Youtube from '@tiptap/extension-youtube'
import StarterKit from '@tiptap/starter-kit'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Divider from 'primevue/divider'
import InputText from 'primevue/inputtext'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import forumApi from '../../api/forum/forum.js'

const props = defineProps({
  previousContent: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: 'Écrivez votre message…'
  }
})

const emit = defineEmits(['content-update'])
const toast = useToast()

const imageInputRef = ref(null)
const isUploadingImage = ref(false)
const showYoutubeDialog = ref(false)
const youtubeUrl = ref('')

const isValidYoutubeUrl = computed(() => {
  if (!youtubeUrl.value) return false
  return /^https?:\/\/(www\.)?(youtube\.com|youtu\.be)\//.test(youtubeUrl.value)
})

const editor = useEditor({
  extensions: [
    StarterKit.configure({
      heading: false
    }),
    Placeholder.configure({
      placeholder: props.placeholder
    }),
    Image,
    Youtube.configure({
      controls: true,
      nocookie: true,
      width: 640,
      height: 360
    })
  ],
  content: props.previousContent,
  onUpdate: ({ editor }) => {
    emit('content-update', {
      html: editor.getHTML(),
      text: editor.getText()
    })
  }
})

onBeforeUnmount(() => {
  editor.value?.destroy()
})

watch(
  () => props.previousContent,
  (newContent) => {
    if (editor.value && newContent !== editor.value.getHTML()) {
      editor.value.commands.setContent(newContent)
    }
  }
)

function triggerImageUpload() {
  imageInputRef.value?.click()
}

async function handleImageUpload(event) {
  const file = event.target.files?.[0]
  if (!file) return

  isUploadingImage.value = true
  try {
    const { uri } = await forumApi.uploadImage(file)
    if (uri) {
      editor.value?.chain().focus().setImage({ src: uri }).run()
      toast.add({ severity: 'success', summary: 'Image ajoutée', life: 3000 })
    }
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Échec du téléchargement',
      detail: e?.response?.data?.detail || 'Une erreur est survenue.',
      life: 4000
    })
  } finally {
    isUploadingImage.value = false
    if (imageInputRef.value) imageInputRef.value.value = ''
  }
}

function insertYoutubeVideo() {
  if (!isValidYoutubeUrl.value) return
  editor.value?.chain().focus().setYoutubeVideo({ src: youtubeUrl.value }).run()
  showYoutubeDialog.value = false
  youtubeUrl.value = ''
}

function reset() {
  editor.value?.commands.clearContent(true)
}

function htmlToPlainText(html) {
  // Render HTML through textContent so embedded tags are stripped without
  // executing scripts; setting innerHTML on a detached element is safe.
  const container = document.createElement('div')
  container.innerHTML = html ?? ''
  return (container.textContent || '').replace(/\s+/g, ' ').trim()
}

function insertQuote({ author, html }) {
  if (!editor.value) return
  const text = htmlToPlainText(html)
  editor.value
    .chain()
    .focus('end')
    .insertContent([
      {
        type: 'blockquote',
        content: [
          { type: 'paragraph', content: [{ type: 'text', text: `${author} a écrit :` }] },
          ...(text
            ? [{ type: 'paragraph', content: [{ type: 'text', text }] }]
            : [{ type: 'paragraph' }])
        ]
      },
      { type: 'paragraph' }
    ])
    .focus('end')
    .run()
}

defineExpose({ reset, insertQuote })
</script>

<style>
.ProseMirror {
  outline: none;
  min-height: 100px;
}

.ProseMirror p.is-editor-empty:first-child::before {
  content: attr(data-placeholder);
  float: left;
  color: #adb5bd;
  pointer-events: none;
  height: 0;
}

.ProseMirror img {
  max-width: 100%;
  height: auto;
}

.ProseMirror [data-youtube-video] iframe {
  aspect-ratio: 16 / 9;
  width: 100%;
  max-width: 640px;
  height: auto;
}
</style>
