<template>
  <div class="flex flex-col gap-6">
    <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0">
      {{ t('profile.settings.notifications.title') }}
    </h2>

    <div v-if="isLoading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl"></i>
    </div>

    <template v-else>
      <!-- Communication section -->
      <div class="flex flex-col gap-2">
        <h3 class="text-lg font-medium text-surface-800 dark:text-surface-100">
          {{ t('profile.settings.notifications.section_communication') }}
        </h3>
        <div class="border-b border-surface-200 dark:border-surface-700" />

        <NotificationToggle
          v-model="form.message_received"
          :label="t('profile.settings.notifications.email.message_received.label')"
          :description="t('profile.settings.notifications.email.message_received.description')"
          :disabled="isUpdating"
        />

        <NotificationToggle
          v-model="form.publication_comment"
          :label="t('profile.settings.notifications.email.publication_comment.label')"
          :description="t('profile.settings.notifications.email.publication_comment.description')"
          :disabled="isUpdating"
        />

        <NotificationToggle
          v-model="form.forum_reply"
          :label="t('profile.settings.notifications.email.forum_reply.label')"
          :description="t('profile.settings.notifications.email.forum_reply.description')"
          :disabled="isUpdating"
        />
      </div>

      <!-- Updates section -->
      <div class="flex flex-col gap-2">
        <h3 class="text-lg font-medium text-surface-800 dark:text-surface-100">
          {{ t('profile.settings.notifications.section_updates') }}
        </h3>
        <div class="border-b border-surface-200 dark:border-surface-700" />

        <NotificationToggle
          v-model="form.site_news"
          :label="t('profile.settings.notifications.email.site_news.label')"
          :description="t('profile.settings.notifications.email.site_news.description')"
          :disabled="isUpdating"
        />

        <NotificationToggle
          v-model="form.weekly_recap"
          :label="t('profile.settings.notifications.email.weekly_recap.label')"
          :description="t('profile.settings.notifications.email.weekly_recap.description')"
          :disabled="isUpdating"
        />
      </div>

      <!-- Reminders section -->
      <div class="flex flex-col gap-2">
        <h3 class="text-lg font-medium text-surface-800 dark:text-surface-100">
          {{ t('profile.settings.notifications.section_reminders') }}
        </h3>
        <div class="border-b border-surface-200 dark:border-surface-700" />

        <NotificationToggle
          v-model="form.activity_reminder"
          :label="t('profile.settings.notifications.email.activity_reminder.label')"
          :description="t('profile.settings.notifications.email.activity_reminder.description')"
          :disabled="isUpdating"
        />
      </div>

      <!-- Chats section: the band chat and direct conversations (#1040, #1243, #1246) -->
      <div class="flex flex-col gap-2">
        <h3 class="text-lg font-medium text-surface-800 dark:text-surface-100">
          {{ t('profile.settings.notifications.section_chats') }}
        </h3>
        <div class="border-b border-surface-200 dark:border-surface-700" />

        <NotificationToggle
          v-model="form.show_online_presence"
          :label="t('profile.settings.notifications.email.show_online_presence.label')"
          :description="t('profile.settings.notifications.email.show_online_presence.description')"
          :disabled="isUpdating"
        />
        <NotificationToggle
          v-model="form.show_read_receipts"
          :label="t('profile.settings.notifications.email.show_read_receipts.label')"
          :description="t('profile.settings.notifications.email.show_read_receipts.description')"
          :disabled="isUpdating"
        />
      </div>

      <!-- Mobile push section (#1110) -->
      <div class="flex flex-col gap-2">
        <h3 class="text-lg font-medium text-surface-800 dark:text-surface-100">
          {{ t('profile.settings.notifications.section_push') }}
        </h3>
        <div class="border-b border-surface-200 dark:border-surface-700" />
        <p class="text-sm text-surface-600 dark:text-surface-400">
          {{ t('profile.settings.notifications.push_help') }}
        </p>

        <NotificationToggle
          v-for="toggle in PUSH_TOGGLES"
          :key="toggle.key"
          v-model="form[toggle.key]"
          :label="t(toggle.labelKey)"
          :aria-label="t('profile.settings.notifications.push_aria_label', { label: t(toggle.labelKey) })"
          :description="t(toggle.descriptionKey)"
          :disabled="isUpdating"
        />
      </div>

      <!-- Marketing section -->
      <div class="flex flex-col gap-2">
        <h3 class="text-lg font-medium text-surface-800 dark:text-surface-100">
          {{ t('profile.settings.notifications.section_marketing') }}
        </h3>
        <div class="border-b border-surface-200 dark:border-surface-700" />

        <NotificationToggle
          v-model="form.marketing"
          :label="t('profile.settings.notifications.email.marketing.label')"
          :description="t('profile.settings.notifications.email.marketing.description')"
          :disabled="isUpdating"
        />
      </div>

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
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import NotificationToggle from '../../../components/settings/NotificationToggle.vue'
import { useNotificationPreferencesStore } from '../../../store/user/notificationPreferences.js'

/** Every switch, under its API name, with the value it has when nothing was saved yet. */
const DEFAULTS = {
  site_news: true,
  weekly_recap: true,
  message_received: true,
  publication_comment: true,
  forum_reply: true,
  marketing: false,
  activity_reminder: true,
  show_online_presence: true,
  show_read_receipts: true,
  push_message_received: true,
  push_publication_comment: true,
  push_forum_reply: true,
  push_moderation: true,
  push_band_chat: true,
  push_band_mention: true,
  push_band_tasks: true,
  push_band_agenda: true,
  push_band_finance: true,
  push_band_membership: true
}

// The push categories (#1110), mirroring PushCategory on the server.
const PUSH_TOGGLES = [
  {
    key: 'push_message_received',
    labelKey: 'profile.settings.notifications.push.message_received.label',
    descriptionKey: 'profile.settings.notifications.push.message_received.description'
  },
  {
    key: 'push_band_chat',
    labelKey: 'profile.settings.notifications.push.band_chat.label',
    descriptionKey: 'profile.settings.notifications.push.band_chat.description'
  },
  {
    key: 'push_band_mention',
    labelKey: 'profile.settings.notifications.push.band_mention.label',
    descriptionKey: 'profile.settings.notifications.push.band_mention.description'
  },
  {
    key: 'push_band_tasks',
    labelKey: 'profile.settings.notifications.push.band_tasks.label',
    descriptionKey: 'profile.settings.notifications.push.band_tasks.description'
  },
  {
    key: 'push_band_agenda',
    labelKey: 'profile.settings.notifications.push.band_agenda.label',
    descriptionKey: 'profile.settings.notifications.push.band_agenda.description'
  },
  {
    key: 'push_band_finance',
    labelKey: 'profile.settings.notifications.push.band_finance.label',
    descriptionKey: 'profile.settings.notifications.push.band_finance.description'
  },
  {
    key: 'push_band_membership',
    labelKey: 'profile.settings.notifications.push.band_membership.label',
    descriptionKey: 'profile.settings.notifications.push.band_membership.description'
  },
  {
    key: 'push_publication_comment',
    labelKey: 'profile.settings.notifications.push.publication_comment.label',
    descriptionKey: 'profile.settings.notifications.push.publication_comment.description'
  },
  {
    key: 'push_forum_reply',
    labelKey: 'profile.settings.notifications.push.forum_reply.label',
    descriptionKey: 'profile.settings.notifications.push.forum_reply.description'
  },
  {
    key: 'push_moderation',
    labelKey: 'profile.settings.notifications.push.moderation.label',
    descriptionKey: 'profile.settings.notifications.push.moderation.description'
  }
]

const notificationPreferencesStore = useNotificationPreferencesStore()
const toast = useToast()
const { t } = useI18n()

const isLoading = ref(true)
const isUpdating = ref(false)

const form = reactive({ ...DEFAULTS })
const originalValues = ref({ ...DEFAULTS })

const hasChanges = computed(() =>
  Object.keys(DEFAULTS).some((key) => form[key] !== originalValues.value[key])
)

function setFormValues(preferences) {
  for (const key of Object.keys(DEFAULTS)) {
    form[key] = preferences?.[key] ?? DEFAULTS[key]
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
