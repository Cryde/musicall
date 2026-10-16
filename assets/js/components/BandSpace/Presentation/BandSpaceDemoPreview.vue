<template>
  <section
    id="band-space-demo"
    :aria-label="t('home.band_space_presentation.preview.aria_label')"
    class="flex flex-col overflow-hidden rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 shadow-2xl"
  >
    <div class="flex items-center justify-between gap-4 h-14 px-4 lg:px-5 border-b border-surface-200 dark:border-surface-700">
      <div class="flex items-center gap-4 min-w-0">
        <span class="hidden sm:inline text-sm tracking-wide text-surface-900 dark:text-surface-0" aria-hidden="true">
          <span class="font-extrabold">MUSIC</span>ALL
        </span>
        <span class="truncate h-8 inline-flex items-center px-3 rounded-lg border border-surface-300 dark:border-surface-600 text-sm font-semibold text-surface-900 dark:text-surface-0">
          {{ shownName }}
        </span>
      </div>
      <div class="flex shrink-0 pl-2" :aria-label="t('home.band_space_presentation.preview.members', { count: size })" role="img">
        <span
          v-for="(member, index) in roster"
          :key="member"
          :class="['-ml-2 size-8 rounded-full inline-flex items-center justify-center text-xs font-bold text-white border-2 border-surface-0 dark:border-surface-900', AVATAR_COLORS[index]]"
          aria-hidden="true"
        >
          {{ member[0] }}
        </span>
      </div>
    </div>

    <div class="grid lg:grid-cols-[12rem_minmax(0,1fr)] grow lg:min-h-[36rem]">
      <div
        class="flex lg:flex-col gap-1 p-3 overflow-x-auto border-b lg:border-b-0 lg:border-r border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-950"
        role="tablist"
        :aria-label="t('home.band_space_presentation.preview.tabs_aria_label')"
      >
        <button
          v-for="tab in TABS"
          :id="`demo-tab-${tab.key}`"
          :key="tab.key"
          type="button"
          role="tab"
          :aria-selected="tab.key === activeTab"
          :aria-controls="`demo-panel-${tab.key}`"
          :tabindex="tab.key === activeTab ? 0 : -1"
          :class="[
            'shrink-0 flex items-center justify-between gap-3 h-10 px-3 rounded-lg text-sm text-left cursor-pointer transition-colors',
            tab.key === activeTab
              ? 'bg-primary-100 text-primary-800 font-semibold dark:bg-surface-800 dark:text-surface-0'
              : 'text-surface-700 hover:bg-surface-200 dark:text-surface-300 dark:hover:bg-surface-800'
          ]"
          @click="activeTab = tab.key"
          @keydown.up.prevent="moveTab(-1)"
          @keydown.left.prevent="moveTab(-1)"
          @keydown.down.prevent="moveTab(1)"
          @keydown.right.prevent="moveTab(1)"
          @keydown.home.prevent="selectTab(TABS[0].key)"
          @keydown.end.prevent="selectTab(TABS.at(-1).key)"
        >
          <span class="flex items-center gap-2.5">
            <i :class="tab.icon" aria-hidden="true" />
            {{ tab.label }}
          </span>
          <span
            v-if="pickedPains.includes(tab.key)"
            class="text-[10px] font-bold tracking-wide text-teal-700 dark:text-teal-300"
          >
            {{ t('home.band_space_presentation.preview.priority') }}
          </span>
        </button>
      </div>

      <div class="flex flex-col gap-5 p-5 lg:p-7 min-w-0">
        <!-- Every panel stays mounted so each tab's aria-controls points at a real element. -->
        <div
          v-for="tab in TABS"
          v-show="tab.key === activeTab"
          :id="`demo-panel-${tab.key}`"
          :key="tab.key"
          role="tabpanel"
          :aria-labelledby="`demo-tab-${tab.key}`"
          class="flex flex-col gap-5"
        >
          <template v-if="tab.key === 'dashboard'">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
              <h3 class="m-0 text-xl font-bold text-surface-900 dark:text-surface-0">{{ t('home.band_space_presentation.preview.welcome', { name: shownName }) }}</h3>
              <span class="text-sm text-surface-600 dark:text-surface-400">{{ t('home.band_space_presentation.preview.members_invited_by_mail', { members: membersLabel }) }}</span>
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
              <button
                v-for="widget in widgets"
                :key="widget.key"
                type="button"
                :class="[
                  'flex flex-col gap-1.5 p-4 rounded-xl border text-left cursor-pointer transition-colors hover:border-primary',
                  widget.isPicked
                    ? 'border-primary-300 bg-primary-50 dark:border-primary-700 dark:bg-primary-950/40'
                    : 'border-surface-200 bg-surface-50 dark:border-surface-700 dark:bg-surface-950'
                ]"
                @click="activeTab = widget.key"
              >
                <span :class="['text-[11px] font-bold tracking-wider uppercase', MODULE_ACCENTS[widget.key].text]">{{ widget.tag }}</span>
                <span class="font-bold text-surface-900 dark:text-surface-0">{{ widget.title }}</span>
                <span class="text-sm text-surface-600 dark:text-surface-400">{{ widget.meta }}</span>
              </button>
            </div>
          </template>

          <template v-else>
            <div class="flex flex-wrap items-end justify-between gap-3">
              <div class="flex flex-col gap-0.5">
                <h3 class="m-0 text-xl font-bold text-surface-900 dark:text-surface-0">{{ panels[tab.key].title }}</h3>
                <span class="text-sm text-surface-600 dark:text-surface-400">{{ panels[tab.key].subtitle }}</span>
              </div>
              <span :class="['text-sm font-bold', MODULE_ACCENTS[tab.key].text]">{{ panels[tab.key].summary }}</span>
            </div>

            <ul v-if="tab.key === 'agenda'" class="m-0 p-0 list-none flex flex-col gap-2">
              <li v-for="event in agenda" :key="event.id" :class="[ROW, 'gap-4']">
                <span class="flex flex-col items-center w-10 shrink-0">
                  <span class="text-[10px] font-bold text-surface-600 dark:text-surface-400">{{ event.day }}</span>
                  <span :class="['text-lg font-extrabold leading-none', event.isGig ? MODULE_ACCENTS.setlists.text : MODULE_ACCENTS.agenda.text]">{{ event.date }}</span>
                </span>
                <span class="flex flex-col grow min-w-0">
                  <span class="font-semibold text-surface-900 dark:text-surface-0">{{ event.name }}</span>
                  <span class="text-sm text-surface-600 dark:text-surface-400">{{ event.detail }}</span>
                </span>
                <span class="text-sm font-bold text-surface-700 dark:text-surface-300" :aria-label="t('home.band_space_presentation.preview.available', { available: event.available, size })">
                  {{ event.available }}/{{ size }}
                </span>
              </li>
            </ul>

            <ul v-else-if="tab.key === 'taches'" class="m-0 p-0 list-none flex flex-col gap-2">
              <li v-for="task in tasks" :key="task.id">
                <label :class="[ROW, 'gap-3 cursor-pointer']">
                  <input
                    type="checkbox"
                    :checked="task.done"
                    class="size-5 shrink-0 accent-teal-600 cursor-pointer"
                    @change="toggleTask(task.id)"
                  />
                  <span class="flex flex-col grow min-w-0">
                    <span :class="['font-semibold', task.done ? 'line-through text-surface-600 dark:text-surface-400' : 'text-surface-900 dark:text-surface-0']">{{ task.label }}</span>
                    <span class="text-sm text-surface-600 dark:text-surface-400">{{ task.detail }}</span>
                  </span>
                  <span class="text-sm font-bold text-surface-700 dark:text-surface-300">{{ task.who }}</span>
                </label>
              </li>
            </ul>

            <template v-else-if="tab.key === 'notes'">
              <div v-if="openNote" class="flex flex-col gap-3">
                <button type="button" :class="[ADD_BUTTON, 'self-start']" @click="openNoteId = null">
                  <i class="pi pi-arrow-left" aria-hidden="true" />
                  {{ t('home.band_space_presentation.preview.back_to_notes') }}
                </button>
                <div class="flex flex-col rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
                  <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-950">
                    <span class="font-semibold text-surface-900 dark:text-surface-0">{{ openNote.title }}</span>
                    <!-- Decorative: a real formatting toolbar is the product, not the demo. -->
                    <span class="flex gap-3 text-surface-500 dark:text-surface-400" aria-hidden="true">
                      <span class="font-bold">{{ t('home.band_space_presentation.preview.toolbar_bold') }}</span>
                      <span class="italic">{{ t('home.band_space_presentation.preview.toolbar_italic') }}</span>
                      <i class="pi pi-list" />
                    </span>
                  </div>
                  <label :for="`demo-note-${openNote.id}`" class="sr-only">{{ t('home.band_space_presentation.preview.note_content', { title: openNote.title }) }}</label>
                  <textarea
                    :id="`demo-note-${openNote.id}`"
                    v-model="openNoteBody"
                    rows="7"
                    class="w-full p-4 bg-surface-0 dark:bg-surface-900 text-surface-900 dark:text-surface-0 leading-relaxed resize-y outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"
                  />
                </div>
                <span class="text-sm text-surface-600 dark:text-surface-400">
                  {{ openNote.isEdited ? t('home.band_space_presentation.preview.note_edited_by_you') : t('home.band_space_presentation.preview.note_written_by', { name: openNote.who }) }}
                </span>
              </div>
              <ul v-else class="m-0 p-0 list-none flex flex-col gap-2">
                <li v-for="note in notes" :key="note.id">
                  <button type="button" :class="[ROW, 'w-full gap-3 text-left cursor-pointer hover:border-primary']" @click="openNoteId = note.id">
                    <i :class="['pi pi-file-edit', MODULE_ACCENTS.notes.text]" aria-hidden="true" />
                    <span class="flex flex-col grow min-w-0">
                      <span class="font-semibold text-surface-900 dark:text-surface-0">{{ note.title }}</span>
                      <span class="truncate text-sm text-surface-600 dark:text-surface-400">{{ note.body || t('home.band_space_presentation.preview.note_empty') }}</span>
                    </span>
                    <span class="text-sm font-bold text-surface-700 dark:text-surface-300">
                      {{ note.isEdited ? t('home.band_space_presentation.preview.just_now') : note.who }}
                    </span>
                  </button>
                </li>
              </ul>
            </template>

            <ol v-else-if="tab.key === 'setlists'" class="m-0 p-0 list-none flex flex-col gap-2">
              <li v-for="(song, index) in setlist" :key="song.id" :class="[ROW, 'gap-3']">
                <span class="w-5 text-sm font-bold text-surface-600 dark:text-surface-400">{{ index + 1 }}</span>
                <span class="flex flex-wrap items-center gap-x-2 grow min-w-0 font-semibold text-surface-900 dark:text-surface-0">
                  <span>{{ song.title }}</span>
                  <span v-if="song.isNew" :class="['text-[10px] font-bold uppercase', MODULE_ACCENTS.setlists.text]">{{ t('home.band_space_presentation.preview.new_song') }}</span>
                </span>
                <span class="text-sm font-bold text-surface-700 dark:text-surface-300">{{ formatSongDuration(song.seconds) }}</span>
                <span class="flex">
                  <button
                    type="button"
                    :class="ICON_BUTTON"
                    :disabled="index === 0"
                    :aria-label="t('home.band_space_presentation.preview.move_up', { title: song.title })"
                    @click="moveSongAt(index, -1)"
                  >
                    <i class="pi pi-arrow-up" aria-hidden="true" />
                  </button>
                  <button
                    type="button"
                    :class="ICON_BUTTON"
                    :disabled="index === setlist.length - 1"
                    :aria-label="t('home.band_space_presentation.preview.move_down', { title: song.title })"
                    @click="moveSongAt(index, 1)"
                  >
                    <i class="pi pi-arrow-down" aria-hidden="true" />
                  </button>
                </span>
              </li>
            </ol>

            <template v-else-if="tab.key === 'files'">
              <ul v-if="files.length" class="m-0 p-0 list-none flex flex-col gap-2">
                <li v-for="file in files" :key="file.id" :class="[ROW, 'gap-3']">
                  <span :class="['w-10 text-xs font-bold', MODULE_ACCENTS.files.text]">{{ file.type }}</span>
                  <span class="flex flex-col grow min-w-0">
                    <span class="truncate font-semibold text-surface-900 dark:text-surface-0">{{ file.name }}</span>
                    <span class="text-sm text-surface-600 dark:text-surface-400">{{ t('home.band_space_presentation.preview.file_added_by', { folder: file.folder, name: file.who }) }}</span>
                  </span>
                  <button
                    type="button"
                    :class="[ICON_BUTTON, 'hover:text-red-600 dark:hover:text-red-400']"
                    :aria-label="t('home.band_space_presentation.preview.delete_file', { name: file.name })"
                    data-file-delete
                    @click="deleteFile(file.id)"
                  >
                    <i class="pi pi-trash" aria-hidden="true" />
                  </button>
                </li>
              </ul>
              <div v-else class="flex flex-col items-center gap-3 py-8 text-center">
                <p class="m-0 text-surface-600 dark:text-surface-400">{{ t('home.band_space_presentation.preview.files_empty') }}</p>
                <button type="button" data-files-restore :class="ADD_BUTTON" @click="deletedFileIds = []">
                  <i class="pi pi-replay" aria-hidden="true" />
                  {{ t('home.band_space_presentation.preview.restore_files') }}
                </button>
              </div>
            </template>

            <template v-else-if="tab.key === 'finances'">
              <ul class="m-0 p-0 list-none flex flex-col gap-2">
                <li v-for="expense in expenses" :key="expense.id" :class="[ROW, 'gap-3']">
                  <span :class="['size-2 rounded-full shrink-0', MODULE_ACCENTS.finances.dot]" aria-hidden="true" />
                  <span class="flex flex-col grow min-w-0">
                    <span class="font-semibold text-surface-900 dark:text-surface-0">{{ expense.label }}</span>
                    <span class="text-sm text-surface-600 dark:text-surface-400">{{ t('home.band_space_presentation.preview.expense_split', { payer: expense.payer, size }) }}</span>
                  </span>
                  <span class="text-sm font-bold text-surface-900 dark:text-surface-0">{{ formatEuros(expense.amount) }}</span>
                </li>
              </ul>
              <h4 class="m-0 text-sm font-bold text-surface-900 dark:text-surface-0">{{ t('home.band_space_presentation.preview.who_owes_what') }}</h4>
              <ul class="m-0 p-0 list-none grid sm:grid-cols-2 gap-2">
                <li
                  v-for="balance in balances"
                  :key="balance.name"
                  class="flex items-center justify-between gap-3 px-4 py-2.5 rounded-xl bg-surface-50 dark:bg-surface-950 text-sm"
                >
                  <span class="font-semibold text-surface-900 dark:text-surface-0">{{ balance.name }}</span>
                  <span
                    :class="[
                      'font-bold',
                      balance.cents > 0 ? 'text-teal-700 dark:text-teal-300' : 'text-surface-700 dark:text-surface-300'
                    ]"
                  >
                    {{ describeBalance(balance.cents) }}
                  </span>
                </li>
              </ul>
            </template>

            <div v-if="panels[tab.key].addLabel" class="flex flex-wrap items-center gap-3">
              <button
                type="button"
                :class="ADD_BUTTON"
                :aria-disabled="!canAddMore(panels[tab.key].addedCount)"
                @click="canAddMore(panels[tab.key].addedCount) && panels[tab.key].add()"
              >
                <i class="pi pi-plus" aria-hidden="true" />
                {{ panels[tab.key].addLabel }}
              </button>
              <span v-if="!canAddMore(panels[tab.key].addedCount)" class="text-sm text-surface-600 dark:text-surface-400">
                {{ t('home.band_space_presentation.preview.demo_limit') }}
              </span>
            </div>
          </template>
        </div>

        <p class="m-0 mt-auto text-sm text-surface-600 dark:text-surface-400">
          {{ t('home.band_space_presentation.preview.footer') }}
        </p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { BAND_SPACE_MODULES } from '../../../constants/bandSpace.js'
import {
  canAddMore,
  dashboardModules,
  demoAgenda,
  demoBalances,
  demoExpenses,
  demoFiles,
  demoNotes,
  demoRoster,
  demoSetlist,
  demoTasks,
  describeBalance,
  formatEuros,
  formatSongDuration,
  INITIAL_DONE_TASK_IDS,
  INITIAL_SETLIST_ORDER,
  MODULE_ACCENTS,
  moveSong,
  requestedDemoTab,
  setlistMinutes
} from '../../../utils/bandSpaceDemo.js'
import { adjacentTabKey } from '../../../utils/tabNavigation.js'

const props = defineProps({
  name: { type: String, default: '' },
  size: { type: Number, required: true },
  pickedPains: { type: Array, default: () => [] },
  // A footer link can land on one module, e.g. /band-space?module=finances.
  requestedTab: { type: [String, Array], default: null }
})

const { t } = useI18n()

const TABS = [
  {
    key: 'dashboard',
    get label() {
      return t('home.band_space_presentation.preview.dashboard_tab')
    },
    icon: 'pi pi-th-large'
  },
  ...BAND_SPACE_MODULES
]

// White initials on each of these clear 4.5:1.
const AVATAR_COLORS = [
  'bg-pink-700',
  'bg-amber-800',
  'bg-teal-700',
  'bg-indigo-600',
  'bg-yellow-800',
  'bg-slate-600'
]

const ROW =
  'flex items-center px-4 py-3 rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-950'
const ICON_BUTTON =
  'size-9 inline-flex items-center justify-center rounded-lg text-surface-600 dark:text-surface-300 hover:bg-surface-200 dark:hover:bg-surface-800 cursor-pointer disabled:opacity-30 disabled:cursor-default disabled:hover:bg-transparent'
const ADD_BUTTON =
  'inline-flex items-center gap-2 h-10 px-4 rounded-lg border border-surface-300 dark:border-surface-600 text-sm font-semibold text-surface-900 dark:text-surface-0 hover:border-primary cursor-pointer aria-disabled:opacity-50 aria-disabled:cursor-default aria-disabled:hover:border-surface-300 dark:aria-disabled:hover:border-surface-600'

const activeTab = ref('dashboard')

watch(
  () => props.requestedTab,
  (requested) => {
    activeTab.value = requestedDemoTab(TABS, requested) ?? activeTab.value
  },
  { immediate: true }
)
const doneTaskIds = ref([...INITIAL_DONE_TASK_IDS])
const setlistOrder = ref([...INITIAL_SETLIST_ORDER])
const deletedFileIds = ref([])
const addedEventCount = ref(0)
const addedExpenseCount = ref(0)
const editedNoteBodies = ref({})
const openNoteId = ref(null)

const shownName = computed(
  () => props.name.trim() || t('home.band_space_presentation.preview.your_band')
)
const roster = computed(() => demoRoster(props.size))
const membersLabel = computed(() =>
  props.size === 6
    ? t('home.band_space_presentation.preview.members_and_more', { count: props.size })
    : t('home.band_space_presentation.preview.members', { count: props.size })
)

const agenda = computed(() => demoAgenda(props.size, addedEventCount.value))
const tasks = computed(() => demoTasks(props.size, doneTaskIds.value))
const setlist = computed(() => demoSetlist(setlistOrder.value))
const files = computed(() => demoFiles(props.size, deletedFileIds.value))
const expenses = computed(() => demoExpenses(props.size, addedExpenseCount.value))
const balances = computed(() => demoBalances(props.size, expenses.value))
const expensesTotal = computed(() =>
  expenses.value.reduce((total, expense) => total + expense.amount, 0)
)
const sharePerMember = computed(() =>
  formatEuros(Math.round((expensesTotal.value / props.size) * 100) / 100)
)

const notes = computed(() => demoNotes(props.size, editedNoteBodies.value))
const openNote = computed(() => notes.value.find((note) => note.id === openNoteId.value) ?? null)
const openNoteBody = computed({
  get: () => openNote.value.body,
  set: (body) => {
    editedNoteBodies.value = { ...editedNoteBodies.value, [openNoteId.value]: body }
  }
})

const panels = computed(() => {
  const openTasks = tasks.value.filter((task) => !task.done)
  const doneCount = tasks.value.length - openTasks.length
  return {
    agenda: {
      title: t('home.band_space_presentation.preview.panels.agenda_title'),
      subtitle: t('home.band_space_presentation.preview.panels.agenda_subtitle'),
      summary: t('home.band_space_presentation.preview.panels.agenda_summary', {
        available: Math.max(1, props.size - 1),
        size: props.size
      }),
      addLabel: t('home.band_space_presentation.preview.panels.agenda_add'),
      addedCount: addedEventCount.value,
      add: () => addedEventCount.value++
    },
    taches: {
      title: t('home.band_space_presentation.preview.panels.tasks_title'),
      subtitle: t('home.band_space_presentation.preview.panels.tasks_subtitle'),
      summary: t(
        'home.band_space_presentation.preview.panels.tasks_summary',
        { total: tasks.value.length },
        doneCount
      ),
      openTasks
    },
    notes: {
      title: t('home.band_space_presentation.preview.panels.notes_title'),
      subtitle: t('home.band_space_presentation.preview.panels.notes_subtitle'),
      summary: ''
    },
    setlists: {
      title: t('home.band_space_presentation.preview.panels.setlists_title'),
      subtitle: t('home.band_space_presentation.preview.panels.setlists_subtitle'),
      summary: t('home.band_space_presentation.preview.panels.setlists_summary', {
        songs: setlist.value.length,
        minutes: setlistMinutes(setlist.value)
      })
    },
    files: {
      title: t('home.band_space_presentation.preview.panels.files_title'),
      subtitle: t('home.band_space_presentation.preview.panels.files_subtitle'),
      summary: t('home.band_space_presentation.preview.panels.files_summary', files.value.length)
    },
    finances: {
      title: t('home.band_space_presentation.preview.panels.finances_title'),
      subtitle: t('home.band_space_presentation.preview.panels.finances_subtitle', {
        amount: formatEuros(expensesTotal.value),
        size: props.size
      }),
      summary: t('home.band_space_presentation.preview.panels.finances_summary', {
        amount: sharePerMember.value
      }),
      addLabel: t('home.band_space_presentation.preview.panels.finances_add'),
      addedCount: addedExpenseCount.value,
      add: () => addedExpenseCount.value++
    }
  }
})

const widgets = computed(() => {
  const nextTask = panels.value.taches.openTasks[0]
  const content = {
    agenda: {
      tag: t('home.band_space_presentation.preview.widgets.agenda_tag'),
      title: t('home.band_space_presentation.preview.widgets.agenda_title'),
      meta: t('home.band_space_presentation.preview.widgets.agenda_meta', {
        available: Math.max(1, props.size - 1),
        size: props.size
      })
    },
    taches: {
      tag: t('home.band_space_presentation.preview.widgets.tasks_tag'),
      title: t('home.band_space_presentation.preview.widgets.tasks_title', {
        count: panels.value.taches.openTasks.length
      }),
      meta: nextTask
        ? t('home.band_space_presentation.preview.widgets.tasks_next', {
            task: nextTask.label.toLowerCase()
          })
        : t('home.band_space_presentation.preview.widgets.tasks_all_done')
    },
    setlists: {
      tag: t('home.band_space_presentation.preview.widgets.setlists_tag'),
      title: t('home.band_space_presentation.preview.widgets.setlists_title'),
      meta: t('home.band_space_presentation.preview.widgets.setlists_meta', {
        songs: setlist.value.length,
        minutes: setlistMinutes(setlist.value)
      })
    },
    finances: {
      tag: t('home.band_space_presentation.preview.widgets.finances_tag'),
      title: t('home.band_space_presentation.preview.widgets.finances_title', {
        amount: formatEuros(expensesTotal.value)
      }),
      meta: t('home.band_space_presentation.preview.widgets.finances_meta', {
        amount: sharePerMember.value
      })
    },
    files: {
      tag: t('home.band_space_presentation.preview.widgets.files_tag'),
      title: t('home.band_space_presentation.preview.widgets.files_title'),
      meta: t('home.band_space_presentation.preview.widgets.files_meta', {
        count: files.value.length
      })
    }
  }
  return dashboardModules(props.pickedPains).map((key) => ({
    key,
    ...content[key],
    isPicked: props.pickedPains.includes(key)
  }))
})

function toggleTask(id) {
  doneTaskIds.value = doneTaskIds.value.includes(id)
    ? doneTaskIds.value.filter((doneId) => doneId !== id)
    : [...doneTaskIds.value, id]
}

// The deleted row takes its button with it, so focus moves on to the next file, or to the restore
// button once the list is empty, instead of falling back to the page.
async function deleteFile(id) {
  const index = files.value.findIndex((file) => file.id === id)
  deletedFileIds.value = [...deletedFileIds.value, id]
  await nextTick()
  const panel = document.getElementById('demo-panel-files')
  const remaining = panel.querySelectorAll('[data-file-delete]')
  ;(
    remaining[Math.min(index, remaining.length - 1)] ?? panel.querySelector('[data-files-restore]')
  )?.focus()
}

function moveSongAt(index, step) {
  setlistOrder.value = moveSong(setlistOrder.value, index, step)
}

function selectTab(key) {
  activeTab.value = key
  document.getElementById(`demo-tab-${key}`)?.focus()
}

function moveTab(step) {
  selectTab(adjacentTabKey(TABS, activeTab.value, step))
}
</script>
