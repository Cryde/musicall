<template>
  <div class="lyrics-editor flex flex-col gap-2">
    <div class="flex flex-wrap items-center gap-1" role="toolbar" aria-label="Mise en forme des paroles">
      <Button label="Accord" icon="pi pi-plus" size="small" severity="secondary" text @click="openChordInput()" />
      <span class="w-px h-5 bg-surface-300 dark:bg-surface-600 mx-1" aria-hidden="true" />
      <Button
        v-for="kind in QUICK_KINDS"
        :key="kind.value"
        :label="kind.label"
        size="small"
        severity="secondary"
        :text="!isSection(kind.value)"
        :aria-pressed="isSection(kind.value)"
        @click="toggleSection(kind.value)"
      />
      <Button
        :label="otherActiveKind?.label ?? 'Section'"
        icon="pi pi-chevron-down"
        icon-pos="right"
        size="small"
        severity="secondary"
        :text="!otherActiveKind"
        aria-haspopup="true"
        aria-controls="lyrics-section-menu"
        @click="sectionMenu?.toggle($event)"
      />
      <Menu id="lyrics-section-menu" ref="sectionMenu" :model="sectionMenuItems" :popup="true" />
      <Button
        label="Commentaire"
        icon="pi pi-comment"
        size="small"
        severity="secondary"
        :text="!editor?.isActive(NODE.comment)"
        :aria-pressed="editor?.isActive(NODE.comment) ?? false"
        @click="toggleComment"
      />
      <span class="ml-auto text-xs text-surface-600 dark:text-surface-300 hidden sm:inline">
        Sélectionnez des mots pour les attribuer · « [ » pour un accord
      </span>
    </div>

    <BubbleMenu
      v-if="editor"
      :editor="editor"
      :should-show="shouldShowBubble"
      :options="{ placement: 'bottom-start' }"
    >
      <div class="bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-lg shadow-lg p-1 text-sm">
        <div v-if="selectedChord" class="flex items-center gap-1">
          <Button label="Modifier l'accord" icon="pi pi-pencil" size="small" text @click="openChordInput(selectedChord)" />
          <Button label="Supprimer" icon="pi pi-trash" size="small" severity="danger" text @click="deleteSelection" />
        </div>
        <div v-else-if="!isPickingSingers" class="flex items-center gap-1">
          <Button label="Attribuer" icon="pi pi-microphone" size="small" text @click="startPickingSingers" />
        </div>
        <div v-else class="flex flex-col gap-0.5 min-w-52 p-1" role="group" aria-label="Qui chante ce passage">
          <label
            v-for="member in pickable"
            :key="member.id"
            class="flex items-center gap-2 px-2 py-1 rounded hover:bg-surface-100 dark:hover:bg-surface-800 cursor-pointer"
          >
            <Checkbox v-model="pickedSingers" :value="member.id" @change="onPick(member.id)" />
            <span>{{ member.name }}</span>
          </label>
          <div class="flex justify-between gap-2 border-t border-surface-200 dark:border-surface-700 pt-1 mt-1">
            <Button label="Retirer" size="small" severity="secondary" text @click="applySingers([])" />
            <Button label="Appliquer" size="small" :disabled="pickedSingers.length === 0" @click="applySingers(pickedSingers)" />
          </div>
        </div>
      </div>
    </BubbleMenu>

    <div
      v-if="chordInput"
      class="fixed z-[1200] bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-lg shadow-lg p-2 flex items-center gap-2"
      :style="{ left: `${chordInput.left}px`, top: `${chordInput.top}px` }"
    >
      <InputText
        ref="chordField"
        v-model="chordInput.name"
        size="small"
        class="w-28 font-bold"
        placeholder="ex. Am7"
        aria-label="Accord"
        @keydown.enter.prevent="confirmChord"
        @keydown.escape.prevent="closeChordInput"
      />
      <Button :label="chordInput.editing ? 'Modifier' : 'Insérer'" size="small" @click="confirmChord" />
      <Button icon="pi pi-times" size="small" severity="secondary" text aria-label="Annuler" @click="closeChordInput" />
    </div>

    <EditorContent :editor="editor" class="border border-surface-200 dark:border-surface-700 rounded-lg" />
  </div>
</template>

<script setup>
import { NodeSelection } from '@tiptap/pm/state'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { BubbleMenu } from '@tiptap/vue-3/menus'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import InputText from 'primevue/inputtext'
import Menu from 'primevue/menu'
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import {
  fromChordSite,
  parse,
  SECTION_KINDS,
  SINGER_ALL,
  serialize
} from '../../../../utils/chordpro.js'
import { fromDoc, NODE, SINGER_MARK, toDoc } from '../../../../utils/chordproTiptap.js'
import { lyricsExtensions } from './lyricsExtensions.js'
import './lyrics.css'

/**
 * The simple mode (#1055): the song laid out as it reads, chords over the words, passages tinted by
 * who sings them. The ChordPro text in v-model stays the one source of truth; this only converts.
 */
const props = defineProps({
  /** The members a passage can be given to: [{ id, name }]. « Tous » is added here. */
  members: { type: Array, default: () => [] },
  nameOf: { type: Function, required: true }
})

const source = defineModel({ type: String, default: '' })

const pickable = computed(() => [...props.members, { id: SINGER_ALL, name: 'Tous' }])

// What this editor last wrote to the model, so its own echo does not reset the document and the caret.
let lastEmitted = source.value

const editor = useEditor({
  content: toDoc(parse(source.value)),
  extensions: lyricsExtensions((id) => props.nameOf(id)),
  editorProps: {
    handlePaste: (_view, event) => handlePaste(event),
    handleKeyDown: (_view, event) => {
      // Not in a comment: it holds plain text only, and « [rires] » there is meant literally.
      if (
        event.key === '[' &&
        !event.ctrlKey &&
        !event.metaKey &&
        !editor.value.isActive(NODE.comment)
      ) {
        event.preventDefault()
        openChordInput()
        return true
      }
      return false
    }
  },
  onUpdate: ({ editor: current }) => {
    lastEmitted = serialize(fromDoc(current.getJSON()))
    source.value = lastEmitted
  }
})

watch(source, (value) => {
  if (value !== lastEmitted && editor.value) {
    lastEmitted = value
    editor.value.commands.setContent(toDoc(parse(value)), { emitUpdate: false })
  }
})

onBeforeUnmount(() => editor.value?.destroy())

// Pasted text is read as ChordPro, the chord-site layout converted first. A single plain line pastes as is.
function handlePaste(event) {
  const text = event.clipboardData?.getData('text/plain') ?? ''
  if (!/[\n[{<]/.test(text)) return false
  const blocks = toDoc(parse(fromChordSite(text.replace(/\n+$/, '')))).content
  editor.value.chain().focus().insertContent(blocks).run()
  return true
}

// --- Sections and comments ---------------------------------------------------------------------

// The two parts every song has get a button; the rest sit in a menu so the toolbar fits a phone.
const QUICK_KINDS = SECTION_KINDS.filter((kind) => ['verse', 'chorus'].includes(kind.value))
const OTHER_KINDS = SECTION_KINDS.filter((kind) => !QUICK_KINDS.includes(kind))
const sectionMenu = ref(null)
const otherActiveKind = computed(() => OTHER_KINDS.find((kind) => isSection(kind.value)) ?? null)
const sectionMenuItems = computed(() =>
  OTHER_KINDS.map((kind) => ({
    label: kind.label,
    icon: isSection(kind.value) ? 'pi pi-check' : undefined,
    command: () => toggleSection(kind.value)
  }))
)

function isSection(kind) {
  return editor.value?.isActive(NODE.section, { kind }) ?? false
}

function toggleSection(kind) {
  const chain = editor.value.chain().focus()
  if (isSection(kind)) {
    chain.lift(NODE.section).run()
  } else if (editor.value.isActive(NODE.section)) {
    chain.updateAttributes(NODE.section, { kind }).run()
  } else {
    chain.wrapIn(NODE.section, { kind, label: '' }).run()
  }
}

function toggleComment() {
  const next = editor.value.isActive(NODE.comment) ? NODE.line : NODE.comment
  editor.value.chain().focus().setNode(next).run()
}

// --- Singers -----------------------------------------------------------------------------------

const isPickingSingers = ref(false)
const pickedSingers = ref([])

const selectedChord = computed(() => {
  const selection = editor.value?.state.selection
  return selection instanceof NodeSelection && selection.node.type.name === NODE.chord
    ? selection.node.attrs.name
    : null
})

function shouldShowBubble({ state }) {
  const { selection } = state
  if (selection instanceof NodeSelection) return selection.node.type.name === NODE.chord
  return !selection.empty && !editor.value.isActive(NODE.comment)
}

watch(
  () => editor.value?.state.selection,
  () => {
    isPickingSingers.value = false
  }
)

function startPickingSingers() {
  pickedSingers.value = [...(editor.value.getAttributes(SINGER_MARK).singers ?? [])]
  isPickingSingers.value = true
}

// « Tous » and named members exclude each other: a passage is either everyone's or someone's.
function onPick(id) {
  if (!pickedSingers.value.includes(id)) return
  pickedSingers.value =
    id === SINGER_ALL ? [SINGER_ALL] : pickedSingers.value.filter((picked) => picked !== SINGER_ALL)
}

function applySingers(singers) {
  const chain = editor.value.chain().focus()
  if (singers.length === 0) {
    chain.unsetMark(SINGER_MARK).run()
  } else {
    chain.setMark(SINGER_MARK, { singers: [...singers] }).run()
  }
  isPickingSingers.value = false
}

function deleteSelection() {
  editor.value.chain().focus().deleteSelection().run()
}

// --- Chords ------------------------------------------------------------------------------------

const chordInput = ref(null)
const chordField = ref(null)

async function openChordInput(existing = null) {
  const { view, state } = editor.value
  const coords = view.coordsAtPos(state.selection.from)
  chordInput.value = {
    name: existing ?? '',
    editing: existing !== null,
    left: coords.left,
    top: coords.bottom + 6
  }
  await nextTick()
  chordField.value?.$el?.focus()
}

function closeChordInput() {
  chordInput.value = null
  editor.value.commands.focus()
}

function confirmChord() {
  const name = chordInput.value?.name.replace(/[[\]]/g, '').trim()
  if (!name) {
    closeChordInput()
    return
  }
  const chain = editor.value.chain().focus()
  if (chordInput.value.editing) {
    chain.updateAttributes(NODE.chord, { name }).run()
  } else {
    chain.insertContent({ type: NODE.chord, attrs: { name } }).run()
  }
  chordInput.value = null
}
</script>
