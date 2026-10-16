<template>
  <span v-if="isVisible" class="inline-flex">
    <Button
      :icon="blocked ? 'pi pi-unlock' : 'pi pi-ban'"
      :label="iconOnly ? undefined : blocked ? t('profile.block.unblock') : t('profile.block.block')"
      text
      :rounded="iconOnly"
      size="small"
      severity="secondary"
      :loading="isWorking"
      :aria-label="blocked ? t('profile.block.unblock_user', { username }) : t('profile.block.block_user', { username })"
      v-tooltip.top="iconOnly ? (blocked ? t('profile.block.unblock') : t('profile.block.block')) : undefined"
      @click="handleClick"
    />
  </span>
</template>

<script setup>
import Button from 'primevue/button'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserBlockStore } from '../../../store/user/block.js'
import { useUserSecurityStore } from '../../../store/user/security.js'

/** « Bloquer » / « Débloquer », offered to a signed-in member on somebody else (#1117). */
const props = defineProps({
  userId: { type: String, required: true },
  username: { type: String, required: true },
  iconOnly: { type: Boolean, default: false }
})

const emit = defineEmits(['blocked', 'unblocked'])

const userSecurityStore = useUserSecurityStore()
const userBlockStore = useUserBlockStore()
const confirm = useConfirm()
const toast = useToast()
const { t } = useI18n()
const isWorking = ref(false)

const isVisible = computed(
  () =>
    userSecurityStore.isAuthenticated &&
    userBlockStore.isLoaded &&
    userSecurityStore.userProfile?.id &&
    userSecurityStore.userProfile.id !== props.userId
)
const blocked = computed(() => userBlockStore.isBlocked(props.userId))

onMounted(() => {
  if (userSecurityStore.isAuthenticated) {
    userBlockStore.loadBlocks().catch(() => {})
  }
})

function handleClick() {
  if (blocked.value) {
    run(
      () => userBlockStore.unblockUser(props.userId),
      t('profile.block.unblocked_toast', { username: props.username }),
      'unblocked'
    )
    return
  }
  confirm.require({
    header: t('profile.block.confirm_header', { username: props.username }),
    message: t('profile.block.confirm_message'),
    icon: 'pi pi-ban',
    rejectLabel: t('profile.block.cancel'),
    acceptLabel: t('profile.block.block'),
    acceptProps: { severity: 'danger' },
    accept: () =>
      run(
        () => userBlockStore.blockUser(props.userId),
        t('profile.block.blocked_toast', { username: props.username }),
        'blocked'
      )
  })
}

async function run(action, successMessage, event) {
  isWorking.value = true
  try {
    await action()
    toast.add({ severity: 'success', summary: successMessage, life: 3000 })
    emit(event)
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: t('profile.block.error_summary'),
      detail: e?.response?.data?.detail || t('profile.block.error_detail'),
      life: 4000
    })
  } finally {
    isWorking.value = false
  }
}
</script>
