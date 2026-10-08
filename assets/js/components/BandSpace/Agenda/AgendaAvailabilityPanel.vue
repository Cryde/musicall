<template>
  <section
    class="flex flex-col gap-3 rounded-md border border-surface-200 dark:border-surface-700 p-3"
    aria-labelledby="agenda-availability-title"
  >
    <div class="flex items-baseline justify-between gap-2">
      <h3 id="agenda-availability-title" class="text-sm font-semibold">Disponibilités</h3>
      <span v-if="summary" class="text-xs text-surface-600 dark:text-surface-300 text-right">
        {{ summary }}
      </span>
    </div>
    <p v-if="dateLabel" class="-mt-2 text-xs text-surface-600 dark:text-surface-300">
      Pour le {{ dateLabel }}
    </p>

    <div v-if="isLoading" class="flex justify-center py-2" role="status">
      <i class="pi pi-spin pi-spinner text-surface-500" aria-hidden="true" />
      <span class="sr-only">Chargement des disponibilités</span>
    </div>

    <Message v-else-if="loadError" severity="error" class="text-sm">{{ loadError }}</Message>

    <template v-else-if="availability">
      <Message
        v-if="actionError"
        severity="error"
        :closable="true"
        class="text-sm"
        @close="actionError = null"
      >
        {{ actionError }}
      </Message>

      <div v-if="availability.can_answer" class="flex flex-wrap gap-2" role="group" aria-label="Ma disponibilité">
        <Button
          v-for="choice in ANSWER_CHOICES"
          :key="choice.value"
          type="button"
          size="small"
          :icon="choice.icon"
          :label="choice.label"
          :severity="choice.severity"
          :outlined="availability.my_answer !== choice.value"
          :aria-pressed="availability.my_answer === choice.value"
          :loading="savingAnswer === choice.value"
          :disabled="savingAnswer !== null"
          @click="handleAnswer(choice.value)"
        />
      </div>
      <p v-else class="text-xs text-surface-600 dark:text-surface-300">
        Cette date est passée, les disponibilités ne peuvent plus changer.
      </p>

      <!-- `absent` comes from a declared absence, not from an answer: the buttons above still apply. -->
      <p
        v-if="availability.can_answer && availability.my_answer === 'absent'"
        class="text-xs text-amber-700 dark:text-amber-300"
      >
        Une indisponibilité est déclarée sur cette date. Répondre ici la remplace pour cet événement.
      </p>

      <ul class="flex flex-col gap-2">
        <li
          v-for="member in availability.members"
          :key="member.membership_id"
          class="flex items-center gap-2 text-sm"
        >
          <Avatar
            :username="member.display_name"
            :picture-url="member.profile_picture_url"
            size="sm"
          />
          <span class="flex-1 min-w-0 truncate">{{ member.display_name }}</span>
          <span
            class="text-xs font-medium px-2 py-0.5 rounded-full flex-shrink-0"
            :class="answerBadgeClass(member.answer)"
          >
            {{ availabilityAnswerLabel(member.answer) }}
          </span>
        </li>
      </ul>

      <Button
        v-if="availability.can_remind && availability.totals.pending > 0"
        type="button"
        size="small"
        icon="pi pi-bell"
        label="Relancer les membres sans réponse"
        severity="secondary"
        text
        class="self-start"
        :loading="isReminding"
        @click="handleRemind"
      />
    </template>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import bandSpaceAgendaApi from '../../../api/bandSpace/band-space-agenda.js'
import { availabilityAnswerLabel, availabilitySummary } from '../../../utils/agendaAvailability.js'
import Avatar from '../../User/Avatar.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  entryId: { type: String, required: true },
  // The item's `metadata.occurrence_date`, sent back untouched: it is the UTC day of the start.
  occurrenceDate: { type: String, required: true },
  dateLabel: { type: String, default: null }
})

const emit = defineEmits(['changed'])

const ANSWER_CHOICES = [
  { value: 'yes', label: 'Disponible', icon: 'pi pi-check', severity: 'success' },
  { value: 'no', label: 'Indisponible', icon: 'pi pi-times', severity: 'danger' }
]

const ANSWER_BADGE_CLASSES = {
  yes: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200',
  no: 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-200',
  absent: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200'
}
const PENDING_BADGE_CLASS =
  'bg-surface-100 text-surface-700 dark:bg-surface-700 dark:text-surface-200'

const toast = useToast()

const availability = ref(null)
const isLoading = ref(false)
const loadError = ref(null)
const actionError = ref(null)
const savingAnswer = ref(null)
const isReminding = ref(false)

const summary = computed(() => availabilitySummary(availability.value?.totals))

function answerBadgeClass(answer) {
  return ANSWER_BADGE_CLASSES[answer] ?? PENDING_BADGE_CLASS
}

// Opening another entry while the first one is still loading must not paint the first one's answers.
let requestId = 0

async function load() {
  const currentRequestId = ++requestId
  isLoading.value = true
  loadError.value = null
  actionError.value = null
  availability.value = null
  try {
    const data = await bandSpaceAgendaApi.getAvailability(
      props.bandSpaceId,
      props.entryId,
      props.occurrenceDate
    )
    if (currentRequestId !== requestId) return
    availability.value = data
  } catch (error) {
    if (currentRequestId !== requestId) return
    loadError.value = error?.message ?? 'Impossible de charger les disponibilités'
  } finally {
    if (currentRequestId === requestId) isLoading.value = false
  }
}

watch(() => [props.bandSpaceId, props.entryId, props.occurrenceDate], load, { immediate: true })

async function handleAnswer(answer) {
  if (availability.value?.my_answer === answer) return

  savingAnswer.value = answer
  actionError.value = null
  try {
    availability.value = await bandSpaceAgendaApi.answerAvailability(
      props.bandSpaceId,
      props.entryId,
      props.occurrenceDate,
      answer
    )
    emit('changed')
  } catch (error) {
    actionError.value = error?.message ?? 'Impossible d’enregistrer votre disponibilité'
  } finally {
    savingAnswer.value = null
  }
}

async function handleRemind() {
  isReminding.value = true
  actionError.value = null
  try {
    await bandSpaceAgendaApi.remindAvailability(
      props.bandSpaceId,
      props.entryId,
      props.occurrenceDate
    )
    toast.add({
      severity: 'success',
      summary: 'Relance envoyée',
      detail: 'Les membres sans réponse ont reçu une notification.',
      life: 4000
    })
  } catch (error) {
    actionError.value = error?.message ?? 'Impossible de relancer les membres'
  } finally {
    isReminding.value = false
  }
}
</script>
