<template>
  <div class="flex flex-col gap-6">
    <div v-if="isLoading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl"></i>
    </div>

    <!-- No profile - CTA to create -->
    <template v-else-if="!hasProfile">
      <div class="flex flex-col items-center justify-center py-12 text-center">
        <div class="flex items-center justify-center w-20 h-20 rounded-full bg-primary-100 dark:bg-primary-900/30 mb-6">
          <MusicNotesIcon :size="32" class="text-primary-600 dark:text-primary-400" />
        </div>
        <h3 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-2">
          Créez votre profil musicien
        </h3>
        <p class="text-surface-500 dark:text-surface-400 mb-6 max-w-md">
          Partagez vos instruments, votre niveau et vos styles musicaux pour que d'autres musiciens puissent vous trouver.
        </p>
        <Button
          label="Créer mon profil musicien"
          icon="pi pi-plus"
          @click="showEditModal = true"
        />
      </div>
    </template>

    <!-- Profile exists - show details -->
    <template v-else>
      <!-- Availability status -->
      <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            Disponibilité
          </div>
        </div>
        <div class="md:w-2/3">
          <Tag
            v-if="profile.availability_status"
            :value="profile.availability_status_label"
            :severity="getAvailabilitySeverity(profile.availability_status)"
          />
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            Non renseigné
          </span>
        </div>
      </div>

      <!-- Instruments -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            Instruments
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.instruments?.length" class="flex flex-col gap-2">
            <div
              v-for="instrument in profile.instruments"
              :key="instrument.instrument_id"
              class="flex items-center gap-2"
            >
              <span class="text-surface-900 dark:text-surface-0">
                {{ instrument.instrument_name }}
              </span>
              <Tag
                :value="instrument.skill_level_label"
                :severity="getSkillLevelSeverity(instrument.skill_level)"
                size="small"
              />
            </div>
          </div>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            Aucun instrument renseigné
          </span>
        </div>
      </div>

      <!-- Styles -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            Styles musicaux
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.styles?.length" class="flex flex-wrap gap-2">
            <Tag
              v-for="style in profile.styles"
              :key="style.id"
              :value="style.name"
              severity="info"
            />
          </div>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            Aucun style renseigné
          </span>
        </div>
      </div>

      <!-- Action buttons -->
      <div class="flex justify-between items-center">
        <Button
          label="Supprimer le profil"
          icon="pi pi-trash"
          severity="danger"
          text
          :loading="isDeleting"
          @click="showDeleteConfirm = true"
        />
        <Button
          label="Modifier"
          icon="pi pi-pencil"
          @click="showEditModal = true"
        />
      </div>
    </template>

    <!-- Delete confirmation dialog -->
    <Dialog
      v-model:visible="showDeleteConfirm"
      modal
      header="Supprimer le profil musicien"
      :style="{ width: '450px' }"
    >
      <div class="flex items-center gap-4">
        <i class="pi pi-exclamation-triangle text-4xl text-red-500" />
        <span>
          Êtes-vous sûr de vouloir supprimer votre profil musicien ?
          Cette action est irréversible.
        </span>
      </div>
      <template #footer>
        <Button
          label="Annuler"
          text
          @click="showDeleteConfirm = false"
        />
        <Button
          label="Supprimer"
          severity="danger"
          :loading="isDeleting"
          @click="handleDeleteProfile"
        />
      </template>
    </Dialog>

    <!-- Edit/Create modal -->
    <EditMusicianProfileModal
      v-model:visible="showEditModal"
      :musician-profile="profile"
      @saved="handleProfileSaved"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import EditMusicianProfileModal from '../../../components/User/Profile/EditMusicianProfileModal.vue'
import MusicNotesIcon from '../../../components/Icons/MusicNotesIcon.vue'
import { useMusicianProfileStore } from '../../../store/user/musicianProfile.js'

const musicianProfileStore = useMusicianProfileStore()
const toast = useToast()

const isLoading = ref(true)
const showEditModal = ref(false)
const showDeleteConfirm = ref(false)

const isDeleting = computed(() => musicianProfileStore.isDeleting)

const profile = computed(() => musicianProfileStore.profile)
const hasProfile = computed(() => profile.value !== null)

function getAvailabilitySeverity(status) {
  switch (status) {
    case 'looking_for_band':
      return 'success'
    case 'available_for_sessions':
      return 'info'
    case 'open_to_collaborations':
      return 'warn'
    case 'not_available':
      return 'secondary'
    default:
      return 'info'
  }
}

function getSkillLevelSeverity(level) {
  switch (level) {
    case 'beginner':
      return 'secondary'
    case 'intermediate':
      return 'info'
    case 'advanced':
      return 'warn'
    case 'professional':
      return 'success'
    default:
      return 'info'
  }
}

async function loadProfile() {
  isLoading.value = true
  try {
    await musicianProfileStore.loadMyProfile()
  } catch (error) {
    // 404 means no profile exists, which is fine
    if (error.response?.status !== 404) {
      toast.add({
        severity: 'error',
        summary: 'Erreur',
        detail: 'Impossible de charger le profil musicien',
        life: 5000
      })
    }
  } finally {
    isLoading.value = false
  }
}

function handleProfileSaved() {
  loadProfile()
  toast.add({
    severity: 'success',
    summary: 'Profil mis à jour',
    detail: 'Votre profil musicien a été enregistré',
    life: 5000
  })
}

async function handleDeleteProfile() {
  try {
    await musicianProfileStore.deleteProfile()
    showDeleteConfirm.value = false
    toast.add({
      severity: 'success',
      summary: 'Profil supprimé',
      detail: 'Votre profil musicien a été supprimé',
      life: 5000
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de supprimer le profil musicien',
      life: 5000
    })
  }
}

onMounted(() => {
  loadProfile()
})

onUnmounted(() => {
  musicianProfileStore.clear()
})
</script>
