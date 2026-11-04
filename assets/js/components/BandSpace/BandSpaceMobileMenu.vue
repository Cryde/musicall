<template>
  <MobileMenuDrawer v-model:visible="visible">
    <MobileMenuSection>
      <!-- Its list opens inside the drawer: outside it, the modal drawer would hide it from screen readers.
           Full width at every size, over the md:w-56 the selector keeps for the desktop header. -->
      <li class="px-3 pb-1">
        <BandSpaceSelector class="w-full md:w-full!" append-to="self" />
      </li>
      <li>
        <MobileMenuItem :to="{ name: 'app_home' }" icon="pi pi-arrow-left">{{ t('band_menu.back_to_musicall') }}</MobileMenuItem>
      </li>
    </MobileMenuSection>

    <BandSidebar in-drawer :disabled="bandSpaceStore.isCreating" @navigate="close" />

    <MobileMenuAccount :settings-label="t('band_space.layout.account_settings')">
      <MobileMenuInbox />
    </MobileMenuAccount>

    <MobileMenuSection>
      <li v-if="CAN_CHOOSE_LOCALE"><LocaleToggle /></li>
      <li>
        <MobileMenuItem icon="pi pi-sign-out" @click="logout">{{ t('user_menu.logout') }}</MobileMenuItem>
      </li>
    </MobileMenuSection>
  </MobileMenuDrawer>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { CAN_CHOOSE_LOCALE } from '../../composables/useLocaleChoice.js'
import { useUserMenu } from '../../composables/useUserMenu.js'
import { useBandSpaceStore } from '../../store/bandSpace/bandSpace.js'
import LocaleToggle from '../Global/LocaleToggle.vue'
import MobileMenuAccount from '../Global/MobileMenuAccount.vue'
import MobileMenuDrawer from '../Global/MobileMenuDrawer.vue'
import MobileMenuInbox from '../Global/MobileMenuInbox.vue'
import MobileMenuItem from '../Global/MobileMenuItem.vue'
import MobileMenuSection from '../Global/MobileMenuSection.vue'
import BandSidebar from './BandSidebar.vue'
import BandSpaceSelector from './BandSpaceSelector.vue'

/** A Band Space's menu below `lg`, built like the site's: the band first, then the member's account. */
const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const bandSpaceStore = useBandSpaceStore()
const { logout } = useUserMenu()

// « Un retour ? » opens a panel rather than following a link, so the sidebar asks for the menu to close.
function close() {
  visible.value = false
}
</script>
