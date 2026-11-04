<template>
  <Drawer
    v-model:visible="visible"
    position="right"
    block-scroll
    :aria-label="t('menu.title')"
    :close-button-props="closeButtonProps"
    :pt="{ root: { onClick: closeOnFollowedLink } }"
  >
    <template #header>
      <MobileMenuProfile v-if="userSecurityStore.isAuthenticated" />
      <span v-else class="text-lg font-semibold text-surface-900 dark:text-surface-0">{{ t('menu.title') }}</span>
    </template>

    <!-- The rows reach into the drawer's padding, so their icons line up with the header's avatar. -->
    <div class="-mx-3 flex flex-col divide-y divide-surface-200 dark:divide-surface-700">
      <slot />
    </div>

    <template v-if="$slots.footer" #footer>
      <slot name="footer" />
    </template>
  </Drawer>
</template>

<script setup>
import Drawer from 'primevue/drawer'
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useUserSecurityStore } from '../../store/user/security.js'
import { followsInPlace } from '../../utils/linkClick.js'
import MobileMenuProfile from './MobileMenuProfile.vue'

/** The phone menu's frame, the site's and the Band Space's alike: a drawer from the right, the member on top. */
const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const route = useRoute()
const userSecurityStore = useUserSecurityStore()

// PrimeVue names its close button in English whatever the page's language.
const closeButtonProps = computed(() => ({
  severity: 'secondary',
  text: true,
  rounded: true,
  'aria-label': t('menu.close')
}))

function close() {
  visible.value = false
}

// A link followed from the menu closes it, the page already shown included, which the route cannot tell.
// One opened in another tab leaves this page, and so the menu, as they are.
function closeOnFollowedLink(event) {
  const link = event.target.closest('a[href]')
  if (link && followsInPlace(event, link.getAttribute('target'))) {
    close()
  }
}

// Going back, or anything else that changes the page, never leaves the menu open over the next one.
watch(() => route.fullPath, close)
</script>
