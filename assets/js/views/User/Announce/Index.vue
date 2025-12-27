<template>
  <div class="py-6 md:py-10">
    <div class="flex flex-col gap-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex flex-col gap-2">
          <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
            Mes annonces
          </h1>
          <p class="text-surface-500 dark:text-surface-400">
            Gerez vos annonces de recherche de musiciens ou de groupes
          </p>
        </div>
        <Button
          label="Nouvelle annonce"
          icon="pi pi-plus"
          severity="info"
          @click="showAddModal = true"
        />
      </div>

      <div v-if="userAnnounceStore.isLoading" class="flex justify-center py-12">
        <ProgressSpinner />
      </div>

      <div v-else-if="userAnnounceStore.announces.length === 0" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm">
        <div class="flex flex-col items-center justify-center py-12 text-surface-500 dark:text-surface-400">
          <i class="pi pi-megaphone text-4xl mb-4" />
          <p class="text-lg font-medium">Vous n'avez pas encore d'annonces</p>
          <p class="text-sm mt-2">Cliquez sur "Nouvelle annonce" pour en creer une</p>
        </div>
      </div>

      <DataView v-else :value="userAnnounceStore.announces" dataKey="id">
        <template #list="slotProps">
          <div class="flex flex-col gap-4">
            <div
              v-for="announce in slotProps.items"
              :key="announce.id"
              class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-4 md:p-6"
            >
              <div class="flex flex-col md:flex-row md:items-center gap-4">
                <!-- Type & Instrument -->
                <div class="flex items-center gap-3 md:w-48 shrink-0">
                  <div class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <i :class="['pi text-xl text-primary-600 dark:text-primary-400', announce.type === TYPES_ANNOUNCE_BAND ? 'pi-users' : 'pi-user']" />
                  </div>
                  <div>
                    <Tag :value="getTypeName(announce.type)" :severity="getTypeSeverity(announce.type)" />
                    <p class="text-sm font-medium text-surface-900 dark:text-surface-0 mt-1">
                      {{ announce.instrument.musician_name }}
                    </p>
                  </div>
                </div>

                <!-- Styles -->
                <div class="flex-1">
                  <p class="text-xs text-surface-500 dark:text-surface-400 mb-2">Styles</p>
                  <div class="flex flex-wrap gap-1">
                    <Tag
                      v-for="style in announce.styles"
                      :key="style.id"
                      :value="style.name"
                      severity="info"
                      size="small"
                    />
                  </div>
                </div>

                <!-- Location -->
                <div class="md:w-48 shrink-0">
                  <p class="text-xs text-surface-500 dark:text-surface-400 mb-1">Localisation</p>
                  <p class="text-sm text-surface-900 dark:text-surface-0 flex items-center gap-1">
                    <i class="pi pi-map-marker text-xs" />
                    {{ announce.location_name }}
                  </p>
                </div>

                <!-- Date -->
                <div class="md:w-28 shrink-0">
                  <p class="text-xs text-surface-500 dark:text-surface-400 mb-1">Date</p>
                  <p class="text-sm text-surface-900 dark:text-surface-0">
                    {{ formatDate(announce.creation_datetime) }}
                  </p>
                </div>

                <!-- Actions -->
                <div class="flex justify-end md:w-16 shrink-0">
                  <Button
                    icon="pi pi-trash"
                    severity="danger"
                    text
                    rounded
                    @click="confirmDelete(announce)"
                  />
                </div>
              </div>

              <!-- Note (if present) -->
              <div v-if="announce.note" class="mt-4 pt-4 border-t border-surface-200 dark:border-surface-700">
                <p class="text-xs text-surface-500 dark:text-surface-400 mb-1">Note</p>
                <p class="text-sm text-surface-700 dark:text-surface-300">
                  {{ announce.note }}
                </p>
              </div>
            </div>
          </div>
        </template>
      </DataView>
    </div>

    <AddAnnounceModal
      v-model:visible="showAddModal"
      @created="handleAnnounceCreated"
    />

    <ConfirmDialog />
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import DataView from 'primevue/dataview'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { onMounted, onUnmounted, ref } from 'vue'
import { TYPES_ANNOUNCE_BAND } from '../../../constants/types.js'
import { useUserAnnounceStore } from '../../../store/announce/userAnnounce.js'
import AddAnnounceModal from './AddAnnounceModal.vue'

useTitle('Mes annonces - MusicAll')

const confirm = useConfirm()
const userAnnounceStore = useUserAnnounceStore()
const showAddModal = ref(false)

onMounted(async () => {
  await userAnnounceStore.loadAnnounces()
})

onUnmounted(() => {
  userAnnounceStore.clear()
})

function getTypeName(type) {
  return type === TYPES_ANNOUNCE_BAND ? 'Groupe' : 'Musicien'
}

function getTypeSeverity(type) {
  return type === TYPES_ANNOUNCE_BAND ? 'success' : 'info'
}

function formatDate(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

function confirmDelete(announce) {
  confirm.require({
    message: 'Etes-vous sur de vouloir supprimer cette annonce ?',
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await userAnnounceStore.deleteAnnounce(announce.id)
    }
  })
}

function handleAnnounceCreated() {
  showAddModal.value = false
}
</script>
