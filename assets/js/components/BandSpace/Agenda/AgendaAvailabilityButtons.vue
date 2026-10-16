<template>
  <!-- Inside clickable rows: a click here answers, it must not also open the entry. -->
  <div
    class="flex items-center gap-1.5"
    role="group"
    :aria-label="t('band_space_agenda.availability.my_availability_for', { title: item.title })"
    @click.stop
    @keydown.stop
  >
    <span v-if="myAnswer === 'absent'" class="text-xs text-amber-700 dark:text-amber-300">
      {{ t('band_space_agenda.availability.absence_declared') }}
    </span>
    <Button
      v-for="choice in CHOICES"
      :key="choice.value"
      type="button"
      size="small"
      :icon="choice.icon"
      :label="t(choice.labelKey)"
      :severity="choice.severity"
      :outlined="myAnswer !== choice.value"
      :aria-pressed="myAnswer === choice.value"
      :loading="savingAnswer === choice.value"
      :disabled="savingAnswer !== null"
      class="py-0.5! px-2! text-xs!"
      @click="handleAnswer(choice.value)"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import bandSpaceAgendaApi from '../../../api/bandSpace/band-space-agenda.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  // An agenda feed item: a manual entry that asks, on a date still ahead (canAnswerFromAgenda).
  item: { type: Object, required: true }
})

const emit = defineEmits(['answered'])

const CHOICES = [
  {
    value: 'yes',
    labelKey: 'band_space_agenda.availability.quick_yes',
    icon: 'pi pi-check',
    severity: 'success'
  },
  {
    value: 'no',
    labelKey: 'band_space_agenda.availability.quick_no',
    icon: 'pi pi-times',
    severity: 'danger'
  }
]

const { t } = useI18n()
const toast = useToast()
const savingAnswer = ref(null)

// `absent` comes from a declared absence, not an answer, so neither button is pressed for it.
const myAnswer = computed(() => props.item.metadata?.my_availability ?? null)

async function handleAnswer(answer) {
  if (myAnswer.value === answer) return

  savingAnswer.value = answer
  try {
    const availability = await bandSpaceAgendaApi.answerAvailability(
      props.bandSpaceId,
      props.item.source_id,
      props.item.metadata.occurrence_date,
      answer
    )
    emit('answered', {
      entryId: props.item.source_id,
      occurrenceDate: props.item.metadata.occurrence_date,
      availability
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('band_space_agenda.availability.answer_failed'),
      detail: error?.message ?? t('band_space_agenda.availability.retry_soon'),
      life: 5000
    })
  } finally {
    savingAnswer.value = null
  }
}
</script>
