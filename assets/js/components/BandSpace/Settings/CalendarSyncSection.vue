<template>
  <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-6">
    <h2 class="text-lg font-semibold text-surface-800 dark:text-surface-100 mb-2">
      Synchronisation agenda
    </h2>
    <p class="text-sm text-surface-600 dark:text-surface-300 mb-4">
      Recevez les dates du groupe directement dans Google Agenda, Apple Calendrier ou Outlook. Le
      lien est personnel : il vous appartient et ne concerne que ce Band Space.
    </p>

    <Message severity="secondary" :closable="false" class="mb-4">
      <ul class="list-disc pl-4 text-sm space-y-1">
        <li>
          L'agenda du groupe apparaît comme un <strong>calendrier séparé</strong>, en lecture seule,
          avec sa propre couleur et sa propre case pour l'afficher ou le masquer.
        </li>
        <li>
          Seuls les <strong>événements de l'agenda</strong> sont publiés. Les tâches, les finances
          et les absences restent dans l'application.
        </li>
        <li>
          Google actualise ce type de calendrier <strong>toutes les 8 à 24 heures</strong> et ne
          propose aucun bouton pour forcer la mise à jour. Une date ajoutée aujourd'hui peut donc
          n'apparaître que demain sur votre téléphone.
        </li>
      </ul>
    </Message>

    <div v-if="isLoading" class="flex flex-col gap-2">
      <Skeleton width="100%" height="3rem" borderRadius="0.5rem" />
      <Skeleton width="60%" height="2rem" borderRadius="0.5rem" />
    </div>

    <template v-else>
      <!-- Shown once, right after generating: the address only exists in that response. -->
      <div
        v-if="generatedUrl"
        class="mb-4 rounded-xl border border-primary-200 dark:border-primary-800 bg-primary-50 dark:bg-primary-950/40 p-4"
      >
        <p class="text-sm font-medium text-surface-800 dark:text-surface-100 mb-2">
          Votre lien d'abonnement
        </p>
        <div class="flex flex-col sm:flex-row gap-2 mb-3">
          <InputText
            :model-value="generatedUrl"
            readonly
            class="flex-1 font-mono text-xs"
            aria-label="Lien d'abonnement à l'agenda"
            @focus="selectAll"
          />
          <Button
            :icon="hasCopied ? 'pi pi-check' : 'pi pi-copy'"
            :label="hasCopied ? 'Copié' : 'Copier'"
            size="small"
            @click="copyUrl"
          />
        </div>

        <div class="flex flex-wrap gap-2 mb-3">
          <Button
            label="Ajouter à Google Agenda"
            icon="pi pi-google"
            size="small"
            severity="secondary"
            outlined
            :as="'a'"
            :href="googleUrl"
            target="_blank"
            rel="noopener"
          />
          <Button
            label="Apple Calendrier ou Outlook"
            icon="pi pi-calendar-plus"
            size="small"
            severity="secondary"
            outlined
            :as="'a'"
            :href="webcalUrl"
          />
        </div>

        <p class="text-xs text-surface-600 dark:text-surface-400">
          Conservez ce lien : il ne pourra plus être affiché. Toute personne qui le détient peut lire
          les dates du groupe, ne le publiez pas.
        </p>
      </div>

      <div v-if="feed.is_enabled" class="flex flex-col gap-3">
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
          <div>
            <dt class="text-surface-500 dark:text-surface-400">Créé le</dt>
            <dd class="tabular-nums">{{ formatDateLong(feed.creation_datetime) }}</dd>
          </div>
          <div>
            <dt class="text-surface-500 dark:text-surface-400">Dernier accès</dt>
            <dd class="tabular-nums">
              {{ feed.last_access_datetime ? formatDateLong(feed.last_access_datetime) : 'Jamais' }}
            </dd>
          </div>
          <div>
            <dt class="text-surface-500 dark:text-surface-400">Accès</dt>
            <dd class="tabular-nums">{{ feed.access_count }}</dd>
          </div>
        </dl>

        <div class="flex flex-wrap gap-2">
          <Button
            label="Régénérer le lien"
            icon="pi pi-refresh"
            size="small"
            severity="secondary"
            outlined
            :loading="isSubmitting"
            @click="confirmRegenerate"
          />
          <Button
            label="Révoquer"
            icon="pi pi-trash"
            size="small"
            severity="danger"
            text
            :loading="isSubmitting"
            @click="confirmRevoke"
          />
        </div>
      </div>

      <Button
        v-else
        label="Générer mon lien d'abonnement"
        icon="pi pi-calendar-plus"
        size="small"
        :loading="isSubmitting"
        @click="generate"
      />
    </template>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import agendaFeedApi from '../../../api/bandSpace/band-space-agenda-feed.js'
import { formatDateLong } from '../../../utils/date.js'

const route = useRoute()
const confirm = useConfirm()
const toast = useToast()

const bandSpaceId = route.params.id
const feed = ref({
  is_enabled: false,
  creation_datetime: null,
  last_access_datetime: null,
  access_count: 0
})
const generatedUrl = ref(null)
const isLoading = ref(true)
const isSubmitting = ref(false)
const hasCopied = ref(false)

/**
 * Apple Calendar and Outlook desktop open their subscribe dialog on a webcal:// link, and Google
 * accepts one inside its own cid parameter. An https:// URL in that parameter is not reliably
 * handled, which is why both buttons start from the same webcal form.
 */
const webcalUrl = computed(() =>
  generatedUrl.value ? generatedUrl.value.replace(/^https?:\/\//, 'webcal://') : null
)

const googleUrl = computed(() =>
  webcalUrl.value
    ? `https://calendar.google.com/calendar/r?cid=${encodeURIComponent(webcalUrl.value)}`
    : null
)

onMounted(loadFeed)

async function loadFeed() {
  isLoading.value = true
  try {
    feed.value = await agendaFeedApi.getFeed(bandSpaceId)
  } catch {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: "Impossible de charger l'état de la synchronisation.",
      life: 4000
    })
  } finally {
    isLoading.value = false
  }
}

async function generate() {
  isSubmitting.value = true
  try {
    const created = await agendaFeedApi.generateFeed(bandSpaceId)
    generatedUrl.value = created.feed_url
    hasCopied.value = false
    await loadFeed()
  } catch {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'La génération du lien a échoué.',
      life: 4000
    })
  } finally {
    isSubmitting.value = false
  }
}

function confirmRegenerate() {
  confirm.require({
    message:
      "Un nouveau lien sera créé et l'ancien cessera immédiatement de fonctionner. Les calendriers déjà abonnés ne se mettront plus à jour.",
    header: 'Régénérer le lien',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Régénérer',
    rejectLabel: 'Annuler',
    accept: generate
  })
}

function confirmRevoke() {
  confirm.require({
    message:
      'Le lien cessera de fonctionner et les calendriers déjà abonnés ne se mettront plus à jour.',
    header: 'Révoquer le lien',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Révoquer',
    rejectLabel: 'Annuler',
    acceptProps: { severity: 'danger' },
    accept: revoke
  })
}

async function revoke() {
  isSubmitting.value = true
  try {
    await agendaFeedApi.revokeFeed(bandSpaceId)
    generatedUrl.value = null
    await loadFeed()
    toast.add({
      severity: 'success',
      summary: 'Lien révoqué',
      detail: "Le flux d'agenda a été désactivé.",
      life: 3000
    })
  } catch {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'La révocation a échoué.',
      life: 4000
    })
  } finally {
    isSubmitting.value = false
  }
}

async function copyUrl() {
  try {
    await navigator.clipboard.writeText(generatedUrl.value)
    hasCopied.value = true
  } catch {
    toast.add({
      severity: 'warn',
      summary: 'Copie impossible',
      detail: 'Sélectionnez le lien et copiez-le manuellement.',
      life: 4000
    })
  }
}

function selectAll(event) {
  event.target.select()
}
</script>
