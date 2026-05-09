<template>
  <Dialog
    v-model:visible="visible"
    modal
    header="Partager le fichier"
    :style="{ width: '36rem' }"
    @hide="resetDialog"
  >
    <div class="flex flex-col gap-5">
      <div v-if="!createdShare" class="flex flex-col gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Date d'expiration</label>
          <DatePicker
            v-model="form.expiryDatetime"
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
              Protéger par mot de passe
            </label>
          </div>
          <Password
            v-if="usePassword"
            v-model="form.password"
            :feedback="false"
            toggle-mask
            placeholder="Mot de passe"
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
          Lien créé. Le lien complet ne sera plus visible après fermeture — copiez-le maintenant.
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
            label="Copier"
            severity="secondary"
            @click="copyShareUrl"
          />
        </div>
        <p v-if="createdShare.has_password" class="text-xs text-amber-800 dark:text-amber-300">
          Mot de passe requis pour ce lien.
        </p>
      </div>

      <div class="flex flex-col gap-2">
        <h4 class="text-sm font-semibold text-surface-700 dark:text-surface-200">
          Liens actifs ({{ fileShares.length }})
        </h4>

        <div v-if="filesStore.isLoadingShares && fileShares.length === 0" class="flex flex-col gap-2">
          <Skeleton v-for="i in 2" :key="i" width="100%" height="3rem" borderRadius="0.5rem" />
        </div>

        <p v-else-if="fileShares.length === 0" class="text-xs italic text-surface-400">
          Aucun lien de partage actif pour ce fichier.
        </p>

        <div
          v-for="share in fileShares"
          :key="share.id"
          class="flex items-center gap-3 p-3 rounded-lg border border-surface-200 dark:border-surface-700"
        >
          <i class="pi pi-link text-surface-400"></i>
          <div class="flex-1 min-w-0 text-xs">
            <p class="text-surface-700 dark:text-surface-200">
              Expire le {{ formatDate(share.expiry_datetime) }}
              <span v-if="share.has_password" class="ml-2 text-amber-600">
                <i class="pi pi-lock"></i> protégé
              </span>
            </p>
            <p class="text-surface-400">
              {{ share.access_count }} accès
              <template v-if="share.last_access_datetime">
                — dernier {{ formatRelative(share.last_access_datetime) }}
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
        label="Annuler"
        severity="secondary"
        text
        :disabled="filesStore.isCreatingShare"
        @click="visible = false"
      />
      <Button
        v-if="!createdShare"
        label="Créer le lien"
        icon="pi pi-share-alt"
        :loading="filesStore.isCreatingShare"
        :disabled="!form.expiryDatetime"
        @click="handleCreate"
      />
      <Button
        v-else
        label="Fermer"
        @click="visible = false"
      />
    </template>
  </Dialog>

  <ConfirmDialog />
</template>

<script setup>
import { formatDistanceToNow } from 'date-fns'
import { fr } from 'date-fns/locale'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import ConfirmDialog from 'primevue/confirmdialog'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Password from 'primevue/password'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, reactive, ref, watch } from 'vue'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  fileId: { type: String, default: null }
})

const visible = defineModel('visible', { type: Boolean, default: false })

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

watch(visible, (open) => {
  if (open) {
    initDefaults()
    if (filesStore.shares.length === 0) {
      filesStore.fetchShares(props.bandSpaceId)
    }
  }
})

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
    fieldErrors.expiryDatetime = "Veuillez choisir une date d'expiration"
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
    message: 'Révoquer ce lien de partage ? Il deviendra immédiatement inaccessible.',
    header: 'Confirmer la révocation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Révoquer',
    rejectLabel: 'Annuler',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await filesStore.revokeShare(props.bandSpaceId, share.id)
      toast.add({
        severity: 'success',
        summary: 'Lien révoqué',
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
      summary: 'Lien copié.',
      life: 2500
    })
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
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function formatRelative(iso) {
  if (!iso) return ''
  return formatDistanceToNow(new Date(iso), { addSuffix: true, locale: fr })
}
</script>
