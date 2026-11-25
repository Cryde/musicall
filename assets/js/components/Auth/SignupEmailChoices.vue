<template>
  <button
    type="button"
    class="w-full flex items-center gap-3 rounded-xl border border-surface-200 dark:border-surface-700 px-4 py-3 text-left hover:bg-surface-50 dark:hover:bg-surface-800 focus-visible:outline-2 focus-visible:outline-primary-500 cursor-pointer"
    aria-haspopup="dialog"
    @click="openDialog"
  >
    <i class="pi pi-envelope text-surface-500 dark:text-surface-400" aria-hidden="true" />
    <span class="flex-1 min-w-0 flex flex-col">
      <span class="text-sm text-surface-500 dark:text-surface-400">{{ t('auth.register.emails.box_label') }}</span>
      <span class="font-medium text-surface-900 dark:text-surface-0">{{ summaryText }}</span>
    </span>
    <span class="text-sm font-medium text-primary">{{ t('auth.register.emails.change') }}</span>
  </button>

  <Dialog
    v-model:visible="isDialogOpen"
    modal
    :header="t('auth.register.emails.title')"
    :style="{ width: '36rem' }"
    :breakpoints="{ '575px': '95vw' }"
  >
    <div class="flex flex-col gap-6">
      <div class="flex justify-end">
        <Button
          :label="allChecked ? t('auth.register.emails.uncheck_all') : t('auth.register.emails.check_all')"
          size="small"
          text
          @click="choices = withEveryEmail(choices, !allChecked)"
        />
      </div>

      <section v-for="group in SIGNUP_EMAIL_GROUPS" :key="group.titleKey" class="flex flex-col">
        <h3 class="pb-2 border-b border-surface-200 dark:border-surface-700 font-medium text-surface-800 dark:text-surface-100">
          {{ t(group.titleKey) }}
        </h3>
        <div
          v-for="row in group.rows"
          :key="row.setting"
          class="flex items-start gap-3 py-3 border-b border-surface-100 dark:border-surface-800 last:border-b-0"
        >
          <Checkbox
            :model-value="choices[row.setting]"
            binary
            :input-id="`signup-email-${row.setting}`"
            class="mt-0.5"
            @update:model-value="(checked) => (choices = { ...choices, [row.setting]: checked })"
          />
          <div class="min-w-0">
            <label :for="`signup-email-${row.setting}`" class="font-medium text-surface-700 dark:text-surface-200 cursor-pointer">
              {{ t(row.labelKey) }}
            </label>
            <p class="text-sm text-surface-500 dark:text-surface-400 mt-0.5">
              {{ t(row.descriptionKey) }}
            </p>
          </div>
        </div>
      </section>

      <p class="text-sm text-surface-500 dark:text-surface-400">
        {{ t('auth.register.emails.settings_hint') }}
      </p>
    </div>

    <!-- In the footer, which never scrolls away: the last box unchecked can be anywhere in the list -->
    <template #footer>
      <div class="flex flex-col gap-3 w-full">
        <Message v-if="summary.checked === 0" severity="warn" :closable="false">
          {{ t('auth.register.emails.none_warning') }}
        </Message>
        <Button :label="t('auth.register.emails.done')" class="self-end" @click="isDialogOpen = false" />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { SIGNUP_EMAIL_GROUPS } from '../../constants/signupEmailChoices.js'
import { emailChoicesSummary, withEveryEmail } from '../../utils/signupEmailChoices.js'

const SUMMARY_KEYS = {
  default: 'auth.register.emails.summary_default',
  all: 'auth.register.emails.summary_all',
  none: 'auth.register.emails.summary_none',
  some: 'auth.register.emails.summary_some'
}

const choices = defineModel({ type: Object, required: true })
const { t } = useI18n()
const isDialogOpen = ref(false)

const summary = computed(() => emailChoicesSummary(choices.value))
const allChecked = computed(() => summary.value.checked === summary.value.total)
const summaryText = computed(() => {
  const { kind, checked, total } = summary.value

  return t(SUMMARY_KEYS[kind], { checked, total }, checked)
})

function openDialog() {
  isDialogOpen.value = true
  trackUmamiEvent('register-emails-modal-opened')
}
</script>
