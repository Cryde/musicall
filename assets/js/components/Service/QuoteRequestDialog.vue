<template>
  <Dialog
    v-model:visible="visible"
    modal
    :aria-labelledby="titleId"
    :style="dialogStyle"
    :pt="dialogPt"
    @hide="handleHide"
  >
    <template #header>
      <div class="flex items-center gap-3 min-w-0">
        <MemberAvatar :page="page" size="w-10! h-10!" />
        <h2 :id="titleId" class="text-lg font-semibold text-surface-900 dark:text-surface-0 m-0">
          {{ t('profile.quote_request.title', { name: page.display_name }) }}
        </h2>
      </div>
    </template>

    <div class="flex flex-col gap-5">
      <p class="m-0 text-sm text-surface-600 dark:text-surface-400">{{ t('profile.quote_request.intro', { name: page.display_name }) }}</p>

      <!-- Phone: one question at a time -->
      <div v-if="isBelowMd" class="flex flex-col gap-2">
        <p class="m-0 text-sm font-medium text-surface-700 dark:text-surface-200" aria-live="polite">
          {{ t('profile.teacher_request.step', { current: step + 1, total: stepCount }) }}
        </p>
        <div class="grid gap-1" :style="{ gridTemplateColumns: `repeat(${stepCount}, minmax(0, 1fr))` }" aria-hidden="true">
          <span
            v-for="index in stepCount"
            :key="index"
            class="h-1.5 rounded-full"
            :class="index - 1 <= step ? 'bg-primary' : 'bg-surface-200 dark:bg-surface-700'"
          />
        </div>
      </div>

      <template v-for="(question, index) in questions" :key="question.key">
        <fieldset v-if="question.type === 'choice' && (!isBelowMd || step === index)" class="border-0 p-0 m-0 min-w-0">
          <legend :ref="(el) => (headings[index] = el)" tabindex="-1" class="text-sm font-semibold text-surface-900 dark:text-surface-0 mb-2 p-0 outline-none">
            {{ questionLabel(question) }}
          </legend>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="option in question.options"
              :key="option"
              type="button"
              :aria-pressed="answers[question.key] === option"
              class="inline-flex items-center min-h-10 px-3.5 rounded-lg border text-sm font-semibold cursor-pointer transition-colors"
              :class="
                answers[question.key] === option
                  ? 'border-primary bg-primary-50 dark:bg-primary-400/15 text-primary-700 dark:text-primary-200'
                  : 'border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 text-surface-700 dark:text-surface-200 hover:border-surface-400'
              "
              @click="choose(question, option)"
            >
              {{ t(OPTION_LABELS[question.key][option]) }}
            </button>
          </div>
        </fieldset>

        <div v-else-if="!isBelowMd || step === index" class="flex flex-col gap-2">
          <label
            :ref="(el) => (headings[index] = el)"
            :for="fieldId(question)"
            tabindex="-1"
            class="text-sm font-semibold text-surface-900 dark:text-surface-0 outline-none"
          >
            {{ questionLabel(question) }}
          </label>
          <InputNumber
            v-if="question.type === 'number'"
            v-model="answers[question.key]"
            :input-id="fieldId(question)"
            :min="1"
            :max="MAX_TRACKS"
            :use-grouping="false"
            class="max-w-40"
            fluid
          />
          <template v-else-if="question.type === 'link'">
            <InputText
              :id="fieldId(question)"
              v-model="answers[question.key]"
              type="url"
              inputmode="url"
              maxlength="255"
              placeholder="https://"
              :aria-describedby="`${fieldId(question)}-hint`"
              fluid
            />
            <small :id="`${fieldId(question)}-hint`" class="text-xs text-surface-500 dark:text-surface-400">{{ t('profile.quote_request.link_hint') }}</small>
          </template>
          <DatePicker
            v-else-if="question.type === 'date'"
            v-model="deadline"
            :input-id="fieldId(question)"
            :min-date="today"
            date-format="dd/mm/yy"
            show-icon
            show-button-bar
            class="w-full sm:w-56"
          />
        </div>
      </template>

      <div v-if="!isBelowMd || step === questions.length" class="flex flex-col gap-2">
        <label :for="messageId" class="text-sm font-semibold text-surface-900 dark:text-surface-0">
          {{ t('profile.teacher_request.message_label') }}
        </label>
        <Textarea
          :id="messageId"
          ref="messageField"
          v-model="message"
          :maxlength="MESSAGE_MAX_LENGTH"
          :disabled="isSending"
          rows="6"
          auto-resize
          fluid
          @input="isMessageEdited = true"
        />
        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-surface-500 dark:text-surface-400">
          <span>{{ t('profile.teacher_request.message_hint') }}</span>
          <button v-if="isMessageEdited" type="button" class="text-primary font-semibold cursor-pointer hover:underline" @click="rewriteMessage">
            {{ t('profile.teacher_request.rewrite') }}
          </button>
        </div>
        <p v-if="!hasRequired" class="m-0 text-sm text-surface-600 dark:text-surface-300">{{ t('profile.quote_request.need_required') }}</p>
        <Message v-if="errorMessage" severity="error" :closable="false">{{ errorMessage }}</Message>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2 w-full">
        <Button v-if="isBelowMd && step > 0" :label="t('profile.teacher_request.back')" severity="secondary" text @click="step--" />
        <Button v-else :label="t('profile.teacher_request.cancel')" severity="secondary" text @click="visible = false" />
        <Button
          v-if="isBelowMd && step < questions.length"
          :label="t('profile.teacher_request.next')"
          icon="pi pi-arrow-right"
          icon-pos="right"
          :disabled="!isStepAnswered"
          @click="step++"
        />
        <Button
          v-else
          :label="t('profile.teacher_request.send')"
          icon="pi pi-send"
          severity="info"
          :loading="isSending"
          :disabled="!canSend"
          @click="handleSend"
        />
      </div>
    </template>
  </Dialog>

  <AuthRequiredModal
    v-model:visible="showAuthModal"
    :message="t('profile.teacher_request.auth_required', { name: page.display_name })"
  />
</template>

<script setup>
import { format, parseISO, startOfToday } from 'date-fns'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import { computed, nextTick, reactive, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useIsBelowMd } from '../../composables/useIsPhone.js'
import { useSendMessage } from '../../composables/useSendMessage.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { formatDateLong } from '../../utils/date.js'
import {
  buildQuoteMessage,
  hasRequiredAnswers,
  MAX_TRACKS,
  OPTION_LABELS,
  QUESTION_LABELS,
  quoteAnswers,
  quoteContactOrigin,
  quoteDraftKey,
  quoteQuestions
} from '../../utils/quoteRequest.js'
import {
  openRequestDraftStorage,
  saveRequestDraft,
  takeRequestDraft
} from '../../utils/requestDraft.js'
import AuthRequiredModal from '../Auth/AuthRequiredModal.vue'
import MemberAvatar from './Page/MemberAvatar.vue'

/** The API refuses longer messages (MessageCreation). */
const MESSAGE_MAX_LENGTH = 5000

/** « Demander un devis » (#1408): a few questions that depend on the kind, then a message carrying the answers. */
const props = defineProps({
  /** The public service page: `id`, `user_id`, `username`, `display_name`, `profile_picture_url`. */
  page: { type: Object, required: true },
  /** The service kind, which picks the questions. */
  kind: { type: String, required: true }
})

const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const userSecurityStore = useUserSecurityStore()
const isBelowMd = useIsBelowMd()
const { isSending, errorMessage, send, resetError } = useSendMessage()

const answers = reactive({})
const message = ref('')
const isMessageEdited = ref(false)
const step = ref(0)
const showAuthModal = ref(false)
const titleId = useId()
const fieldPrefix = useId()
const messageId = `${fieldPrefix}-message`
const headings = ref([])
const messageField = ref(null)
const today = startOfToday()

const questions = computed(() => quoteQuestions(props.kind))
const stepCount = computed(() => questions.value.length + 1)
const draftKey = computed(() => quoteDraftKey(props.kind, props.page.username))
const hasRequired = computed(() => hasRequiredAnswers(answers, questions.value))
const canSend = computed(
  () => hasRequired.value && message.value.trim().length > 0 && !isSending.value
)
const isStepAnswered = computed(() =>
  hasRequiredAnswers(answers, questions.value.slice(step.value, step.value + 1))
)

// The picker works on a Date, the API on the calendar day: local getters, so the day picked is the day sent.
const deadline = computed({
  get: () => (answers.deadline ? parseISO(answers.deadline) : null),
  set: (value) => {
    answers.deadline = value ? format(value, 'yyyy-MM-dd') : undefined
  }
})

const dialogStyle = computed(() =>
  isBelowMd.value ? { width: '100vw', height: '100dvh', maxHeight: '100dvh' } : { width: '36rem' }
)
const dialogPt = computed(() => ({
  root: { class: isBelowMd.value ? '!m-0 !max-w-full !rounded-none' : '' },
  header: { class: 'pb-0 border-0' },
  content: { class: 'pt-4' }
}))

function fieldId(question) {
  return `${fieldPrefix}-${question.key}`
}

function questionLabel(question) {
  const label = t(QUESTION_LABELS[question.key])
  return question.required ? label : `${label} (${t('profile.quote_request.optional')})`
}

function generatedMessage() {
  return buildQuoteMessage(
    answers,
    { name: props.page.display_name, questions: questions.value, formatDeadline: formatDateLong },
    (key, params) => t(key, params ?? {})
  )
}

function choose(question, option) {
  answers[question.key] = answers[question.key] === option ? undefined : option
}

function rewriteMessage() {
  isMessageEdited.value = false
  message.value = generatedMessage()
}

// The message follows the answers until the visitor writes in it.
watch(
  () => ({ ...answers }),
  () => {
    if (!isMessageEdited.value) message.value = generatedMessage()
  },
  { deep: true }
)

/**
 * Starts from the answers given before signing in when there are some. Run as the dialog opens rather than on its
 * `show` event, which comes after the animation and would undo a quick first answer.
 */
function prepare() {
  resetError()
  step.value = 0
  for (const key of Object.keys(answers)) delete answers[key]

  const draft = takeRequestDraft(openRequestDraftStorage(), draftKey.value)
  Object.assign(answers, quoteAnswers(draft?.answers, questions.value))

  isMessageEdited.value = typeof draft?.message === 'string' && draft.message !== generatedMessage()
  message.value = isMessageEdited.value ? draft.message : generatedMessage()
}

// Immediate too: a request kept from before signing in mounts the dialog already open.
watch(
  visible,
  (isOpen) => {
    if (isOpen) prepare()
  },
  { immediate: true }
)

// Phone steps: the button that was pressed goes away, so the new step takes the focus.
watch(step, async (current, previous) => {
  if (!isBelowMd.value || previous === undefined) return
  await nextTick()
  if (current < questions.value.length) headings.value[current]?.focus()
  else messageField.value?.$el?.focus()
})

function handleHide() {
  showAuthModal.value = false
}

async function handleSend() {
  if (!userSecurityStore.isAuthenticated) {
    saveRequestDraft(openRequestDraftStorage(), draftKey.value, {
      answers: { ...answers },
      message: message.value
    })
    showAuthModal.value = true
    return
  }

  const sent = await send({
    recipientId: props.page.user_id,
    content: message.value,
    contactOrigin: quoteContactOrigin(props.page.id, answers, questions.value)
  })
  if (sent) {
    visible.value = false
  }
}
</script>
