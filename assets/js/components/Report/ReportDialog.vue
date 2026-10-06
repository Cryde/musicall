<template>
  <Dialog
    v-model:visible="visible"
    modal
    header="Signaler"
    :style="{ width: '32rem' }"
    :breakpoints="{ '575px': '95vw' }"
    @hide="reset"
  >
    <form class="flex flex-col gap-5" @submit.prevent="handleSubmit">
      <p class="text-sm text-surface-600 dark:text-surface-300">
        Votre signalement est transmis à la modération. La personne concernée ne saura pas qui l'a
        envoyé.
      </p>

      <fieldset class="flex flex-col gap-3">
        <legend class="text-sm font-medium text-surface-700 dark:text-surface-200 mb-2">Motif</legend>
        <label
          v-for="option in REPORT_REASON_OPTIONS"
          :key="option.value"
          class="flex items-center gap-3 cursor-pointer"
        >
          <RadioButton v-model="reason" :value="option.value" name="report-reason" :disabled="isSubmitting" />
          <span class="text-sm">{{ option.label }}</span>
        </label>
        <small v-if="fieldErrors.reason" class="text-red-600 dark:text-red-400">{{ fieldErrors.reason }}</small>
      </fieldset>

      <div class="flex flex-col gap-2">
        <label for="report-details" class="text-sm font-medium text-surface-700 dark:text-surface-200">
          Précisions (facultatif)
        </label>
        <Textarea
          id="report-details"
          v-model="details"
          rows="4"
          fluid
          :disabled="isSubmitting"
          :invalid="isTooLong || !!fieldErrors.details"
          aria-describedby="report-details-counter"
        />
        <div class="flex justify-between gap-2 text-xs">
          <small v-if="fieldErrors.details" class="text-red-600 dark:text-red-400">{{ fieldErrors.details }}</small>
          <span
            id="report-details-counter"
            class="ml-auto"
            :class="isTooLong ? 'text-red-600 dark:text-red-400' : 'text-surface-500 dark:text-surface-400'"
          >
            {{ details.length }} / {{ REPORT_DETAILS_MAX_LENGTH }}
          </span>
        </div>
      </div>

      <Message v-if="errorMessage" severity="error" :closable="false">{{ errorMessage }}</Message>

      <div class="flex justify-end gap-2">
        <Button label="Annuler" severity="secondary" text :disabled="isSubmitting" @click="visible = false" />
        <Button
          type="submit"
          label="Envoyer le signalement"
          icon="pi pi-flag"
          severity="danger"
          :loading="isSubmitting"
          :disabled="!canSubmit"
        />
      </div>
    </form>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import RadioButton from 'primevue/radiobutton'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import reportApi from '../../api/report/report.js'
import { handleApiError } from '../../api/utils/handleApiError.js'
import { REPORT_DETAILS_MAX_LENGTH, REPORT_REASON_OPTIONS } from '../../utils/reportTarget.js'

const HTTP_TOO_MANY_REQUESTS = 429

const props = defineProps({
  targetType: { type: String, required: true },
  targetId: { type: String, default: null }
})

const visible = defineModel('visible', { type: Boolean, default: false })

const toast = useToast()

const reason = ref(null)
const details = ref('')
const isSubmitting = ref(false)
const errorMessage = ref('')
const fieldErrors = ref({})

const isTooLong = computed(() => details.value.length > REPORT_DETAILS_MAX_LENGTH)
const canSubmit = computed(
  () => !!reason.value && !!props.targetId && !isTooLong.value && !isSubmitting.value
)

async function handleSubmit() {
  if (!canSubmit.value) {
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''
  fieldErrors.value = {}
  try {
    await reportApi.create({
      targetType: props.targetType,
      targetId: props.targetId,
      reason: reason.value,
      details: details.value.trim()
    })
    visible.value = false
    toast.add({
      severity: 'success',
      summary: 'Merci, votre signalement a été transmis à la modération.',
      life: 4000
    })
  } catch (e) {
    showError(e)
  } finally {
    isSubmitting.value = false
  }
}

function showError(error) {
  try {
    handleApiError(error)
  } catch (normalized) {
    if (normalized.status === HTTP_TOO_MANY_REQUESTS) {
      errorMessage.value = 'Vous avez fait trop de signalements, réessayez plus tard.'
      return
    }

    const {
      reason: reasonErrors,
      details: detailsErrors,
      ...otherErrors
    } = normalized.violationsByField
    fieldErrors.value = {
      reason: reasonErrors?.[0]?.message,
      details: detailsErrors?.[0]?.message
    }
    // Violations on fields the form does not show (the target) and plain errors land here.
    const unplaced = Object.values(otherErrors).flat()
    if (!normalized.isValidationError) {
      errorMessage.value = normalized.message
    } else if (unplaced.length > 0) {
      errorMessage.value = unplaced.map((violation) => violation.message).join('. ')
    }
  }
}

function reset() {
  reason.value = null
  details.value = ''
  errorMessage.value = ''
  fieldErrors.value = {}
}
</script>
