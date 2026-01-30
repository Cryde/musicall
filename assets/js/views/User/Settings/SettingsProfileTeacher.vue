<template>
  <div class="flex flex-col gap-6">
    <div v-if="isLoading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl"></i>
    </div>

    <!-- No profile - CTA to create -->
    <template v-else-if="!hasProfile">
      <div class="flex flex-col items-center justify-center py-12 text-center">
        <div class="flex items-center justify-center w-20 h-20 rounded-full bg-primary-100 dark:bg-primary-900/30 mb-6">
          <i class="pi pi-graduation-cap text-3xl text-primary-600 dark:text-primary-400" />
        </div>
        <h3 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-2">
          Créez votre profil professeur
        </h3>
        <p class="text-surface-500 dark:text-surface-400 mb-6 max-w-md">
          Partagez vos compétences, votre expérience et vos tarifs pour que des élèves puissent vous trouver.
        </p>
        <Button
          label="Créer mon profil professeur"
          icon="pi pi-plus"
          @click="showEditModal = true"
        />
      </div>
    </template>

    <!-- Profile exists - show details -->
    <template v-else>
      <!-- Description -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            Présentation
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.description" class="text-surface-900 dark:text-surface-0 whitespace-pre-line">
            {{ truncateDescription(profile.description) }}
          </div>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            Non renseigné
          </span>
        </div>
      </div>

      <!-- Years of experience -->
      <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            Expérience
          </div>
        </div>
        <div class="md:w-2/3">
          <span v-if="profile.years_of_experience" class="text-surface-900 dark:text-surface-0">
            {{ profile.years_of_experience }} {{ profile.years_of_experience > 1 ? 'ans' : 'an' }}
          </span>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            Non renseigné
          </span>
        </div>
      </div>

      <!-- Course title -->
      <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            Titre du cours
          </div>
        </div>
        <div class="md:w-2/3">
          <span v-if="profile.course_title" class="text-surface-900 dark:text-surface-0">
            {{ profile.course_title }}
          </span>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            Non renseigné
          </span>
        </div>
      </div>

      <!-- Student levels -->
      <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            Niveaux acceptés
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.student_levels?.length" class="flex flex-wrap gap-2">
            <Tag
              v-for="level in profile.student_levels"
              :key="level"
              :value="getStudentLevelLabel(level)"
              severity="secondary"
            />
          </div>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            Aucun niveau renseigné
          </span>
        </div>
      </div>

      <!-- Age groups -->
      <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            Tranches d'âge
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.age_groups?.length" class="flex flex-wrap gap-2">
            <Tag
              v-for="ageGroup in profile.age_groups"
              :key="ageGroup"
              :value="getAgeGroupLabel(ageGroup)"
              severity="secondary"
            />
          </div>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            Aucune tranche d'âge renseignée
          </span>
        </div>
      </div>

      <!-- Locations -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            Lieux d'enseignement
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.locations?.length" class="flex flex-col gap-1">
            <div v-for="(loc, index) in profile.locations" :key="index" class="text-surface-900 dark:text-surface-0">
              <span v-if="loc.type === 'teacher_place'">
                <i class="pi pi-home mr-1 text-surface-500" />Chez le professeur<span v-if="loc.city"> ({{ loc.city }})</span>
              </span>
              <span v-else-if="loc.type === 'student_place'">
                <i class="pi pi-car mr-1 text-surface-500" />Chez l'élève<span v-if="loc.radius"> ({{ loc.radius }} km)</span><span v-if="loc.city"> - {{ loc.city }}</span>
              </span>
              <span v-else-if="loc.type === 'online'">
                <i class="pi pi-video mr-1 text-surface-500" />En ligne
              </span>
            </div>
          </div>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            Non renseigné
          </span>
        </div>
      </div>

      <!-- Instruments -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            Instruments enseignés
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.instruments?.length" class="flex flex-col gap-2">
            <div
              v-for="instrument in profile.instruments"
              :key="instrument.instrument_id"
              class="text-surface-900 dark:text-surface-0"
            >
              {{ instrument.instrument_name }}
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
            Styles enseignés
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

      <!-- Trial offer -->
      <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            Cours d'essai
          </div>
        </div>
        <div class="md:w-2/3">
          <span v-if="profile.offers_trial" class="text-surface-900 dark:text-surface-0">
            <i class="pi pi-check-circle text-green-500 mr-1" />
            Proposé
            <span v-if="profile.trial_price != null">
              - {{ profile.trial_price === 0 ? 'Gratuit' : formatPrice(profile.trial_price) }}
            </span>
          </span>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            Non proposé
          </span>
        </div>
      </div>

      <!-- Pricing -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            Tarifs
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.pricing?.length" class="flex flex-col gap-1">
            <div v-for="pricing in profile.pricing" :key="pricing.id" class="text-surface-900 dark:text-surface-0">
              <span class="font-medium">{{ getSessionDurationLabel(pricing.duration) }}</span>
              <span class="text-surface-500 dark:text-surface-400 ml-2">{{ formatPrice(pricing.price) }}</span>
            </div>
          </div>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            Aucun tarif renseigné
          </span>
        </div>
      </div>

      <!-- Availability -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            Disponibilités
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.availability?.length" class="flex flex-col gap-1">
            <div v-for="slot in profile.availability" :key="slot.id" class="text-surface-900 dark:text-surface-0">
              <span class="font-medium">{{ getDayOfWeekLabel(slot.day_of_week) }}</span>
              <span class="text-surface-500 dark:text-surface-400 ml-2">{{ slot.start_time }} - {{ slot.end_time }}</span>
            </div>
          </div>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            Aucune disponibilité renseignée
          </span>
        </div>
      </div>

      <!-- Packages -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            Forfaits
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.packages?.length" class="flex flex-col gap-2">
            <div v-for="pkg in profile.packages" :key="pkg.id" class="text-surface-900 dark:text-surface-0">
              <span class="font-medium">{{ pkg.title }}</span>
              <span class="text-surface-500 dark:text-surface-400 ml-2">
                {{ pkg.sessions_count }} séances - {{ formatPrice(pkg.price) }}
              </span>
            </div>
          </div>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            Aucun forfait renseigné
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

      <!-- Media showcase section -->
      <div class="mt-8 pt-6 border-t border-surface-200 dark:border-surface-700">
        <TeacherMediaShowcase :is-own-profile="true" />
      </div>
    </template>

    <!-- Delete confirmation dialog -->
    <Dialog
      v-model:visible="showDeleteConfirm"
      modal
      header="Supprimer le profil professeur"
      :style="{ width: '450px' }"
    >
      <div class="flex items-start gap-4">
        <i class="pi pi-exclamation-triangle text-4xl text-red-500 shrink-0" />
        <div class="flex flex-col gap-2">
          <span>
            Êtes-vous sûr de vouloir supprimer votre profil professeur ?
          </span>
          <span class="text-surface-500 dark:text-surface-400 text-sm">
            Vos créations seront également supprimées de MusicAll.
          </span>
          <span class="text-red-500 text-sm font-medium">
            Cette action est irréversible.
          </span>
        </div>
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
    <EditTeacherProfileModal
      v-model:visible="showEditModal"
      :teacher-profile="profile"
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
import EditTeacherProfileModal from '../../../components/Teacher/EditTeacherProfileModal.vue'
import TeacherMediaShowcase from '../../../components/Teacher/TeacherMediaShowcase.vue'
import { getAgeGroupLabel, getDayOfWeekLabel, getSessionDurationLabel, getStudentLevelLabel } from '../../../constants/teacherProfile.js'
import { useTeacherProfileStore } from '../../../store/user/teacherProfile.js'
import { useTeacherProfileMediaStore } from '../../../store/user/teacherProfileMedia.js'

const teacherProfileStore = useTeacherProfileStore()
const teacherProfileMediaStore = useTeacherProfileMediaStore()
const toast = useToast()

const isLoading = ref(true)
const showEditModal = ref(false)
const showDeleteConfirm = ref(false)

const isDeleting = computed(() => teacherProfileStore.isDeleting)

const profile = computed(() => teacherProfileStore.profile)
const hasProfile = computed(() => profile.value !== null)

function truncateDescription(text, maxLength = 200) {
  if (!text || text.length <= maxLength) return text
  return text.substring(0, maxLength) + '...'
}

function formatPrice(priceInCents) {
  if (priceInCents == null) return ''
  const euros = priceInCents / 100
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(euros)
}

async function loadProfile() {
  isLoading.value = true
  try {
    await teacherProfileStore.loadMyProfile()
  } catch (error) {
    // 404 means no profile exists, which is fine
    if (error.response?.status !== 404) {
      toast.add({
        severity: 'error',
        summary: 'Erreur',
        detail: 'Impossible de charger le profil professeur',
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
    detail: 'Votre profil professeur a été enregistré',
    life: 5000
  })
}

async function handleDeleteProfile() {
  try {
    await teacherProfileStore.deleteProfile()
    showDeleteConfirm.value = false
    toast.add({
      severity: 'success',
      summary: 'Profil supprimé',
      detail: 'Votre profil professeur a été supprimé',
      life: 5000
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de supprimer le profil professeur',
      life: 5000
    })
  }
}

onMounted(() => {
  loadProfile()
})

onUnmounted(() => {
  teacherProfileStore.clear()
  teacherProfileMediaStore.clear()
})
</script>
