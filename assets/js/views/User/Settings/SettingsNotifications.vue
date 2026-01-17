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
          v-model="messageReceived"
          label="Messages privés"
          description="Recevez un email lorsqu'un utilisateur vous envoie un message"
          :disabled="isUpdating"
        />

        <NotificationToggle
          v-model="publicationComment"
          label="Commentaires sur mes publications"
          description="Recevez un email lorsqu'un utilisateur commente une de vos publications"
          :disabled="isUpdating"
        />

        <NotificationToggle
          v-model="forumReply"
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
          v-model="siteNews"
          label="Actualités du site"
          description="Restez informé des nouvelles fonctionnalités et annonces importantes"
          :disabled="isUpdating"
        />

        <NotificationToggle
          v-model="weeklyRecap"
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
          v-model="activityReminder"
          label="Rappels d'activité"
          description="Recevez des rappels concernant vos annonces, votre profil et votre activité"
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
          v-model="marketing"
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
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import NotificationToggle from '../../../components/settings/NotificationToggle.vue'
import { useNotificationPreferencesStore } from '../../../store/user/notificationPreferences.js'

const notificationPreferencesStore = useNotificationPreferencesStore()
const toast = useToast()

const isLoading = ref(true)
const isUpdating = ref(false)

const siteNews = ref(true)
const weeklyRecap = ref(true)
const messageReceived = ref(true)
const publicationComment = ref(true)
const forumReply = ref(true)
const marketing = ref(false)
const activityReminder = ref(true)

const originalValues = ref({
  siteNews: true,
  weeklyRecap: true,
  messageReceived: true,
  publicationComment: true,
  forumReply: true,
  marketing: false,
  activityReminder: true
})

const hasChanges = computed(() => {
  return (
    siteNews.value !== originalValues.value.siteNews ||
    weeklyRecap.value !== originalValues.value.weeklyRecap ||
    messageReceived.value !== originalValues.value.messageReceived ||
    publicationComment.value !== originalValues.value.publicationComment ||
    forumReply.value !== originalValues.value.forumReply ||
    marketing.value !== originalValues.value.marketing ||
    activityReminder.value !== originalValues.value.activityReminder
  )
})

function setFormValues(preferences) {
  siteNews.value = preferences?.site_news ?? true
  weeklyRecap.value = preferences?.weekly_recap ?? true
  messageReceived.value = preferences?.message_received ?? true
  publicationComment.value = preferences?.publication_comment ?? true
  forumReply.value = preferences?.forum_reply ?? true
  marketing.value = preferences?.marketing ?? false
  activityReminder.value = preferences?.activity_reminder ?? true

  originalValues.value = {
    siteNews: siteNews.value,
    weeklyRecap: weeklyRecap.value,
    messageReceived: messageReceived.value,
    publicationComment: publicationComment.value,
    forumReply: forumReply.value,
    marketing: marketing.value,
    activityReminder: activityReminder.value
  }
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
    await notificationPreferencesStore.updatePreferences({
      site_news: siteNews.value,
      weekly_recap: weeklyRecap.value,
      message_received: messageReceived.value,
      publication_comment: publicationComment.value,
      forum_reply: forumReply.value,
      marketing: marketing.value,
      activity_reminder: activityReminder.value
    })

    originalValues.value = {
      siteNews: siteNews.value,
      weeklyRecap: weeklyRecap.value,
      messageReceived: messageReceived.value,
      publicationComment: publicationComment.value,
      forumReply: forumReply.value,
      marketing: marketing.value,
      activityReminder: activityReminder.value
    }

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
