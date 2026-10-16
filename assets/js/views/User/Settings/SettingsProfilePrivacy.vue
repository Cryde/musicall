<template>
  <div class="flex flex-col gap-6">
    <div v-if="isLoading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl"></i>
    </div>

    <template v-else-if="userProfileStore.profile">
      <!-- Public profile toggle -->
      <div class="flex flex-col md:flex-row md:items-start gap-4 py-3">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.profile_privacy.visibility') }}
          </div>
          <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
            {{ t('profile.settings.profile_privacy.visibility_help') }}
          </p>
        </div>
        <div class="md:w-2/3 flex flex-col gap-4">
          <div class="flex items-center gap-3">
            <ToggleSwitch v-model="isPublic" :disabled="isUpdating" :aria-label="t('profile.settings.profile_privacy.visibility')" />
            <span class="text-surface-700 dark:text-surface-300">
              {{ isPublic ? t('profile.settings.profile_privacy.public') : t('profile.settings.profile_privacy.private') }}
            </span>
          </div>
          <div class="p-4 rounded-lg" :class="isPublic ? 'bg-green-50 dark:bg-green-900/20' : 'bg-surface-100 dark:bg-surface-800'">
            <div class="flex items-start gap-3">
              <i :class="['pi text-lg mt-0.5', isPublic ? 'pi-globe text-green-600 dark:text-green-400' : 'pi-lock text-surface-500']" />
              <div>
                <p class="font-medium text-surface-900 dark:text-surface-0">
                  {{ isPublic ? t('profile.settings.profile_privacy.visible_title') : t('profile.settings.profile_privacy.hidden_title') }}
                </p>
                <p class="text-sm text-surface-600 dark:text-surface-400 mt-1">
                  {{
                    isPublic
                      ? t('profile.settings.profile_privacy.visible_help')
                      : t('profile.settings.profile_privacy.hidden_help')
                  }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Save button -->
      <div class="flex justify-end pt-4 border-t border-surface-200 dark:border-surface-700">
        <Button
          :label="t('profile.settings.profile_privacy.save')"
          icon="pi pi-check"
          :loading="isUpdating"
          :disabled="!hasChanges"
          @click="savePrivacy"
        />
      </div>
    </template>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserProfileStore } from '../../../store/user/profile.js'

const userProfileStore = useUserProfileStore()
const toast = useToast()
const { t } = useI18n()

const isLoading = ref(true)
const isUpdating = ref(false)

const isPublic = ref(true)
const originalIsPublic = ref(true)

const hasChanges = computed(() => {
  return isPublic.value !== originalIsPublic.value
})

function setFormValues(profile) {
  isPublic.value = profile?.is_public ?? true
  originalIsPublic.value = isPublic.value
}

watch(
  () => userProfileStore.profile,
  (profile) => {
    if (profile) {
      setFormValues(profile)
    }
  }
)

async function loadData() {
  isLoading.value = true

  try {
    await userProfileStore.loadProfile()
    setFormValues(userProfileStore.profile)
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('profile.settings.profile_privacy.error'),
      detail: t('profile.settings.profile_privacy.load_error'),
      life: 5000
    })
  } finally {
    isLoading.value = false
  }
}

async function savePrivacy() {
  isUpdating.value = true

  try {
    await userProfileStore.updateProfile({
      is_public: isPublic.value
    })
    originalIsPublic.value = isPublic.value
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
    isUpdating.value = false
  }
}

onMounted(() => {
  loadData()
})
</script>
