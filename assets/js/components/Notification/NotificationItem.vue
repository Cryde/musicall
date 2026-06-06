<template>
  <div
    class="flex gap-3 px-2 py-3 rounded-md cursor-pointer transition-colors hover:bg-surface-50 dark:hover:bg-surface-800/50"
    @click="handleBodyClick"
  >
    <div
      class="flex items-center justify-center w-10 h-10 rounded-full shrink-0"
      :class="config.avatarClass"
    >
      <i :class="config.icon" aria-hidden="true" />
    </div>

    <div class="flex-1 min-w-0">
      <div class="flex items-center justify-between gap-2">
        <span class="text-sm truncate" :class="isUnread ? 'font-bold' : 'font-medium'">
          {{ config.title }}
        </span>
        <div class="flex items-center gap-2 shrink-0">
          <span class="text-xs text-surface-400 whitespace-nowrap">
            {{ relativeDate(notification.creation_datetime) }}
          </span>
          <span v-if="isUnread" class="w-2 h-2 rounded-full bg-indigo-500" aria-hidden="true" />
        </div>
      </div>

      <p v-if="config.preview" class="text-sm text-surface-500 dark:text-surface-400 line-clamp-2">
        {{ config.preview }}
      </p>

      <div
        v-if="staleMessage"
        class="mt-2 flex items-start gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400"
      >
        <i class="pi pi-exclamation-triangle mt-0.5" aria-hidden="true" />
        <span>{{ staleMessage }}</span>
      </div>

      <div v-else-if="isActionable" class="flex gap-2 mt-2" @click.stop>
        <Button label="Accepter" size="small" :loading="isProcessing" @click="handleAccept" />
        <Button
          label="Décliner"
          size="small"
          severity="secondary"
          outlined
          :disabled="isProcessing"
          @click="handleDecline"
        />
      </div>

      <div
        v-else-if="resolvedLabel"
        class="mt-2 flex items-center gap-1.5 text-xs text-surface-500 dark:text-surface-400"
      >
        <i class="pi pi-check-circle text-green-500" aria-hidden="true" />
        <span>{{ resolvedLabel }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import bandSpaceSettingsApi from '../../api/bandSpace/band-space-settings.js'
import { BAND_SPACE_ROUTES } from '../../constants/bandSpace.js'
import relativeDate from '../../helper/date/relative-date.js'
import { useUserNotificationStore } from '../../store/notification/userNotification.js'

const props = defineProps({
  notification: { type: Object, required: true }
})

const emit = defineEmits(['navigate'])

const router = useRouter()
const toast = useToast()
const store = useUserNotificationStore()

const isProcessing = ref(false)
const staleMessage = ref(null)

const isUnread = computed(() => props.notification.read_datetime === null)

// Publication and course comments deep-link to the same page by slug; is_course picks the route.
function publicationTarget(payload) {
  const name = payload.is_course ? 'app_course_show' : 'app_publication_show'
  return { name, params: { slug: payload.publication_slug } }
}

// type -> row rendering. Each future producer adds its branch here.
const TYPE_CONFIG = {
  band_space_invitation: (payload) => ({
    icon: 'pi pi-users',
    avatarClass: 'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300',
    title: payload.invited_by_username,
    preview: `vous a invité à rejoindre ${payload.band_space_name}`,
    actions: 'invitation',
    target: null
  }),
  band_space_task_assignment: (payload) => ({
    icon: 'pi pi-check-square',
    avatarClass: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
    title: payload.actor_username,
    preview: `vous a assigné à la tâche « ${payload.task_title} »`,
    actions: null,
    target: { name: BAND_SPACE_ROUTES.TASKS, params: { id: payload.band_space_id } }
  }),
  forum_topic_reply: (payload) => ({
    icon: 'pi pi-comments',
    avatarClass: 'bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300',
    title: payload.actor_username,
    preview: `a répondu à « ${payload.topic_title} »`,
    actions: null,
    target: { name: 'forum_topic_item', params: { slug: payload.topic_slug } }
  }),
  publication_comment: (payload) => ({
    icon: 'pi pi-comment',
    avatarClass: 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300',
    title: payload.actor_username,
    preview: `a commenté « ${payload.publication_title} »`,
    actions: null,
    target: publicationTarget(payload)
  }),
  comment_reply: (payload) => ({
    icon: 'pi pi-reply',
    avatarClass: 'bg-violet-100 text-violet-600 dark:bg-violet-500/20 dark:text-violet-300',
    title: payload.actor_username,
    preview: `a répondu à un commentaire sur « ${payload.publication_title} »`,
    actions: null,
    target: publicationTarget(payload)
  }),
  publication_approved: (payload) => ({
    icon: 'pi pi-check-circle',
    avatarClass: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
    title: payload.publication_title,
    preview: payload.is_course ? 'Votre cours a été publié' : 'Votre publication a été publiée',
    actions: null,
    target: publicationTarget(payload)
  }),
  publication_rejected: (payload) => ({
    icon: 'pi pi-times-circle',
    avatarClass: 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300',
    title: payload.publication_title,
    preview: payload.is_course
      ? "Votre cours n'a pas été accepté"
      : "Votre publication n'a pas été acceptée",
    actions: null,
    target: null
  }),
  gallery_approved: (payload) => ({
    icon: 'pi pi-check-circle',
    avatarClass: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300',
    title: payload.gallery_title,
    preview: 'Votre galerie a été publiée',
    actions: null,
    target: { name: 'app_gallery_show', params: { slug: payload.gallery_slug } }
  }),
  gallery_rejected: (payload) => ({
    icon: 'pi pi-times-circle',
    avatarClass: 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300',
    title: payload.gallery_title,
    preview: "Votre galerie n'a pas été acceptée",
    actions: null,
    target: null
  }),
  band_space_role_changed: (payload) => ({
    icon: 'pi pi-shield',
    avatarClass: 'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300',
    title: payload.actor_username,
    preview:
      payload.to === 'admin'
        ? `vous a nommé administrateur de « ${payload.band_space_name} »`
        : `vous a retiré les droits d'administrateur sur « ${payload.band_space_name} »`,
    actions: null,
    target: { name: BAND_SPACE_ROUTES.DASHBOARD, params: { id: payload.band_space_id } }
  }),
  band_space_member_removed: (payload) => ({
    icon: 'pi pi-user-minus',
    avatarClass: 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300',
    title: payload.actor_username,
    preview: `vous a retiré de « ${payload.band_space_name} »`,
    actions: null,
    target: null
  })
}

const config = computed(() => {
  const builder = TYPE_CONFIG[props.notification.type]
  if (builder) {
    return builder(props.notification.payload ?? {})
  }
  return {
    icon: 'pi pi-bell',
    avatarClass: 'bg-surface-200 text-surface-600 dark:bg-surface-700 dark:text-surface-300',
    title: 'Notification',
    preview: '',
    actions: null,
    target: null
  }
})

// Live invitation status, resolved server-side by the feed enricher (and updated
// optimistically by the store on action). Actions show only while it is pending.
const invitationStatus = computed(() => props.notification.payload?.invitation_status ?? 'pending')
const isActionable = computed(
  () => config.value.actions === 'invitation' && invitationStatus.value === 'pending'
)
const resolvedLabel = computed(() => {
  if (config.value.actions !== 'invitation' || invitationStatus.value === 'pending') {
    return null
  }
  if (invitationStatus.value === 'accepted') {
    return 'Invitation acceptée'
  }
  if (invitationStatus.value === 'declined') {
    return 'Invitation déclinée'
  }
  return 'Invitation expirée'
})

function handleBodyClick() {
  store.markRead(props.notification.id)
  if (config.value.target) {
    emit('navigate')
    router.push(config.value.target)
  }
}

async function handleAccept() {
  isProcessing.value = true
  try {
    const data = await bandSpaceSettingsApi.acceptInvitation(
      props.notification.payload.invitation_token
    )
    store.recordInvitationAction(props.notification.payload.invitation_token, 'accepted')
    await store.markRead(props.notification.id)
    toast.add({
      severity: 'success',
      summary: 'Invitation acceptée',
      detail: `Vous avez rejoint ${props.notification.payload.band_space_name}.`,
      life: 3000
    })
    emit('navigate')
    router.push({ name: BAND_SPACE_ROUTES.DASHBOARD, params: { id: data.band_space_id } })
  } catch (e) {
    handleStale()
  } finally {
    isProcessing.value = false
  }
}

async function handleDecline() {
  isProcessing.value = true
  try {
    await bandSpaceSettingsApi.declineInvitation(props.notification.payload.invitation_token)
    store.recordInvitationAction(props.notification.payload.invitation_token, 'declined')
    await store.markRead(props.notification.id)
    toast.add({ severity: 'info', summary: 'Invitation déclinée', life: 3000 })
  } catch (e) {
    handleStale()
  } finally {
    isProcessing.value = false
  }
}

// Graceful staleness (contract item 6): if the invite is no longer valid at click time
// (race vs the feed read), the endpoint errors - surface it inline, mark read, never crash.
function handleStale() {
  staleMessage.value = "Cette invitation a expiré ou n'est plus valide."
  store.markRead(props.notification.id)
}
</script>
