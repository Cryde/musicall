<template>
  <div class="flex flex-col gap-6">
    <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0">
      {{ t('profile.settings.notifications.title') }}
    </h2>

    <div v-if="isLoading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl"></i>
    </div>

    <template v-else>
      <div v-if="offersApp" class="flex flex-wrap items-center gap-x-3 gap-y-2">
        <span class="text-sm font-medium text-surface-700 dark:text-surface-200">
          {{ t('profile.settings.notifications.push_get_app') }}
        </span>
        <MobileAppBadges placement="notification-settings" />
      </div>

      <!-- One row per event, grouped by module, the channels as columns (#1419) -->
      <section
        v-for="(group, groupIndex) in NOTIFICATION_GROUPS"
        :key="group.titleKey"
        class="grid grid-cols-[1fr_3.5rem_3.5rem] gap-x-2"
      >
        <div class="col-span-3 grid grid-cols-subgrid items-end pb-2 border-b border-surface-200 dark:border-surface-700">
          <h3 class="text-lg font-medium text-surface-800 dark:text-surface-100">
            {{ t(group.titleKey) }}
          </h3>
          <span
            v-for="channel in CHANNELS"
            :key="channel.slot"
            class="flex items-center justify-center gap-0.5 text-xs font-medium text-surface-600 dark:text-surface-400"
          >
            {{ t(channel.columnKey) }}
            <!-- What the column means, once, on the first group: a tap, as a phone has no hover -->
            <template v-if="groupIndex === 0">
              <button
                type="button"
                class="inline-flex items-center justify-center rounded-full p-1 text-surface-500 hover:text-surface-800 dark:text-surface-400 dark:hover:text-surface-100 focus-visible:outline-2 focus-visible:outline-primary-500 cursor-pointer"
                :aria-label="t('profile.settings.notifications.column_help_aria_label', { column: t(channel.columnKey) })"
                aria-haspopup="dialog"
                :aria-expanded="openColumnHelp === channel.slot"
                @click="(event) => columnHelpPopovers[channel.slot].toggle(event)"
              >
                <i class="pi pi-info-circle text-xs" aria-hidden="true" />
              </button>
              <!-- PrimeVue pins a popover that would overflow against the screen edge: keep the gutter on a phone -->
              <Popover
                :ref="(popover) => (columnHelpPopovers[channel.slot] = popover)"
                :pt="{ root: { class: 'max-md:ms-4' } }"
                @show="openColumnHelp = channel.slot"
                @hide="openColumnHelp = null"
              >
                <p class="m-0 max-w-xs text-sm text-surface-700 dark:text-surface-200">
                  {{ t(channel.helpKey) }}
                </p>
              </Popover>
            </template>
          </span>
        </div>

        <div
          v-for="row in group.rows"
          :key="row.labelKey"
          class="col-span-3 grid grid-cols-subgrid items-center py-3 border-b border-surface-100 dark:border-surface-800 last:border-b-0"
        >
          <div class="min-w-0">
            <div class="font-medium text-surface-700 dark:text-surface-200">
              {{ t(row.labelKey) }}
            </div>
            <p class="text-sm text-surface-500 dark:text-surface-400 mt-0.5">
              {{ t(row.descriptionKey) }}
            </p>
          </div>

          <label v-if="row.bell" class="col-span-2 flex items-center justify-center gap-2 text-sm text-surface-600 dark:text-surface-300">
            <Checkbox
              v-model="form[row.bell]"
              binary
              :aria-label="t('profile.settings.notifications.bell_aria_label', { label: t(row.labelKey) })"
              :disabled="isUpdating"
            />
            <span aria-hidden="true">{{ t('profile.settings.notifications.bell') }}</span>
          </label>

          <template v-else>
            <div v-for="channel in CHANNELS" :key="channel.slot" class="flex justify-center">
              <Checkbox
                v-if="row[channel.slot]"
                v-model="form[row[channel.slot]]"
                binary
                :aria-label="t(channel.ariaKey, { label: t(row.labelKey) })"
                :disabled="isUpdating"
              />
              <span v-else aria-hidden="true" class="text-surface-400 dark:text-surface-500">-</span>
            </div>
          </template>
        </div>
      </section>

      <!-- The chat privacy switches, saved with the notifications but not about them -->
      <section class="flex flex-col">
        <h3 class="pb-2 border-b border-surface-200 dark:border-surface-700 text-lg font-medium text-surface-800 dark:text-surface-100">
          {{ t('profile.settings.notifications.groups.privacy') }}
        </h3>
        <div
          v-for="row in PRIVACY_SETTINGS"
          :key="row.setting"
          class="flex items-center justify-between gap-4 py-3 border-b border-surface-100 dark:border-surface-800 last:border-b-0"
        >
          <div class="min-w-0">
            <div class="font-medium text-surface-700 dark:text-surface-200">
              {{ t(row.labelKey) }}
            </div>
            <p class="text-sm text-surface-500 dark:text-surface-400 mt-0.5">
              {{ t(row.descriptionKey) }}
            </p>
          </div>
          <ToggleSwitch v-model="form[row.setting]" :aria-label="t(row.labelKey)" :disabled="isUpdating" />
        </div>
      </section>

      <!-- Save button -->
      <div class="flex justify-end pt-4 border-t border-surface-200 dark:border-surface-700">
        <Button
          :label="t('profile.settings.notifications.save')"
          icon="pi pi-check"
          :loading="isUpdating"
          :disabled="!hasChanges"
          @click="savePreferences"
        />
      </div>
    </template>
  </div>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Popover from 'primevue/popover'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import MobileAppBadges from '../../../components/MobileApp/MobileAppBadges.vue'
import {
  CHANNELS,
  NOTIFICATION_DEFAULTS,
  NOTIFICATION_GROUPS,
  PRIVACY_SETTINGS
} from '../../../constants/notificationSettings.js'
import { useNotificationPreferencesStore } from '../../../store/user/notificationPreferences.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { offersMobileApp } from '../../../utils/mobileApp.js'

const notificationPreferencesStore = useNotificationPreferencesStore()
const toast = useToast()
const { t } = useI18n()
const userSecurityStore = useUserSecurityStore()
const offersApp = computed(() => offersMobileApp(userSecurityStore.userProfile))

const isLoading = ref(true)
const isUpdating = ref(false)
// The column explanations, by channel slot, and the one open (for aria-expanded).
const columnHelpPopovers = {}
const openColumnHelp = ref(null)

const form = reactive({ ...NOTIFICATION_DEFAULTS })
const originalValues = ref({ ...NOTIFICATION_DEFAULTS })

const hasChanges = computed(() =>
  Object.keys(NOTIFICATION_DEFAULTS).some((key) => form[key] !== originalValues.value[key])
)

function setFormValues(preferences) {
  for (const key of Object.keys(NOTIFICATION_DEFAULTS)) {
    form[key] = preferences?.[key] ?? NOTIFICATION_DEFAULTS[key]
  }
  originalValues.value = { ...form }
}

watch(
  () => notificationPreferencesStore.preferences,
  (preferences) => {
    if (preferences) {
      setFormValues(preferences)
    }
  }
)

async function loadData() {
  isLoading.value = true

  try {
    await notificationPreferencesStore.loadPreferences()
    setFormValues(notificationPreferencesStore.preferences)
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('profile.settings.notifications.error'),
      detail: t('profile.settings.notifications.load_error'),
      life: 5000
    })
  } finally {
    isLoading.value = false
  }
}

async function savePreferences() {
  isUpdating.value = true

  try {
    await notificationPreferencesStore.updatePreferences({ ...form })
    originalValues.value = { ...form }

    trackUmamiEvent('settings-notification-save')
    toast.add({
      severity: 'success',
      summary: t('profile.settings.notifications.saved'),
      detail: t('profile.settings.notifications.saved_detail'),
      life: 5000
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('profile.settings.notifications.error'),
      detail: error.message || t('profile.settings.notifications.save_error'),
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
