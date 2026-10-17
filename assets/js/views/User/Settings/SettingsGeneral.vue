<template>
  <div class="flex flex-col gap-6">
    <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0">
      {{ t('profile.settings.general.title') }}
    </h2>

    <div v-if="userSettingsStore.isLoading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl"></i>
    </div>

    <template v-else-if="userSettingsStore.userProfile">
      <div class="flex flex-col gap-4">
        <!-- Username -->
        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
          <div class="md:w-1/3 text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.general.username') }}
          </div>
          <div class="md:w-2/3">
            <template v-if="!isEditingUsername">
              <div class="flex items-center gap-3">
                <span class="text-surface-900 dark:text-surface-0">
                  {{ userSettingsStore.userProfile.username }}
                </span>
                <Button
                  v-if="canChangeUsername"
                  :label="t('profile.settings.general.edit')"
                  icon="pi pi-pencil"
                  severity="info"
                  text
                  size="small"
                  @click="startEditUsername"
                />
              </div>
              <p v-if="!canChangeUsername && nextUsernameChangeDate" class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                {{ t('profile.settings.general.username_next_change', { date: nextUsernameChangeDate }) }}
              </p>
            </template>
            <template v-else>
              <div class="flex flex-col gap-2">
                <Message severity="warn" :closable="false" class="mb-2">
                  {{ t('profile.settings.general.username_warning') }}
                </Message>
                <div class="flex items-center gap-2">
                  <InputText
                    v-model="newUsername"
                    :placeholder="t('profile.settings.general.username_placeholder')"
                    class="w-full md:w-64"
                    :invalid="!!usernameError"
                  />
                  <i v-if="isCheckingUsername" class="pi pi-spin pi-spinner text-surface-500"></i>
                  <i v-else-if="isUsernameAvailable === true" class="pi pi-check-circle text-green-500"></i>
                  <i v-else-if="isUsernameAvailable === false" class="pi pi-times-circle text-red-500"></i>
                </div>
                <small v-if="usernameError" class="text-red-500">{{ usernameError }}</small>
                <small v-else-if="isUsernameAvailable === true" class="text-green-600">{{ t('profile.settings.general.username_available') }}</small>
                <div class="flex gap-2">
                  <Button
                    :label="t('profile.settings.general.save')"
                    icon="pi pi-check"
                    size="small"
                    :loading="userSettingsStore.isChangingUsername"
                    :disabled="isCheckingUsername || isUsernameAvailable === false"
                    @click="saveUsername"
                  />
                  <Button
                    :label="t('profile.settings.general.cancel')"
                    icon="pi pi-times"
                    severity="secondary"
                    text
                    size="small"
                    :disabled="userSettingsStore.isChangingUsername"
                    @click="cancelEditUsername"
                  />
                </div>
              </div>
            </template>
          </div>
        </div>

        <!-- Email -->
        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
          <div class="md:w-1/3 text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.general.email') }}
          </div>
          <div class="md:w-2/3 text-surface-900 dark:text-surface-0">
            {{ userSettingsStore.userProfile.email }}
          </div>
        </div>

        <!-- Profile Picture -->
        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
          <div class="md:w-1/3 text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.general.picture') }}
          </div>
          <div class="md:w-2/3 flex items-center gap-4">
            <Avatar
              v-if="userSettingsStore.userProfile.profile_picture"
              :image="profilePictureUrl"
              size="xlarge"
              shape="circle"
            />
            <Avatar
              v-else
              :label="userSettingsStore.userProfile.username.charAt(0).toUpperCase()"
              :style="getAvatarStyle(userSettingsStore.userProfile.username)"
              size="xlarge"
              shape="circle"
            />
            <div class="flex gap-2">
              <Button
                :label="userSettingsStore.userProfile.profile_picture ? t('profile.settings.general.picture_edit') : t('profile.settings.general.picture_add')"
                icon="pi pi-image"
                severity="info"
                outlined
                @click="triggerFileInput"
              />
              <Button
                v-if="userSettingsStore.userProfile.profile_picture"
                :label="t('profile.settings.general.picture_delete')"
                icon="pi pi-trash"
                severity="danger"
                outlined
                :loading="userSettingsStore.isDeletingPicture"
                @click="confirmDeletePicture"
              />
            </div>
            <input
              ref="fileInput"
              type="file"
              accept="image/*"
              class="hidden"
              @change="handleFileSelect"
            />
          </div>
        </div>

        <!-- Language -->
        <div
          v-if="CAN_CHOOSE_LOCALE"
          class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700"
        >
          <div class="md:w-1/3 text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.general.language') }}
          </div>
          <div class="md:w-2/3">
            <Select
              :modelValue="activeLocale"
              :options="LOCALE_OPTIONS"
              optionLabel="label"
              optionValue="locale"
              :aria-label="t('profile.settings.general.language')"
              :disabled="isSwitching"
              :pt="{ option: ({ context }) => ({ lang: context.option.locale }) }"
              class="w-full md:w-64"
              @update:modelValue="chooseLocale"
            />
          </div>
        </div>

        <!-- Dark Mode -->
        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3">
          <div class="md:w-1/3 text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.general.dark_mode') }}
          </div>
          <div class="md:w-2/3 flex items-center gap-3">
            <ToggleSwitch v-model="isDarkModeEnabled" :aria-label="t('profile.settings.general.dark_mode')" />
            <span class="text-surface-600 dark:text-surface-400 text-sm">
              {{ isDarkModeEnabled ? t('profile.settings.enabled') : t('profile.settings.disabled') }}
            </span>
          </div>
        </div>
      </div>

      <!-- Danger Zone -->
      <div class="mt-8 border border-red-300 dark:border-red-800 rounded-lg p-4">
        <h3 class="text-lg font-semibold text-red-600 dark:text-red-400 mb-2">{{ t('profile.settings.general.danger_zone') }}</h3>
        <p class="text-sm text-surface-600 dark:text-surface-400 mb-4">
          {{ t('profile.settings.general.danger_zone_help') }}
        </p>
        <Button
          :label="t('profile.settings.general.delete_account.title')"
          icon="pi pi-trash"
          severity="danger"
          @click="showDeleteAccountDialog = true"
        />
      </div>
    </template>

    <Dialog
      v-model:visible="showDeleteAccountDialog"
      :header="t('profile.settings.general.delete_account.title')"
      modal
      :style="{ width: '450px' }"
    >
      <div class="flex flex-col gap-4">
        <Message severity="warn" :closable="false">
          {{ t('profile.settings.general.delete_account.warning') }}
        </Message>
        <i18n-t keypath="profile.settings.general.delete_account.band_spaces" tag="p" class="text-sm text-surface-600 dark:text-surface-400">
          <template #label><span class="font-semibold">{{ t('profile.settings.general.delete_account.band_spaces_label') }}</span></template>
        </i18n-t>
        <template v-if="hasPassword">
          <p class="text-sm text-surface-600 dark:text-surface-400">
            {{ t('profile.settings.general.delete_account.password_help') }}
          </p>
          <InputText
            v-model="deleteAccountPassword"
            type="password"
            :placeholder="t('profile.settings.general.delete_account.password_placeholder')"
            class="w-full"
            :invalid="!!deleteAccountError"
          />
        </template>
        <template v-else>
          <i18n-t keypath="profile.settings.general.delete_account.phrase_help" tag="p" class="text-sm text-surface-600 dark:text-surface-400">
            <template #phrase><span class="font-semibold">{{ t('profile.settings.general.delete_account.confirm_phrase') }}</span></template>
          </i18n-t>
          <InputText
            v-model="deleteAccountConfirmation"
            :placeholder="t('profile.settings.general.delete_account.confirm_phrase')"
            class="w-full"
          />
        </template>
        <small v-if="deleteAccountError" class="text-red-500">{{ deleteAccountError }}</small>
      </div>
      <template #footer>
        <Button
          :label="t('profile.settings.general.cancel')"
          severity="secondary"
          text
          @click="showDeleteAccountDialog = false"
        />
        <Button
          :label="t('profile.settings.general.delete_account.submit')"
          icon="pi pi-trash"
          severity="danger"
          :loading="isDeletingAccount"
          :disabled="hasPassword ? !deleteAccountPassword : !isConfirmationValid"
          @click="handleDeleteAccount"
        />
      </template>
    </Dialog>

    <ProfilePictureModal
      v-model:visible="showPictureModal"
      :image="selectedImage"
      @saved="handlePictureSaved"
    />
  </div>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import securityApi from '../../../api/user/security.js'
import { useDarkMode } from '../../../composables/useDarkMode.js'
import {
  CAN_CHOOSE_LOCALE,
  LOCALE_OPTIONS,
  useLocaleChoice
} from '../../../composables/useLocaleChoice.js'
import { intlLocale } from '../../../i18n/formatLocale.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { useUserSettingsStore } from '../../../store/user/settings.js'
import { getAvatarStyle } from '../../../utils/avatar.js'
import ProfilePictureModal from './ProfilePictureModal.vue'

const COOLDOWN_DAYS = 30

const userSettingsStore = useUserSettingsStore()
const userSecurityStore = useUserSecurityStore()
const confirm = useConfirm()
const toast = useToast()
const { t } = useI18n()
const { isDarkMode, setDarkMode } = useDarkMode()
const { activeLocale, isSwitching, chooseLocale } = useLocaleChoice()

const fileInput = ref(null)
const selectedImage = ref(null)
const showPictureModal = ref(false)

const isEditingUsername = ref(false)
const newUsername = ref('')
const usernameError = ref('')
const isCheckingUsername = ref(false)
const isUsernameAvailable = ref(null)
let checkUsernameTimeout = null

const canChangeUsername = computed(() => {
  const lastChange = userSettingsStore.userProfile?.username_changed_datetime
  if (!lastChange) {
    return true
  }
  const lastChangeDate = new Date(lastChange)
  const cooldownEnd = new Date(lastChangeDate.getTime() + COOLDOWN_DAYS * 24 * 60 * 60 * 1000)
  return new Date() >= cooldownEnd
})

const nextUsernameChangeDate = computed(() => {
  const lastChange = userSettingsStore.userProfile?.username_changed_datetime
  if (!lastChange) {
    return null
  }
  const lastChangeDate = new Date(lastChange)
  const cooldownEnd = new Date(lastChangeDate.getTime() + COOLDOWN_DAYS * 24 * 60 * 60 * 1000)
  return cooldownEnd.toLocaleDateString(intlLocale(), {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })
})

function startEditUsername() {
  newUsername.value = userSettingsStore.userProfile?.username || ''
  usernameError.value = ''
  isEditingUsername.value = true
}

function cancelEditUsername() {
  isEditingUsername.value = false
  newUsername.value = ''
  usernameError.value = ''
  isUsernameAvailable.value = null
  if (checkUsernameTimeout) {
    clearTimeout(checkUsernameTimeout)
  }
}

const USERNAME_REGEX = /^[a-zA-Z0-9._]+$/

function validateUsername(username) {
  if (!username || username.length < 3) {
    return t('profile.settings.general.username_too_short')
  }
  if (username.length > 40) {
    return t('profile.settings.general.username_too_long')
  }
  if (!USERNAME_REGEX.test(username)) {
    return t('profile.settings.general.username_invalid')
  }
  return null
}

watch(newUsername, (value) => {
  usernameError.value = ''
  isUsernameAvailable.value = null

  if (checkUsernameTimeout) {
    clearTimeout(checkUsernameTimeout)
  }

  const trimmed = value?.trim()
  if (!trimmed || trimmed === userSettingsStore.userProfile?.username) {
    return
  }

  // Validate format before checking availability
  const validationError = validateUsername(trimmed)
  if (validationError) {
    usernameError.value = validationError
    return
  }

  isCheckingUsername.value = true
  checkUsernameTimeout = setTimeout(async () => {
    try {
      const result = await securityApi.checkUsernameAvailability(trimmed)
      isUsernameAvailable.value = result.available
      if (!result.available) {
        usernameError.value = t('profile.settings.general.username_taken')
      }
    } catch {
      // Ignore errors, validation will happen on submit
    } finally {
      isCheckingUsername.value = false
    }
  }, 500)
})

async function saveUsername() {
  usernameError.value = ''

  const trimmed = newUsername.value?.trim()
  const validationError = validateUsername(trimmed)
  if (validationError) {
    usernameError.value = validationError
    return
  }

  if (trimmed === userSettingsStore.userProfile?.username) {
    usernameError.value = t('profile.settings.general.username_unchanged')
    return
  }

  try {
    await userSettingsStore.changeUsername(newUsername.value.trim())
    isEditingUsername.value = false
    newUsername.value = ''
    toast.add({
      severity: 'success',
      summary: t('profile.settings.general.username_saved'),
      detail: t('profile.settings.general.username_saved_detail'),
      life: 5000
    })
  } catch (error) {
    usernameError.value = error.message || t('profile.settings.general.error')
  }
}

const isDarkModeEnabled = computed({
  get: () => isDarkMode.value,
  set: (value) => setDarkMode(value)
})

const profilePictureUrl = computed(() => {
  if (userSettingsStore.userProfile?.profile_picture?.small) {
    return userSettingsStore.userProfile?.profile_picture?.small
  }
  return null
})

function triggerFileInput() {
  fileInput.value?.click()
}

function handleFileSelect(event) {
  const file = event.target.files?.[0]
  if (file) {
    const reader = new FileReader()
    reader.onload = (e) => {
      selectedImage.value = e.target.result
      showPictureModal.value = true
    }
    reader.readAsDataURL(file)
  }
  event.target.value = ''
}

function handlePictureSaved() {
  toast.add({
    severity: 'success',
    summary: t('profile.settings.general.picture_saved'),
    detail: t('profile.settings.general.picture_saved_detail'),
    life: 5000
  })
}

function confirmDeletePicture() {
  confirm.require({
    message: t('profile.settings.general.picture_delete_confirm_message'),
    header: t('profile.settings.general.picture_delete_confirm_header'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: t('profile.settings.general.cancel'),
    acceptLabel: t('profile.settings.general.picture_delete'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await userSettingsStore.deleteProfilePicture()
        toast.add({
          severity: 'success',
          summary: t('profile.settings.general.picture_deleted'),
          detail: t('profile.settings.general.picture_deleted_detail'),
          life: 5000
        })
      } catch {
        toast.add({
          severity: 'error',
          summary: t('profile.settings.general.error_summary'),
          detail: t('profile.settings.general.picture_delete_error'),
          life: 5000
        })
      }
    }
  })
}

// Delete account
const hasPassword = computed(() => userSettingsStore.userProfile?.has_password ?? true)
const showDeleteAccountDialog = ref(false)
const deleteAccountPassword = ref('')
const deleteAccountConfirmation = ref('')
const deleteAccountError = ref('')
const isDeletingAccount = ref(false)

function normalize(str) {
  return str
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
}

const isConfirmationValid = computed(
  () =>
    normalize(deleteAccountConfirmation.value) ===
    normalize(t('profile.settings.general.delete_account.confirm_phrase'))
)

async function handleDeleteAccount() {
  deleteAccountError.value = ''
  isDeletingAccount.value = true
  try {
    await securityApi.deleteAccount(hasPassword.value ? deleteAccountPassword.value : null)
    showDeleteAccountDialog.value = false
    await userSecurityStore.logout()
  } catch (error) {
    if (error.isValidationError) {
      deleteAccountError.value =
        error.violations?.[0]?.message ||
        t('profile.settings.general.delete_account.invalid_password')
    } else {
      deleteAccountError.value = t('profile.settings.general.error')
    }
  } finally {
    isDeletingAccount.value = false
  }
}
</script>
