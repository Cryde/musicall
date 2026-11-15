<template>
  <div class="flex flex-col gap-6">
    <div class="flex flex-col gap-2">
      <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('profile.settings.profile.title') }}
      </h2>
      <p class="text-surface-500 dark:text-surface-400 text-sm">
        {{ t('profile.settings.profile.subtitle') }}
      </p>
    </div>

    <div v-if="isLoading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl" aria-hidden="true" />
    </div>

    <!-- The links below need the member's username. -->
    <div v-else-if="username" class="flex flex-col divide-y divide-surface-200 dark:divide-surface-700">
      <!-- Everything shown on the profile is edited there (#1317). -->
      <section :class="ROW_CLASS">
        <div class="md:w-1/3">
          <h3 :class="LABEL_CLASS">{{ t('profile.settings.profile.sections.profile') }}</h3>
        </div>
        <div class="md:w-2/3 flex flex-col items-start gap-3">
          <p class="m-0 text-sm text-surface-600 dark:text-surface-400">
            {{ t('profile.settings.profile.edit_on_profile') }}
          </p>
          <Button
            as="router-link"
            :to="{ name: 'app_user_public_profile', params: { username } }"
            :label="t('profile.settings.profile.open_profile')"
            icon="pi pi-user"
          />
        </div>
      </section>

      <!-- Left out until the profile says what it is: a guess could save the wrong visibility. -->
      <section v-if="savedIsPublic !== null" :class="ROW_CLASS">
        <div class="md:w-1/3">
          <h3 :class="LABEL_CLASS">{{ t('profile.settings.profile_privacy.visibility') }}</h3>
          <p class="text-sm text-surface-500 dark:text-surface-400 mt-1 mb-0">
            {{ t('profile.settings.profile_privacy.visibility_help') }}
          </p>
        </div>
        <div class="md:w-2/3 flex flex-col gap-4">
          <div class="flex items-center gap-3">
            <ToggleSwitch
              v-model="isPublic"
              :disabled="isSavingVisibility"
              :aria-label="t('profile.settings.profile_privacy.visibility')"
            />
            <span class="text-surface-700 dark:text-surface-300">
              {{ isPublic ? t('profile.settings.profile_privacy.public') : t('profile.settings.profile_privacy.private') }}
            </span>
          </div>
          <div class="p-4 rounded-lg" :class="isPublic ? 'bg-green-50 dark:bg-green-900/20' : 'bg-surface-100 dark:bg-surface-800'">
            <div class="flex items-start gap-3">
              <i
                :class="['pi text-lg mt-0.5', isPublic ? 'pi-globe text-green-600 dark:text-green-400' : 'pi-lock text-surface-500']"
                aria-hidden="true"
              />
              <div>
                <p class="m-0 font-medium text-surface-900 dark:text-surface-0">
                  {{ isPublic ? t('profile.settings.profile_privacy.visible_title') : t('profile.settings.profile_privacy.hidden_title') }}
                </p>
                <p class="mb-0 text-sm text-surface-600 dark:text-surface-400 mt-1">
                  {{ isPublic ? t('profile.settings.profile_privacy.visible_help') : t('profile.settings.profile_privacy.hidden_help') }}
                </p>
              </div>
            </div>
          </div>
          <div>
            <Button
              :label="t('profile.settings.profile_privacy.save')"
              icon="pi pi-check"
              :loading="isSavingVisibility"
              :disabled="isPublic === savedIsPublic"
              @click="saveVisibility"
            />
          </div>
        </div>
      </section>

      <section :class="ROW_CLASS">
        <div class="md:w-1/3">
          <h3 :class="LABEL_CLASS">{{ t('profile.settings.profile.sections.musician') }}</h3>
        </div>
        <div class="md:w-2/3 flex flex-col items-start gap-3">
          <template v-if="musicianProfileStore.profile">
            <Button
              as="router-link"
              :to="{ name: 'app_user_public_profile', params: { username }, query: { voir: 'medias' } }"
              :label="t('profile.settings.musician.view_profile')"
              icon="pi pi-external-link"
              severity="secondary"
              outlined
            />
            <Button
              :label="t('profile.settings.musician.delete_title')"
              icon="pi pi-trash"
              severity="danger"
              text
              :loading="musicianProfileStore.isDeleting"
              @click="confirmDeleteMusician"
            />
          </template>
          <template v-else>
            <p class="m-0 text-sm text-surface-600 dark:text-surface-400">{{ t('profile.settings.musician.empty_help') }}</p>
            <Button :label="t('profile.settings.musician.create')" icon="pi pi-plus" @click="showCreateMusician = true" />
          </template>
        </div>
      </section>
    </div>

    <EditMusicianProfileModal v-model:visible="showCreateMusician" :musician-profile="null" @saved="handleMusicianCreated" />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import ToggleSwitch from 'primevue/toggleswitch'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import profileApi from '../../../api/user/profile.js'
import EditMusicianProfileModal from '../../../components/User/Profile/EditMusicianProfileModal.vue'
import { useMusicianProfileStore } from '../../../store/user/musicianProfile.js'
import { useUserSecurityStore } from '../../../store/user/security.js'

/**
 * The profile settings (#1317): what the profile is and who sees it. Its content is edited on the profile itself,
 * so only its visibility and the musician profile's creation and deletion stay here; the teacher profile is a service,
 * managed in « Mes services » (#1406).
 */
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const userSecurityStore = useUserSecurityStore()
const musicianProfileStore = useMusicianProfileStore()

const ROW_CLASS = 'flex flex-col md:flex-row md:items-start gap-4 py-5 first:pt-0'
const LABEL_CLASS = 'm-0 text-base font-medium text-surface-600 dark:text-surface-400'
const isLoading = ref(true)
const isPublic = ref(null)
const savedIsPublic = ref(null)
const isSavingVisibility = ref(false)
const showCreateMusician = ref(false)

const username = computed(() => userSecurityStore.user?.username)

// A 404 means the member has no musician profile yet.
async function loadMusicianProfile() {
  try {
    await musicianProfileStore.loadMyProfile()
  } catch (error) {
    if (error.response?.status !== 404) {
      toast.add({
        severity: 'error',
        summary: t('profile.settings.musician.load_error'),
        life: 5000
      })
    }
  }
}

async function loadVisibility() {
  try {
    const profile = await profileApi.getMyProfile()
    isPublic.value = profile.is_public
    savedIsPublic.value = profile.is_public
  } catch {
    toast.add({
      severity: 'error',
      summary: t('profile.settings.profile_privacy.load_error'),
      life: 5000
    })
  }
}

async function saveVisibility() {
  isSavingVisibility.value = true
  try {
    await profileApi.updateMyProfile({ is_public: isPublic.value })
    savedIsPublic.value = isPublic.value
    toast.add({
      severity: 'success',
      summary: t('profile.settings.profile_privacy.saved'),
      detail: t('profile.settings.profile_privacy.saved_detail'),
      life: 5000
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('profile.settings.profile_privacy.error'),
      detail: error.message || t('profile.settings.profile_privacy.save_error'),
      life: 5000
    })
  } finally {
    isSavingVisibility.value = false
  }
}

function confirmDeleteMusician() {
  confirm.require({
    header: t('profile.settings.musician.delete_title'),
    message: [
      t('profile.settings.musician.delete_confirm'),
      t('profile.settings.musician.delete_media_notice'),
      t('profile.settings.musician.delete_irreversible')
    ].join(' '),
    icon: 'pi pi-exclamation-triangle',
    rejectProps: {
      label: t('profile.settings.musician.cancel'),
      severity: 'secondary',
      outlined: true
    },
    acceptProps: { label: t('profile.settings.musician.delete'), severity: 'danger' },
    accept: deleteMusicianProfile
  })
}

async function deleteMusicianProfile() {
  try {
    await musicianProfileStore.deleteProfile()
    toast.add({
      severity: 'success',
      summary: t('profile.settings.musician.deleted'),
      detail: t('profile.settings.musician.deleted_detail'),
      life: 5000
    })
  } catch {
    toast.add({
      severity: 'error',
      summary: t('profile.settings.musician.delete_error'),
      life: 5000
    })
  }
}

function handleMusicianCreated() {
  loadMusicianProfile()
  toast.add({
    severity: 'success',
    summary: t('profile.settings.musician.saved'),
    detail: t('profile.settings.musician.saved_detail'),
    life: 5000
  })
}

onMounted(async () => {
  await Promise.all([loadVisibility(), loadMusicianProfile()])
  isLoading.value = false
})

onUnmounted(() => {
  musicianProfileStore.clear()
})
</script>
