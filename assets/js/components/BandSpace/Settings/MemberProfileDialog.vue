<template>
  <Dialog v-model:visible="visible" modal :header="t('band_space.member_profile.header')" :style="{ width: '30rem' }">
    <form v-if="member" class="flex flex-col gap-4" @submit.prevent="handleSave">
      <div>
        <label :for="`${uid}-stage-name`" class="block text-sm font-medium mb-1">{{ t('band_space.member_profile.stage_name') }}</label>
        <InputText
          :id="`${uid}-stage-name`"
          v-model="stageName"
          class="w-full"
          maxlength="60"
          :placeholder="member.username"
          autofocus
        />
        <small class="text-surface-600 dark:text-surface-300">
          {{ t('band_space.member_profile.stage_name_help', { username: member.username }) }}
        </small>
      </div>

      <div>
        <label :for="`${uid}-instruments`" class="block text-sm font-medium mb-1">{{ t('band_space.member_profile.instruments') }}</label>
        <MultiSelect
          :id="`${uid}-instruments`"
          v-model="instrumentIds"
          :options="instruments"
          option-label="name"
          option-value="id"
          :loading="isLoadingInstruments"
          :selection-limit="MAX_INSTRUMENTS"
          filter
          display="chip"
          class="w-full"
          :placeholder="t('band_space.member_profile.instruments_placeholder')"
          :empty-message="t('band_space.member_profile.instruments_empty')"
          :empty-filter-message="t('band_space.member_profile.instruments_not_found')"
        />
        <small class="text-surface-600 dark:text-surface-300">
          {{ t('band_space.member_profile.instruments_help', { max: MAX_INSTRUMENTS }) }}
        </small>
      </div>

      <!-- Only on one's own profile: an email is personal, so no admin can publish it (#1119). -->
      <div v-if="isSelf" class="flex items-start gap-2">
        <Checkbox v-model="showEmailOnRiders" :input-id="`${uid}-show-email`" binary />
        <div>
          <label :for="`${uid}-show-email`" class="text-sm font-medium">
            {{ t('band_space.member_profile.show_email') }}
          </label>
          <small class="block text-surface-600 dark:text-surface-300">
            {{ t('band_space.member_profile.show_email_help') }}
          </small>
        </div>
      </div>

      <Message v-if="error" severity="error" :closable="false" size="small">{{ error }}</Message>

      <div class="flex justify-end gap-2">
        <Button :label="t('band_space.common.cancel')" severity="secondary" text type="button" @click="visible = false" />
        <Button :label="t('band_space.general.save')" type="submit" :loading="isSaving" />
      </div>
    </form>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import { ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import instrumentApi from '../../../api/attribute/instrument.js'
import { useBandSpaceSettingsStore } from '../../../store/bandSpace/bandSpaceSettings.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  member: { type: Object, default: null },
  // Whether the member edited is the viewer: only they choose to show their own email
  isSelf: { type: Boolean, default: false }
})

const emit = defineEmits(['saved'])
const visible = defineModel('visible', { type: Boolean, default: false })

/** Mirrors the Assert\Count on BandSpaceMemberProfile. */
const MAX_INSTRUMENTS = 6

const settingsStore = useBandSpaceSettingsStore()
const { t } = useI18n()

const stageName = ref('')
const instrumentIds = ref([])
const showEmailOnRiders = ref(false)
const instruments = ref([])
const isLoadingInstruments = ref(false)
const isSaving = ref(false)
const error = ref(null)

// Fetched once and kept, because the catalogue is the same for every member and the dialog is
// opened repeatedly while filling in a line-up.
async function loadInstruments() {
  if (instruments.value.length > 0) return
  isLoadingInstruments.value = true
  try {
    instruments.value = await instrumentApi.listInstrument()
  } catch (e) {
    error.value = e.message
  } finally {
    isLoadingInstruments.value = false
  }
}

// Reseeded from the member each time the dialog opens, so editing one person and then another
// never carries the first one's values across.
watch(visible, (open) => {
  if (!open) return
  error.value = null
  stageName.value = props.member?.stage_name ?? ''
  instrumentIds.value = (props.member?.instruments ?? []).map((instrument) => instrument.id)
  showEmailOnRiders.value = props.member?.show_email_on_riders ?? false
  loadInstruments()
})

const uid = useId()

async function handleSave() {
  if (!props.member) return

  error.value = null
  isSaving.value = true
  try {
    await settingsStore.updateMemberProfile(props.bandSpaceId, props.member.id, {
      stageName: stageName.value.trim() === '' ? null : stageName.value.trim(),
      instrumentIds: instrumentIds.value,
      // Left out for anyone else: the server refuses it from an admin editing a member.
      showEmailOnRiders: props.isSelf ? showEmailOnRiders.value : undefined
    })
    emit('saved')
    visible.value = false
  } catch (e) {
    error.value = e.message
  } finally {
    isSaving.value = false
  }
}
</script>
