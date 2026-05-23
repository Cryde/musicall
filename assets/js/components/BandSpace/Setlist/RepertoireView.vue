<template>
  <div class="flex flex-col gap-4">
    <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 border border-surface-200 dark:border-surface-700 flex flex-wrap items-center gap-3">
      <div class="flex-1 min-w-[200px]">
        <IconField iconPosition="left">
          <InputIcon class="pi pi-search" />
          <InputText v-model="query" placeholder="Rechercher un titre…" class="w-full" />
        </IconField>
      </div>
      <Button label="Ajouter un titre" icon="pi pi-plus" size="small" @click="openCreateDialog" />
    </div>

    <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 overflow-hidden">
      <div v-if="songsStore.isLoading && songsStore.songs.length === 0" class="p-4 flex flex-col gap-2">
        <Skeleton v-for="i in 4" :key="i" width="100%" height="3rem" borderRadius="0.5rem" />
      </div>
      <div v-else-if="filteredSongs.length === 0" class="p-8 text-center text-surface-500">
        <i class="pi pi-music text-3xl mb-3 block"></i>
        {{ query ? 'Aucun titre ne correspond à votre recherche.' : 'Aucun titre dans le répertoire. Commencez par en ajouter un.' }}
      </div>
      <table v-else class="w-full text-sm">
        <thead class="bg-surface-50 dark:bg-surface-800 text-xs uppercase tracking-wide text-surface-600 dark:text-surface-300">
          <tr>
            <th class="text-left px-4 py-3">Titre</th>
            <th class="text-left px-4 py-3 hidden md:table-cell">Tonalité</th>
            <th class="text-right px-4 py-3 hidden md:table-cell">BPM</th>
            <th class="text-right px-4 py-3 hidden lg:table-cell">Durée</th>
            <th class="px-4 py-3 w-12"></th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="song in filteredSongs"
            :key="song.id"
            class="border-t border-surface-100 dark:border-surface-800 hover:bg-surface-50 dark:hover:bg-surface-800 cursor-pointer"
            @click="openDrawer(song)"
          >
            <td class="px-4 py-3 font-medium">{{ song.title }}</td>
            <td class="px-4 py-3 hidden md:table-cell text-surface-600 dark:text-surface-300">{{ song.tonality || '—' }}</td>
            <td class="px-4 py-3 hidden md:table-cell text-right tabular-nums text-surface-600 dark:text-surface-300">{{ song.tempo || '—' }}</td>
            <td class="px-4 py-3 hidden lg:table-cell text-right tabular-nums text-surface-600 dark:text-surface-300">
              {{ formatDuration(song.reference_duration) }}
            </td>
            <td class="px-4 py-3 text-right">
              <Button
                icon="pi pi-ellipsis-v"
                severity="secondary"
                text
                rounded
                @click.stop="openMenu($event, song)"
                aria-label="Actions"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Menu ref="actionsMenu" :model="menuItems" :popup="true" />

    <SongFormDialog
      v-model:visible="dialogVisible"
      :band-space-id="bandSpaceId"
      :song="editingSong"
      @saved="handleSaved"
    />

    <SongDetailDrawer
      v-model:visible="drawerVisible"
      :band-space-id="bandSpaceId"
      :song="drawerSong"
      @edit="handleEditFromDrawer"
      @archived="handleArchivedFromDrawer"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Menu from 'primevue/menu'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useBandSongsStore } from '../../../store/bandSpace/bandSpaceSongs.js'
import SongDetailDrawer from './SongDetailDrawer.vue'
import SongFormDialog from './SongFormDialog.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const songsStore = useBandSongsStore()
const confirm = useConfirm()
const toast = useToast()

const query = ref('')
const dialogVisible = ref(false)
const editingSong = ref(null)
const drawerVisible = ref(false)
const drawerSong = ref(null)
const actionsMenu = ref(null)
const menuTargetSong = ref(null)

const filteredSongs = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return songsStore.songs
  return songsStore.songs.filter((s) => s.title.toLowerCase().includes(q))
})

const menuItems = computed(() => [
  {
    label: 'Modifier',
    icon: 'pi pi-pencil',
    command: () => openEditDialog(menuTargetSong.value)
  },
  {
    label: 'Archiver',
    icon: 'pi pi-archive',
    command: () => confirmArchive(menuTargetSong.value)
  }
])

function formatDuration(seconds) {
  if (!seconds) return '—'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}′${String(s).padStart(2, '0')}″`
}

function openCreateDialog() {
  editingSong.value = null
  dialogVisible.value = true
}

function openEditDialog(song) {
  editingSong.value = song
  dialogVisible.value = true
}

function openDrawer(song) {
  drawerSong.value = song
  drawerVisible.value = true
}

function openMenu(event, song) {
  menuTargetSong.value = song
  actionsMenu.value?.toggle(event)
}

function handleEditFromDrawer(song) {
  drawerVisible.value = false
  openEditDialog(song)
}

function handleArchivedFromDrawer() {
  drawerVisible.value = false
  drawerSong.value = null
}

function handleSaved() {
  dialogVisible.value = false
  editingSong.value = null
}

function confirmArchive(song) {
  if (!song) return
  confirm.require({
    message: `Archiver le titre « ${song.title} » ? Il sera retiré du répertoire actif.`,
    header: "Confirmer l'archivage",
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Archiver',
    rejectLabel: 'Annuler',
    accept: async () => {
      try {
        await songsStore.deleteSong(props.bandSpaceId, song.id)
        toast.add({ severity: 'success', summary: 'Titre archivé', life: 3000 })
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
      }
    }
  })
}
</script>
