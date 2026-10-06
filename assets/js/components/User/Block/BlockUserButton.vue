<template>
  <span v-if="isVisible" class="inline-flex">
    <Button
      :icon="blocked ? 'pi pi-unlock' : 'pi pi-ban'"
      :label="iconOnly ? undefined : blocked ? 'Débloquer' : 'Bloquer'"
      text
      :rounded="iconOnly"
      size="small"
      severity="secondary"
      :loading="isWorking"
      :aria-label="blocked ? `Débloquer ${username}` : `Bloquer ${username}`"
      v-tooltip.top="iconOnly ? (blocked ? 'Débloquer' : 'Bloquer') : undefined"
      @click="handleClick"
    />
  </span>
</template>

<script setup>
import Button from 'primevue/button'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
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
      `${props.username} est débloqué.`,
      'unblocked'
    )
    return
  }
  confirm.require({
    header: `Bloquer ${props.username} ?`,
    message: [
      'Vous ne pourrez plus échanger de messages, votre conversation disparaîtra de votre messagerie et vous ne vous verrez plus dans les recherches ni dans les annonces.',
      "Cette personne n'est pas prévenue. Si vous partagez un Band Space, rien n'y change : seul son administrateur peut retirer un membre.",
      'Vous pourrez la débloquer à tout moment depuis vos paramètres, rubrique « Confidentialité ».'
    ].join('\n\n'),
    icon: 'pi pi-ban',
    rejectLabel: 'Annuler',
    acceptLabel: 'Bloquer',
    acceptProps: { severity: 'danger' },
    accept: () =>
      run(() => userBlockStore.blockUser(props.userId), `${props.username} est bloqué.`, 'blocked')
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
      summary: 'Action impossible',
      detail: e?.response?.data?.detail || 'Une erreur est survenue.',
      life: 4000
    })
  } finally {
    isWorking.value = false
  }
}
</script>
