<template>
  <MobileMenuSection :title="t('menu.my_account')">
    <slot />
    <li v-for="entry in accountEntries" :key="entry.key">
      <MobileMenuItem :to="entry.to" :icon="entry.icon">{{ entry.label }}</MobileMenuItem>
    </li>
    <!-- The header's feedback shortcut, which a phone has no room for. -->
    <li v-if="userSecurityStore.isAdmin && notificationStore.newFeedbacks > 0" class="sm:hidden">
      <MobileMenuItem :to="{ name: 'admin_feedbacks_index' }" icon="pi pi-comment">
        {{ t('user_menu.feedbacks_pending', { count: notificationStore.newFeedbacks }) }}
      </MobileMenuItem>
    </li>
  </MobileMenuSection>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserMenu } from '../../composables/useUserMenu.js'
import { useNotificationStore } from '../../store/notification/notification.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import MobileMenuItem from './MobileMenuItem.vue'
import MobileMenuSection from './MobileMenuSection.vue'

/** « Mon compte » in the phone menu: the avatar menu's entries, the profile being in the menu's header. */
const props = defineProps({
  /** Renames the settings where another « Paramètres » sits in the same menu, as in a Band Space. */
  settingsLabel: { type: String, default: null }
})

const { t } = useI18n()
const userSecurityStore = useUserSecurityStore()
const notificationStore = useNotificationStore()
const { entries } = useUserMenu()

const accountEntries = computed(() =>
  entries.value
    .filter((entry) => entry.key !== 'profile')
    .map((entry) =>
      entry.key === 'settings' && props.settingsLabel
        ? { ...entry, label: props.settingsLabel }
        : entry
    )
)
</script>
