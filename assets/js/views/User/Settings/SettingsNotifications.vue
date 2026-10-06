<template>
  <div class="flex flex-col gap-6">
    <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0">
      Notifications
    </h2>

    <div v-if="isLoading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl"></i>
    </div>

    <template v-else>
      <!-- Communication section -->
      <div class="flex flex-col gap-2">
        <h3 class="text-lg font-medium text-surface-800 dark:text-surface-100">
          Communication
        </h3>
        <div class="border-b border-surface-200 dark:border-surface-700" />

        <NotificationToggle
          v-model="form.message_received"
          label="Messages privés"
          description="Recevez un email lorsqu'un utilisateur vous envoie un message"
          :disabled="isUpdating"
        />

        <NotificationToggle
          v-model="form.publication_comment"
          label="Commentaires sur mes publications"
          description="Recevez un email lorsqu'un utilisateur commente une de vos publications"
          :disabled="isUpdating"
        />

        <NotificationToggle
          v-model="form.forum_reply"
          label="Réponses sur le forum"
          description="Recevez un email lorsqu'un utilisateur répond à un de vos sujets sur le forum"
          :disabled="isUpdating"
        />
      </div>

      <!-- Updates section -->
      <div class="flex flex-col gap-2">
        <h3 class="text-lg font-medium text-surface-800 dark:text-surface-100">
          Mises à jour
        </h3>
        <div class="border-b border-surface-200 dark:border-surface-700" />

        <NotificationToggle
          v-model="form.site_news"
          label="Actualités du site"
          description="Restez informé des nouvelles fonctionnalités et annonces importantes"
          :disabled="isUpdating"
        />

        <NotificationToggle
          v-model="form.weekly_recap"
          label="Récapitulatif hebdomadaire"
          description="Recevez un résumé hebdomadaire de l'activité sur le site"
          :disabled="isUpdating"
        />
      </div>

      <!-- Reminders section -->
      <div class="flex flex-col gap-2">
        <h3 class="text-lg font-medium text-surface-800 dark:text-surface-100">
          Rappels
        </h3>
        <div class="border-b border-surface-200 dark:border-surface-700" />

        <NotificationToggle
          v-model="form.activity_reminder"
          label="Rappels d'activité"
          description="Recevez des rappels concernant vos annonces, votre profil et votre activité"
          :disabled="isUpdating"
        />
      </div>

      <!-- Band Space section -->
      <div class="flex flex-col gap-2">
        <h3 class="text-lg font-medium text-surface-800 dark:text-surface-100">
          Band Space
        </h3>
        <div class="border-b border-surface-200 dark:border-surface-700" />

        <NotificationToggle
          v-model="form.show_online_presence"
          label="Me montrer en ligne"
          description="Les membres de vos groupes voient quand vous avez leur discussion ouverte"
          :disabled="isUpdating"
        />
      </div>

      <!-- Mobile push section (#1110) -->
      <div class="flex flex-col gap-2">
        <h3 class="text-lg font-medium text-surface-800 dark:text-surface-100">
          Notifications mobiles
        </h3>
        <div class="border-b border-surface-200 dark:border-surface-700" />
        <p class="text-sm text-surface-600 dark:text-surface-400">
          Sur l'application MusicAll, une fois les notifications autorisées sur votre téléphone. La suppression
          programmée d'un Band Space vous est toujours signalée.
        </p>

        <NotificationToggle
          v-for="toggle in PUSH_TOGGLES"
          :key="toggle.key"
          v-model="form[toggle.key]"
          :label="toggle.label"
          :aria-label="`${toggle.label} sur le téléphone`"
          :description="toggle.description"
          :disabled="isUpdating"
        />
      </div>

      <!-- Marketing section -->
      <div class="flex flex-col gap-2">
        <h3 class="text-lg font-medium text-surface-800 dark:text-surface-100">
          Marketing
        </h3>
        <div class="border-b border-surface-200 dark:border-surface-700" />

        <NotificationToggle
          v-model="form.marketing"
          label="Offres et promotions"
          description="Recevez des informations sur les offres spéciales et promotions"
          :disabled="isUpdating"
        />
      </div>

      <!-- Save button -->
      <div class="flex justify-end pt-4 border-t border-surface-200 dark:border-surface-700">
        <Button
          label="Enregistrer"
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
    label: 'Messages privés',
    description: "Lorsqu'un utilisateur vous envoie un message"
  },
  {
    key: 'push_band_chat',
    label: 'Discussion du groupe',
    description: 'Chaque nouveau message dans la discussion de vos groupes'
  },
  {
    key: 'push_band_mention',
    label: 'Mentions',
    description: "Lorsque quelqu'un vous mentionne dans une discussion ou une tâche"
  },
  {
    key: 'push_band_tasks',
    label: 'Tâches',
    description: 'Une tâche qui vous est assignée, un commentaire sur une tâche que vous suivez'
  },
  {
    key: 'push_band_agenda',
    label: 'Agenda',
    description: "Un nouvel événement dans l'agenda de vos groupes"
  },
  {
    key: 'push_band_finance',
    label: 'Finances',
    description: 'Une dépense qui vous est attribuée'
  },
  {
    key: 'push_band_membership',
    label: 'Membres et invitations',
    description: 'Invitations, arrivées et départs, changements de rôle'
  },
  {
    key: 'push_publication_comment',
    label: 'Commentaires',
    description: 'Un commentaire sur vos publications, une réponse à vos commentaires'
  },
  {
    key: 'push_forum_reply',
    label: 'Forum',
    description: 'Une réponse à un sujet que vous suivez'
  },
  {
    key: 'push_moderation',
    label: 'Modération',
    description: 'La publication ou le refus de vos articles et galeries'
  }
]

const notificationPreferencesStore = useNotificationPreferencesStore()
const toast = useToast()

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
      summary: 'Erreur',
      detail: 'Impossible de charger les préférences de notification',
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
      summary: 'Préférences mises à jour',
      detail: 'Vos préférences de notification ont été enregistrées',
      life: 5000
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: error.message || 'Impossible de mettre à jour les préférences',
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
