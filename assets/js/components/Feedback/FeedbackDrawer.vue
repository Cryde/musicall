<template>
  <Drawer
    v-model:visible="visible"
    position="right"
    class="w-full sm:!w-[28rem]"
    :header="t('shared.feedback.title')"
    @hide="handleHide"
  >
    <div v-if="isSent" class="flex flex-col items-center text-center gap-4 py-8">
      <i class="pi pi-check-circle text-5xl text-green-600 dark:text-green-400" aria-hidden="true" />
      <p class="text-lg text-surface-700 dark:text-surface-300">{{ t('shared.feedback.thanks') }}</p>
      <Button :label="t('shared.feedback.close')" severity="secondary" outlined @click="visible = false" />
    </div>

    <form v-else class="flex flex-col gap-5" novalidate @submit.prevent="handleSubmit">
      <p class="text-surface-600 dark:text-surface-400 text-sm">
        {{ t('shared.feedback.intro') }}
      </p>

      <Message v-if="errorMessage" severity="error" :closable="false">{{ errorMessage }}</Message>

      <div class="flex flex-col gap-2">
        <label for="feedback-type" class="font-medium text-surface-700 dark:text-surface-300">
          {{ t('shared.feedback.type_label') }}
        </label>
        <Select
          id="feedback-type"
          v-model="type"
          :options="FEEDBACK_TYPE_OPTIONS"
          option-label="label"
          option-value="value"
          fluid
        />
      </div>

      <div class="flex flex-col gap-2">
        <label for="feedback-module" class="font-medium text-surface-700 dark:text-surface-300">
          {{ t('shared.feedback.module_label') }}
        </label>
        <Select
          id="feedback-module"
          v-model="module"
          :options="FEEDBACK_MODULE_GROUPS"
          option-label="label"
          option-value="value"
          option-group-label="label"
          option-group-children="items"
          fluid
        />
      </div>

      <div class="flex flex-col gap-2">
        <label for="feedback-message" class="font-medium text-surface-700 dark:text-surface-300">
          {{ t('shared.feedback.message_label') }}
        </label>
        <Textarea
          id="feedback-message"
          v-model="message"
          rows="6"
          :maxlength="MESSAGE_MAX_LENGTH"
          :invalid="submitted && !isMessageLongEnough"
          aria-describedby="feedback-message-help"
          :placeholder="t('shared.feedback.message_placeholder')"
        />
        <small id="feedback-message-help" class="text-surface-500 dark:text-surface-400">
          {{
            t('shared.feedback.message_counter', {
              length: message.trim().length,
              max: MESSAGE_MAX_LENGTH,
              min: MESSAGE_MIN_LENGTH
            })
          }}
        </small>
      </div>

      <div v-if="!userSecurityStore.isAuthenticated" class="flex flex-col gap-2">
        <i18n-t
          keypath="shared.feedback.email_label"
          tag="label"
          for="feedback-email"
          class="font-medium text-surface-700 dark:text-surface-300"
        >
          <template #optional>
            <span class="font-normal text-surface-500">{{ t('shared.feedback.optional') }}</span>
          </template>
        </i18n-t>
        <InputText id="feedback-email" v-model="email" type="email" aria-describedby="feedback-email-help" />
        <small id="feedback-email-help" class="text-surface-500 dark:text-surface-400">
          {{ t('shared.feedback.email_help') }}
        </small>
      </div>

      <p class="text-xs text-surface-500 dark:text-surface-400">
        {{ t('shared.feedback.page_sent', { page: pageUrl }) }}
      </p>

      <div class="flex justify-end gap-2">
        <Button :label="t('shared.feedback.cancel')" severity="secondary" text :disabled="feedbackStore.isSending" @click="visible = false" />
        <Button
          type="submit"
          :label="t('shared.feedback.send')"
          icon="pi pi-send"
          :loading="feedbackStore.isSending"
          :disabled="!isMessageLongEnough || feedbackStore.isSending"
        />
      </div>
    </form>
  </Drawer>
</template>

<script setup>
import Button from 'primevue/button'
import Drawer from 'primevue/drawer'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { handleApiError } from '../../api/utils/handleApiError.js'
import {
  FEEDBACK_MODULE_GROUPS,
  FEEDBACK_TYPE_OPTIONS,
  MESSAGE_MAX_LENGTH,
  MESSAGE_MIN_LENGTH
} from '../../constants/feedback.js'
import { useFeedbackStore } from '../../store/feedback.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { resolveFeedbackModule } from '../../utils/feedbackModule.js'

const { t } = useI18n()
const feedbackStore = useFeedbackStore()
const userSecurityStore = useUserSecurityStore()
const route = useRoute()

const visible = computed({
  get: () => feedbackStore.isDrawerOpen,
  set: (value) => (value ? feedbackStore.openDrawer() : feedbackStore.closeDrawer())
})

const type = ref('bug')
const module = ref('other')
const message = ref('')
const email = ref('')
const submitted = ref(false)
const isSent = ref(false)
const errorMessage = ref('')

// Read when the drawer opens rather than as a computed, so the values stay put if a background
// navigation happens while the user is typing.
const pageUrl = ref('/')
const bandSpaceId = ref(null)

const isMessageLongEnough = computed(() => message.value.trim().length >= MESSAGE_MIN_LENGTH)

watch(
  () => feedbackStore.isDrawerOpen,
  (isOpen) => {
    if (isOpen) {
      resetForm()
    }
  }
)

function resetForm() {
  type.value = 'bug'
  module.value = resolveFeedbackModule(route.name)
  message.value = ''
  email.value = ''
  submitted.value = false
  isSent.value = false
  errorMessage.value = ''
  pageUrl.value = route.fullPath
  // The live setlist view names it differently, and it is the only Band Space route outside the
  // band layout.
  bandSpaceId.value = route.params.id || route.params.bandSpaceId || null
}

function handleHide() {
  // A drawer dismissed by the mask or Escape never runs the cancel button, so the sent state has to
  // be cleared here too or reopening shows the thank-you panel.
  isSent.value = false
}

async function handleSubmit() {
  submitted.value = true
  errorMessage.value = ''
  if (!isMessageLongEnough.value) {
    return
  }

  try {
    await feedbackStore.send({
      type: type.value,
      module: module.value,
      message: message.value.trim(),
      email: email.value.trim() || null,
      pageUrl: pageUrl.value,
      bandSpaceId: bandSpaceId.value
    })
    isSent.value = true
  } catch (error) {
    if (error.response?.status === 429) {
      errorMessage.value = t('shared.feedback.too_many')
      return
    }
    errorMessage.value = handleApiError(error).message
  }
}
</script>
