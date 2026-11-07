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
        <Avatar
          v-if="profile.profile_picture_url"
          :image="profile.profile_picture_url"
          :pt="{ image: { alt: '' } }"
          shape="circle"
          class="w-10! h-10! shrink-0"
        />
        <h2 :id="titleId" class="text-lg font-semibold text-surface-900 dark:text-surface-0 m-0">{{ title }}</h2>
      </div>
    </template>

    <div class="flex flex-col gap-5">
      <p class="m-0 text-sm text-surface-600 dark:text-surface-400">{{ t('profile.teacher_request.intro', { name: profile.display_name }) }}</p>

      <!-- Phone: one question at a time -->
      <div v-if="isBelowMd && questions.length" class="flex flex-col gap-2">
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
        <fieldset v-if="!isBelowMd || step === index" class="border-0 p-0 m-0 min-w-0">
          <legend :ref="(el) => (legends[index] = el)" tabindex="-1" class="text-sm font-semibold text-surface-900 dark:text-surface-0 mb-2 p-0 outline-none">
            {{ t(question.labelKey) }}
          </legend>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="option in question.options"
              :key="option.value"
              type="button"
              :aria-pressed="answers[question.key] === option.value"
              class="inline-flex items-center gap-1.5 min-h-10 px-3.5 rounded-lg border text-sm font-semibold cursor-pointer transition-colors"
              :class="
                answers[question.key] === option.value
                  ? 'border-primary bg-primary-50 dark:bg-primary-400/15 text-primary-700 dark:text-primary-200'
                  : 'border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 text-surface-700 dark:text-surface-200 hover:border-surface-400'
              "
              @click="choose(question.key, option.value)"
            >
              {{ optionLabel(option) }}
              <template v-if="prefilled[question.key] === option.value">
                <i class="pi pi-search text-[10px] opacity-70" aria-hidden="true" />
                <span class="sr-only">{{ t('profile.teacher_request.from_search') }}</span>
              </template>
            </button>
          </div>
        </fieldset>
      </template>

      <div v-if="!isBelowMd || step === questions.length" class="flex flex-col gap-2">
        <label for="trial-request-message" class="text-sm font-semibold text-surface-900 dark:text-surface-0">
          {{ t('profile.teacher_request.message_label') }}
        </label>
        <Textarea
          id="trial-request-message"
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
        <Message v-if="errorMessage" severity="error" :closable="false">{{ errorMessage }}</Message>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2 w-full">
        <template v-if="isBelowMd && step < questions.length">
          <Button v-if="step > 0" :label="t('profile.teacher_request.back')" severity="secondary" text @click="step--" />
          <Button v-else :label="t('profile.teacher_request.cancel')" severity="secondary" text @click="visible = false" />
          <Button :label="t('profile.teacher_request.next')" icon="pi pi-arrow-right" icon-pos="right" @click="step++" />
        </template>
        <template v-else>
          <Button
            v-if="isBelowMd && questions.length"
            :label="t('profile.teacher_request.back')"
            severity="secondary"
            text
            @click="step--"
          />
          <Button v-else :label="t('profile.teacher_request.cancel')" severity="secondary" text @click="visible = false" />
          <Button
            :label="t('profile.teacher_request.send')"
            icon="pi pi-send"
            severity="info"
            :loading="isSending"
            :disabled="!canSend"
            @click="handleSend"
          />
        </template>
      </div>
    </template>
  </Dialog>

  <AuthRequiredModal
    v-model:visible="showAuthModal"
    :message="t('profile.teacher_request.auth_required', { name: profile.display_name })"
  />
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import { computed, nextTick, reactive, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useIsBelowMd } from '../../composables/useIsPhone.js'
import { useSendMessage } from '../../composables/useSendMessage.js'
import { getDayOfWeekLabel } from '../../constants/teacherProfile.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import {
  buildRequestMessage,
  openRequestDraftStorage,
  PART_OF_DAY_LABELS,
  requestContactOrigin,
  requestQuestions,
  saveRequestDraft,
  takeRequestDraft,
  WHEN_OPTION_LABEL
} from '../../utils/trialRequest.js'
import AuthRequiredModal from '../Auth/AuthRequiredModal.vue'

/** The API refuses longer messages (MessageCreation). */
const MESSAGE_MAX_LENGTH = 5000

const props = defineProps({
  /** The public teacher profile, as the teacher page loads it. */
  profile: { type: Object, required: true },
  /** Answers known before the visitor opens the dialog, e.g. from the teacher search (#1315). */
  initialAnswers: { type: Object, default: () => ({}) }
})

const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const userSecurityStore = useUserSecurityStore()
const isBelowMd = useIsBelowMd()
const { isSending, errorMessage, send, resetError } = useSendMessage()

const answers = reactive({})
const prefilled = ref({})
const message = ref('')
const isMessageEdited = ref(false)
const step = ref(0)
const showAuthModal = ref(false)
const titleId = useId()
const legends = ref([])
const messageField = ref(null)

const questions = computed(() => requestQuestions(props.profile))
const stepCount = computed(() => questions.value.length + 1)
const title = computed(() =>
  t(
    props.profile.offers_trial
      ? 'profile.teacher_request.title_trial'
      : 'profile.teacher_request.title_first',
    {
      name: props.profile.display_name
    }
  )
)
const canSend = computed(() => message.value.trim().length > 0 && !isSending.value)

const dialogStyle = computed(() =>
  isBelowMd.value ? { width: '100vw', height: '100dvh', maxHeight: '100dvh' } : { width: '36rem' }
)
const dialogPt = computed(() => ({
  root: { class: isBelowMd.value ? '!m-0 !max-w-full !rounded-none' : '' },
  header: { class: 'pb-0 border-0' },
  content: { class: 'pt-4' }
}))

function whenLabel({ day, part }) {
  return t(WHEN_OPTION_LABEL, {
    day: getDayOfWeekLabel(day),
    part: t(PART_OF_DAY_LABELS[part])
  })
}

function optionLabel(option) {
  if (option.label) return option.label
  if (option.labelKey === WHEN_OPTION_LABEL) return whenLabel(option.params)
  return t(option.labelKey, option.params ?? {})
}

function generatedMessage() {
  return buildRequestMessage(
    answers,
    {
      name: props.profile.display_name,
      offersTrial: props.profile.offers_trial,
      questions: questions.value,
      instrumentName:
        props.profile.instruments?.length === 1
          ? props.profile.instruments[0].instrument_name
          : null,
      whenLabel
    },
    (key, params) => t(key, params ?? {})
  )
}

function choose(key, value) {
  answers[key] = answers[key] === value ? undefined : value
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
 * Starts from the answers given before signing in when there are some, otherwise from the search. Run as the
 * dialog opens rather than on its `show` event, which comes after the animation and would undo a quick first answer.
 */
function prepare() {
  resetError()
  step.value = 0
  for (const key of Object.keys(answers)) delete answers[key]

  const offered = (key, value) =>
    questions.value.some(
      (question) =>
        question.key === key && question.options.some((option) => option.value === value)
    )
  const draft = takeRequestDraft(openRequestDraftStorage(), props.profile.username)
  const start = draft?.answers ?? props.initialAnswers ?? {}
  for (const [key, value] of Object.entries(start)) {
    if (offered(key, value)) answers[key] = value
  }
  prefilled.value = draft ? {} : { ...answers }

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
  if (current < questions.value.length) legends.value[current]?.focus()
  else messageField.value?.$el?.focus()
})

function handleHide() {
  showAuthModal.value = false
}

async function handleSend() {
  if (!userSecurityStore.isAuthenticated) {
    saveRequestDraft(openRequestDraftStorage(), props.profile.username, {
      answers: { ...answers },
      message: message.value
    })
    showAuthModal.value = true
    return
  }

  const sent = await send({
    recipientId: props.profile.user_id,
    content: message.value,
    contactOrigin: requestContactOrigin(props.profile, answers)
  })
  if (sent) {
    visible.value = false
  }
}
</script>
