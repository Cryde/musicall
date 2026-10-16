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
          {{ t('profile.settings.musician.empty_title') }}
        </h3>
        <p class="text-surface-500 dark:text-surface-400 mb-6 max-w-md">
          {{ t('profile.settings.musician.empty_help') }}
        </p>
        <Button
          :label="t('profile.settings.musician.create')"
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
            {{ t('profile.settings.musician.availability') }}
          </div>
        </div>
        <div class="md:w-2/3">
          <Tag
            v-if="profile.availability_status"
            :value="profile.availability_status_label"
            :severity="getAvailabilitySeverity(profile.availability_status)"
          />
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            {{ t('profile.settings.musician.availability_empty') }}
          </span>
        </div>
      </div>

      <!-- Instruments -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.musician.instruments') }}
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
            {{ t('profile.settings.musician.instruments_empty') }}
          </span>
        </div>
      </div>

      <!-- Styles -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.musician.styles') }}
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
            {{ t('profile.settings.musician.styles_empty') }}
          </span>
        </div>
      </div>

      <!-- Action buttons -->
      <div class="flex justify-between items-center">
        <Button
          :label="t('profile.settings.musician.delete_profile')"
          icon="pi pi-trash"
          severity="danger"
          text
          :loading="isDeleting"
          @click="showDeleteConfirm = true"
        />
        <div class="flex items-center gap-2">
          <RouterLink :to="{ name: 'app_user_musician_profile', params: { username: userSecurityStore.user.username } }">
            <Button
              :label="t('profile.settings.musician.view_profile')"
              icon="pi pi-external-link"
              severity="secondary"
              outlined
            />
          </RouterLink>
          <Button
            :label="t('profile.settings.musician.edit')"
            icon="pi pi-pencil"
            @click="showEditModal = true"
          />
        </div>
      </div>

      <!-- Media showcase section -->
      <div class="mt-8 pt-6 border-t border-surface-200 dark:border-surface-700">
        <MediaShowcase :is-own-profile="true" />
      </div>
    </template>

    <!-- Delete confirmation dialog -->
    <Dialog
      v-model:visible="showDeleteConfirm"
      modal
      :header="t('profile.settings.musician.delete_title')"
      :style="{ width: '450px' }"
    >
      <div class="flex items-start gap-4">
        <i class="pi pi-exclamation-triangle text-4xl text-red-500 shrink-0" />
        <div class="flex flex-col gap-2">
          <span>
            {{ t('profile.settings.musician.delete_confirm') }}
          </span>
          <span class="text-surface-500 dark:text-surface-400 text-sm">
            {{ t('profile.settings.musician.delete_media_notice') }}
          </span>
          <span class="text-red-500 text-sm font-medium">
            {{ t('profile.settings.musician.delete_irreversible') }}
          </span>
        </div>
      </div>
      <template #footer>
        <Button
          :label="t('profile.settings.musician.cancel')"
          text
          @click="showDeleteConfirm = false"
        />
        <Button
          :label="t('profile.settings.musician.delete')"
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
import { useI18n } from 'vue-i18n'
import MusicNotesIcon from '../../../components/Icons/MusicNotesIcon.vue'
import EditMusicianProfileModal from '../../../components/User/Profile/EditMusicianProfileModal.vue'
import MediaShowcase from '../../../components/User/Profile/MediaShowcase.vue'
import { useMusicianProfileStore } from '../../../store/user/musicianProfile.js'
import { useMusicianProfileMediaStore } from '../../../store/user/musicianProfileMedia.js'
import { useUserSecurityStore } from '../../../store/user/security.js'

const musicianProfileStore = useMusicianProfileStore()
const userSecurityStore = useUserSecurityStore()
const musicianProfileMediaStore = useMusicianProfileMediaStore()
const toast = useToast()
const { t } = useI18n()

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
        summary: t('profile.settings.musician.error'),
        detail: t('profile.settings.musician.load_error'),
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
    summary: t('profile.settings.musician.saved'),
    detail: t('profile.settings.musician.saved_detail'),
    life: 5000
  })
}

async function handleDeleteProfile() {
  try {
    await musicianProfileStore.deleteProfile()
    showDeleteConfirm.value = false
    toast.add({
      severity: 'success',
      summary: t('profile.settings.musician.deleted'),
      detail: t('profile.settings.musician.deleted_detail'),
      life: 5000
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('profile.settings.musician.error'),
      detail: t('profile.settings.musician.delete_error'),
      life: 5000
    })
  }
}

onMounted(() => {
  loadProfile()
})

onUnmounted(() => {
  musicianProfileStore.clear()
  musicianProfileMediaStore.clear()
})
</script>
