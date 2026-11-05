<template>
  <MobileMenuDrawer v-model:visible="visible" @show="handleShow">
    <MobileMenuSection v-if="isMember">
      <MobileMenuInbox />
    </MobileMenuSection>

    <MobileMenuSection v-if="isMember" :title="t('menu.my_band_spaces')" :aria-busy="isLoadingBandSpaces">
      <li v-for="space in bandSpaceStore.spaces" :key="space.id">
        <MobileMenuItem :to="{ name: 'app_band_dashboard', params: { id: space.id } }">
          <template #icon>
            <span :class="BAND_MARK" aria-hidden="true">{{ bandInitial(space.name) }}</span>
          </template>
          {{ space.name }}
          <template #end>
            <i :class="CHEVRON" aria-hidden="true" />
          </template>
        </MobileMenuItem>
      </li>
      <li v-if="isLoadingBandSpaces" class="flex items-center gap-3 px-3 py-2.5" aria-hidden="true">
        <span class="size-6 shrink-0 rounded-md bg-surface-200 dark:bg-surface-700 animate-pulse" />
        <span class="h-4 w-32 rounded bg-surface-200 dark:bg-surface-700 animate-pulse" />
      </li>
      <li v-else-if="!bandSpaceStore.hasSpaces">
        <MobileMenuItem :to="{ name: 'app_band_index' }" :icon="noSpaceEntry.icon">
          {{ noSpaceEntry.label }}
          <template #end>
            <i :class="CHEVRON" aria-hidden="true" />
          </template>
        </MobileMenuItem>
      </li>
    </MobileMenuSection>

    <MobileMenuSection :title="t('menu.explore')">
      <li v-for="link in exploreLinks" :key="link.key">
        <MobileMenuItem :to="link.to" :icon="link.icon">{{ link.label }}</MobileMenuItem>
      </li>
    </MobileMenuSection>

    <MobileMenuAccount v-if="isMember" />

    <MobileMenuSection v-if="CAN_CHOOSE_LOCALE || isMember">
      <li v-if="CAN_CHOOSE_LOCALE"><LocaleToggle /></li>
      <li v-if="isMember">
        <MobileMenuItem icon="pi pi-sign-out" @click="logout">{{ t('user_menu.logout') }}</MobileMenuItem>
      </li>
    </MobileMenuSection>

    <template v-if="isVisitor" #footer>
      <div class="flex gap-2">
        <Button
          as="router-link"
          :to="{ name: 'app_login' }"
          :label="t('common.login')"
          severity="info"
          outlined
          class="flex-1"
        />
        <Button as="router-link" :to="{ name: 'app_register' }" :label="t('common.register')" class="flex-1" />
      </div>
    </template>
  </MobileMenuDrawer>
</template>

<script setup>
import Button from 'primevue/button'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { CAN_CHOOSE_LOCALE } from '../../composables/useLocaleChoice.js'
import { useUserMenu } from '../../composables/useUserMenu.js'
import { useBandSpaceStore } from '../../store/bandSpace/bandSpace.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { bandInitial } from '../../utils/userMenu.js'
import LocaleToggle from './LocaleToggle.vue'
import MobileMenuAccount from './MobileMenuAccount.vue'
import MobileMenuDrawer from './MobileMenuDrawer.vue'
import MobileMenuInbox from './MobileMenuInbox.vue'
import MobileMenuItem from './MobileMenuItem.vue'
import MobileMenuSection from './MobileMenuSection.vue'

/** The site's menu below `lg`: the member's own places first, then the site, then the account. */
const visible = defineModel('visible', { type: Boolean, default: false })

const CHEVRON = 'pi pi-chevron-right ml-auto text-xs text-surface-500 dark:text-surface-400'
const BAND_MARK =
  'flex size-6 shrink-0 items-center justify-center rounded-md bg-primary-600 dark:bg-primary-400 text-xs font-semibold text-white dark:text-surface-900'

const { t } = useI18n()
const userSecurityStore = useUserSecurityStore()
const bandSpaceStore = useBandSpaceStore()
const { logout } = useUserMenu()

const isMember = computed(() => userSecurityStore.isAuthenticated)
// Neither one while the session check is out, so a returning member is not offered to log in.
const isVisitor = computed(
  () => !userSecurityStore.isAuthenticatedLoading && !userSecurityStore.isAuthenticated
)

const exploreLinks = computed(() => [
  { key: 'home', label: t('menu.home'), icon: 'pi pi-home', to: { name: 'app_home' } },
  // Only a visitor needs the tour of the site; nothing else on a phone leads to it.
  ...(isVisitor.value
    ? [
        {
          key: 'discover',
          label: t('menu.discover'),
          icon: 'pi pi-sparkles',
          to: { name: 'app_discover' }
        }
      ]
    : []),
  {
    key: 'musicians',
    label: t('menu.search_musicians'),
    icon: 'pi pi-search',
    to: { name: 'app_search_musician' }
  },
  {
    key: 'teachers',
    label: t('menu.search_teachers'),
    icon: 'pi pi-graduation-cap',
    to: { name: 'app_search_teacher' }
  },
  {
    key: 'publications',
    label: t('menu.publications'),
    icon: 'pi pi-file',
    to: { name: 'app_publications' }
  },
  { key: 'courses', label: t('menu.courses'), icon: 'pi pi-book', to: { name: 'app_course' } },
  { key: 'forum', label: t('menu.forum'), icon: 'pi pi-comments', to: { name: 'app_forum_index' } },
  // A member finds their own bands above; a visitor is shown what the Band Space is.
  ...(isVisitor.value
    ? [
        {
          key: 'band_space',
          label: t('menu.band_space'),
          icon: 'pi pi-users',
          to: { name: 'app_band_space_presentation' }
        }
      ]
    : [])
])

// Only the home loads a member's Band Spaces, so the first opening anywhere else fetches them:
// 'idle' until then, 'loading', then 'loaded' or 'failed', which the next opening tries again.
const bandSpacesStatus = ref('idle')
const isLoadingBandSpaces = computed(
  () => !bandSpaceStore.hasSpaces && ['idle', 'loading'].includes(bandSpacesStatus.value)
)

// Without the list, a failed fetch must not claim the member has no band: the plain entry still lands
// right, since app_band_index opens their space or offers to create one.
const noSpaceEntry = computed(() =>
  bandSpacesStatus.value === 'failed'
    ? { label: t('menu.band_space'), icon: 'pi pi-users' }
    : { label: t('menu.create_band_space'), icon: 'pi pi-plus' }
)

async function handleShow() {
  if (
    !isMember.value ||
    bandSpaceStore.hasSpaces ||
    ['loading', 'loaded'].includes(bandSpacesStatus.value)
  ) {
    return
  }
  bandSpacesStatus.value = 'loading'
  try {
    await bandSpaceStore.loadMyBandSpaces()
    bandSpacesStatus.value = 'loaded'
  } catch {
    bandSpacesStatus.value = 'failed'
  }
}
</script>
