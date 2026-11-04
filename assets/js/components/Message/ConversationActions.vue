<template>
  <Button
    icon="pi pi-ellipsis-v"
    text
    rounded
    severity="secondary"
    :aria-label="t('message.thread.more_actions')"
    :aria-haspopup="isPhone ? 'dialog' : 'menu'"
    :aria-expanded="isOpen"
    @click="toggle"
  />

  <BottomSheet v-if="isPhone" v-model:visible="isOpen" :title="name">
    <ul class="m-0 list-none divide-y divide-surface-200 p-0 dark:divide-surface-700">
      <li>
        <SheetAction icon="pi-user" :to="profileRoute">{{ t('message.thread.view_profile') }}</SheetAction>
      </li>
      <li>
        <SheetAction icon="pi-flag" danger @click="close(openReport)">{{ reportLabel }}</SheetAction>
      </li>
      <li v-if="canBlock">
        <SheetAction :icon="isBlocked ? 'pi-unlock' : 'pi-ban'" danger :disabled="isWorking" @click="close(block)">
          {{ blockLabel }}
        </SheetAction>
      </li>
    </ul>
  </BottomSheet>
  <Menu v-else ref="menu" :model="menuItems" :pt="menuPt" popup @show="isOpen = true" @hide="isOpen = false" />

  <ReportDialog v-model:visible="isReportVisible" target-type="user" :target-id="participant.id" />
</template>

<script setup>
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import { computed, ref, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useBlockUser } from '../../composables/useBlockUser.js'
import { useIsPhone } from '../../composables/useIsPhone.js'
import BottomSheet from '../Global/BottomSheet.vue'
import SheetAction from '../Global/SheetAction.vue'
import ReportDialog from '../Report/ReportDialog.vue'

/**
 * « ⋮ » in a direct conversation's header: the other person's profile, reporting them, blocking them.
 * A sheet from the bottom on a phone, a menu elsewhere.
 */
const props = defineProps({
  // The other person: id and username, as the conversation's participant.
  participant: { type: Object, required: true },
  // What the conversation calls them.
  name: { type: String, required: true }
})

const emit = defineEmits(['blocked'])

const { t } = useI18n()
const router = useRouter()
const isPhone = useIsPhone()
const menu = useTemplateRef('menu')
const isOpen = ref(false)
const isReportVisible = ref(false)

const { canBlock, isBlocked, isWorking, toggleBlock } = useBlockUser(
  () => props.participant.id,
  () => props.participant.username
)

const profileRoute = computed(() => ({
  name: 'app_user_public_profile',
  params: { username: props.participant.username }
}))
const reportLabel = computed(() =>
  t('profile.public.report', { username: props.participant.username })
)
const blockLabel = computed(() =>
  isBlocked.value
    ? t('profile.block.unblock_user', { username: props.participant.username })
    : t('profile.block.block_user', { username: props.participant.username })
)

function toggle(event) {
  if (isPhone.value) isOpen.value = !isOpen.value
  else menu.value?.toggle(event)
}

function close(then) {
  isOpen.value = false
  then()
}

function openReport() {
  isReportVisible.value = true
}

function block() {
  toggleBlock({ onBlocked: () => emit('blocked') })
}

// PrimeVue colours an item's text and icon itself, unlayered, so only an important class turns them red.
const DANGER_TEXT = '!text-red-700 dark:!text-red-400'
const menuPt = {
  itemContent: ({ context }) => ({ class: context.item.danger ? DANGER_TEXT : undefined }),
  itemIcon: ({ context }) => ({ class: context.item.danger ? DANGER_TEXT : undefined })
}

const menuItems = computed(() => [
  {
    label: t('message.thread.view_profile'),
    icon: 'pi pi-user',
    command: () => router.push(profileRoute.value)
  },
  { separator: true },
  { label: reportLabel.value, icon: 'pi pi-flag', danger: true, command: openReport },
  ...(canBlock.value
    ? [
        {
          label: blockLabel.value,
          icon: isBlocked.value ? 'pi pi-unlock' : 'pi pi-ban',
          danger: true,
          disabled: isWorking.value,
          command: block
        }
      ]
    : [])
])
</script>
