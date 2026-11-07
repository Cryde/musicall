<template>
  <div
    class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-xl px-4 py-2.5 text-sm bg-amber-50 text-amber-900 dark:bg-amber-500/15 dark:text-amber-200"
  >
    <span class="inline-flex items-center gap-2 font-semibold">
      <i class="pi pi-lock" aria-hidden="true" />{{ t('profile.owner.private.notice') }}
    </span>
    <Button
      :label="t('profile.owner.private.make_public')"
      icon="pi pi-eye"
      size="small"
      severity="secondary"
      :loading="isMakingPublic"
      @click="makePublic"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import profileApi from '../../../api/user/profile.js'

/**
 * The private profile's owner, on their own pages (#1317). Saved through the API, not the profile store, whose copy is
 * the page's public profile.
 */
const emit = defineEmits(['made-public'])
const { t } = useI18n()
const toast = useToast()
const isMakingPublic = ref(false)

async function makePublic() {
  isMakingPublic.value = true
  try {
    await profileApi.updateMyProfile({ is_public: true })
    emit('made-public')
  } catch {
    toast.add({
      severity: 'error',
      summary: t('profile.owner.private.make_public_error'),
      life: 5000
    })
  } finally {
    isMakingPublic.value = false
  }
}
</script>
