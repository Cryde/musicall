<template>
  <span v-if="canBlock" class="inline-flex">
    <Button
      :icon="isBlocked ? 'pi pi-unlock' : 'pi pi-ban'"
      :label="iconOnly ? undefined : isBlocked ? t('profile.block.unblock') : t('profile.block.block')"
      text
      :rounded="iconOnly"
      size="small"
      severity="secondary"
      :loading="isWorking"
      :aria-label="isBlocked ? t('profile.block.unblock_user', { username }) : t('profile.block.block_user', { username })"
      v-tooltip.top="iconOnly ? (isBlocked ? t('profile.block.unblock') : t('profile.block.block')) : undefined"
      @click="handleClick"
    />
  </span>
</template>

<script setup>
import Button from 'primevue/button'
import { useI18n } from 'vue-i18n'
import { useBlockUser } from '../../../composables/useBlockUser.js'

/** « Bloquer » / « Débloquer », offered to a signed-in member on somebody else (#1117). */
const props = defineProps({
  userId: { type: String, required: true },
  username: { type: String, required: true },
  iconOnly: { type: Boolean, default: false }
})

const emit = defineEmits(['blocked', 'unblocked'])

const { t } = useI18n()
const { canBlock, isBlocked, isWorking, toggleBlock } = useBlockUser(
  () => props.userId,
  () => props.username
)

function handleClick() {
  toggleBlock({ onBlocked: () => emit('blocked'), onUnblocked: () => emit('unblocked') })
}
</script>
