<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="t('band_space_files.share_dialog.title')"
    :style="{ width: '36rem' }"
    @hide="resetDialog"
  >
    <div class="flex flex-col gap-5">
      <div v-if="!createdShare" class="flex flex-col gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700 dark:text-surface-200">{{ t('band_space_files.share_dialog.expiry_label') }}</label>
          <DatePicker
            v-model="form.expiryDatetime"
            :aria-label="t('band_space_files.share_dialog.expiry_label')"
            show-time
            hour-format="24"
            date-format="dd/mm/yy"
            :show-button-bar="true"
            :min-date="minDate"
            :disabled="filesStore.isCreatingShare"
            class="w-full"
          />
          <small v-if="fieldErrors.expiryDatetime" class="text-red-500">{{ fieldErrors.expiryDatetime }}</small>
        </div>

        <div class="flex flex-col gap-1">
          <div class="flex items-center gap-2">
            <Checkbox v-model="usePassword" input-id="use-password" binary :disabled="filesStore.isCreatingShare" />
            <label for="use-password" class="text-sm text-surface-700 dark:text-surface-200">
              {{ t('band_space_files.share_dialog.use_password') }}
            </label>
          </div>
          <Password
            v-if="usePassword"
            v-model="form.password"
            :feedback="false"
            toggle-mask
            :placeholder="t('band_space_files.share_dialog.password_placeholder')"
            input-class="w-full"
            class="w-full"
            :disabled="filesStore.isCreatingShare"
          />
          <small v-if="fieldErrors.password" class="text-red-500">{{ fieldErrors.password }}</small>
        </div>

        <Message v-if="globalError" severity="error" :closable="false">{{ globalError }}</Message>
      </div>

      <div
        v-if="createdShare"
        class="flex flex-col gap-2 p-3 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800"
      >
        <p class="text-sm font-medium text-amber-900 dark:text-amber-200">
          {{ t('band_space_files.share_dialog.link_created') }}
        </p>
        <div class="flex items-center gap-2">
          <InputText
            ref="shareUrlInput"
            :model-value="createdShare.share_url"
            readonly
            size="small"
            class="flex-1 font-mono text-xs"
            @focus="selectAll"
          />
          <Button
            icon="pi pi-copy"
            size="small"
            :label="t('band_space_files.share_dialog.copy')"
            severity="secondary"
            @click="copyShareUrl"
          />
        </div>
        <p v-if="createdShare.has_password" class="text-xs text-amber-800 dark:text-amber-300">
          {{ t('band_space_files.share_dialog.password_required') }}
        </p>
      </div>

      <div class="flex flex-col gap-2">
        <h4 class="text-sm font-semibold text-surface-700 dark:text-surface-200">
          {{ t('band_space_files.share_dialog.active_links', { count: fileShares.length }) }}
        </h4>

        <div v-if="filesStore.isLoadingShares && fileShares.length === 0" class="flex flex-col gap-2">
          <Skeleton v-for="i in 2" :key="i" width="100%" height="3rem" borderRadius="0.5rem" />
        </div>

        <p v-else-if="fileShares.length === 0" class="text-xs italic text-surface-400">
          {{ t('band_space_files.share_dialog.no_active_links') }}
        </p>

        <div
          v-for="share in fileShares"
          :key="share.id"
          class="flex items-center gap-3 p-3 rounded-lg border border-surface-200 dark:border-surface-700"
        >
          <i class="pi pi-link text-surface-400"></i>
          <div class="flex-1 min-w-0 text-xs">
            <p class="text-surface-700 dark:text-surface-200">
              {{ t('band_space_files.share_dialog.expires_on', { date: formatDate(share.expiry_datetime) }) }}
              <span v-if="share.has_password" class="ml-2 text-amber-600">
                <i class="pi pi-lock"></i> {{ t('band_space_files.share_dialog.protected') }}
              </span>
            </p>
            <p class="text-surface-400">
              <template v-if="share.last_access_datetime">
                {{
                  t('band_space_files.share_dialog.access_count_last', {
                    count: share.access_count,
                    when: formatRelative(share.last_access_datetime)
                  })
                }}
              </template>
              <template v-else>
                {{ t('band_space_files.share_dialog.access_count', { count: share.access_count }) }}
              </template>
            </p>
          </div>
          <Button
            icon="pi pi-trash"
            size="small"
            severity="danger"
            text
            @click="confirmRevoke(share)"
          />
        </div>
      </div>
    </div>

    <template #footer>
      <Button
        v-if="!createdShare"
        :label="t('band_space_files.common.cancel')"
        severity="secondary"
        text
        :disabled="filesStore.isCreatingShare"
        @click="visible = false"
      />
      <Button
        v-if="!createdShare"
        :label="t('band_space_files.share_dialog.create')"
        icon="pi pi-share-alt"
        :loading="filesStore.isCreatingShare"
        :disabled="!form.expiryDatetime"
        @click="handleCreate"
      />
      <Button
        v-else
        :label="t('band_space_files.common.close')"
        @click="visible = false"
      />
    </template>
  </Dialog>
</template>

<script setup>
import { formatDistanceToNow } from 'date-fns'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Password from 'primevue/password'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { dateFnsLocale, intlLocale, noValue } from '../../../i18n/formatLocale.js'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  fileId: { type: String, default: null }
})

const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const filesStore = useBandFilesStore()
const confirm = useConfirm()
const toast = useToast()

const minDate = new Date()

const form = reactive({
  expiryDatetime: null,
  password: ''
})

const usePassword = ref(false)
const createdShare = ref(null)
const fieldErrors = reactive({ expiryDatetime: null, password: null })
const globalError = ref(null)
const shareUrlInput = ref(null)

const fileShares = computed(() =>
  filesStore.shares.filter((s) => s.file_id === props.fileId && s.is_active)
)

watch(
  visible,
  (open) => {
    if (open) {
      initDefaults()
      if (filesStore.shares.length === 0) {
        filesStore.fetchShares(props.bandSpaceId)
      }
    }
  },
  { immediate: true }
)

function initDefaults() {
  const inSevenDays = new Date()
  inSevenDays.setDate(inSevenDays.getDate() + 7)
  form.expiryDatetime = inSevenDays
  form.password = ''
  usePassword.value = false
  createdShare.value = null
  fieldErrors.expiryDatetime = null
  fieldErrors.password = null
  globalError.value = null
}

async function handleCreate() {
  if (!form.expiryDatetime) {
    fieldErrors.expiryDatetime = t('band_space_files.share_dialog.expiry_required')
    return
  }
  fieldErrors.expiryDatetime = null
  fieldErrors.password = null
  globalError.value = null

  const payload = {
    expiryDatetime: form.expiryDatetime.toISOString()
  }
  if (usePassword.value && form.password.trim() !== '') {
    payload.password = form.password
  }

  try {
    const created = await filesStore.createShare(props.bandSpaceId, props.fileId, payload)
    createdShare.value = created
  } catch (e) {
    if (e.isValidationError) {
      const expiryV = e.violationsByField?.expiryDatetime?.[0]?.message
      const passwordV = e.violationsByField?.password?.[0]?.message
      if (expiryV) fieldErrors.expiryDatetime = expiryV
      if (passwordV) fieldErrors.password = passwordV
      if (!expiryV && !passwordV) globalError.value = e.message
    } else {
      globalError.value = e.message
    }
  }
}

function confirmRevoke(share) {
  confirm.require({
    message: t('band_space_files.share_dialog.revoke_confirm_message'),
    header: t('band_space_files.share_dialog.revoke_confirm_header'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('band_space_files.share_dialog.revoke'),
    rejectLabel: t('band_space_files.common.cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      await filesStore.revokeShare(props.bandSpaceId, share.id)
      toast.add({
        severity: 'success',
        summary: t('band_space_files.share_dialog.revoked'),
        life: 3000
      })
    }
  })
}

async function copyShareUrl() {
  if (!createdShare.value?.share_url) return
  try {
    await navigator.clipboard.writeText(createdShare.value.share_url)
    toast.add({
      severity: 'success',
      summary: t('band_space_files.share_dialog.copied'),
      life: 2500
    })
  } catch {
    toast.add({
      severity: 'warn',
      summary: t('band_space_files.share_dialog.copy_failed'),
      detail: t('band_space_files.share_dialog.copy_failed_detail'),
      life: 4000
    })
  }
}

function selectAll(event) {
  event.target?.select?.()
}

function resetDialog() {
  form.expiryDatetime = null
  form.password = ''
  usePassword.value = false
  createdShare.value = null
  fieldErrors.expiryDatetime = null
  fieldErrors.password = null
  globalError.value = null
}

function formatDate(iso) {
  if (!iso) return noValue()
  return new Date(iso).toLocaleDateString(intlLocale(), {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function formatRelative(iso) {
  if (!iso) return ''
  return formatDistanceToNow(new Date(iso), { addSuffix: true, locale: dateFnsLocale() })
}
</script>
