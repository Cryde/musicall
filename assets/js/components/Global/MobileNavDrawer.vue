<template>
  <Drawer
    v-model:visible="visible"
    position="right"
    block-scroll
    :aria-label="t('menu.title')"
    :close-button-props="closeButtonProps"
    @show="handleShow"
  >
    <template #header>
      <div v-if="isMember" class="flex min-w-0 items-center gap-3">
        <Avatar
          v-if="userSecurityStore.profilePictureUrl"
          :image="userSecurityStore.profilePictureUrl"
          :pt="{ image: { alt: '' } }"
          shape="circle"
          :class="AVATAR"
          aria-hidden="true"
        />
        <Avatar
          v-else
          :label="username.charAt(0).toUpperCase()"
          :style="getAvatarStyle(username)"
          shape="circle"
          :class="AVATAR"
          aria-hidden="true"
        />
        <div class="flex min-w-0 flex-col">
          <span class="truncate font-semibold text-surface-900 dark:text-surface-0">{{ username }}</span>
          <RouterLink
            :to="profile.to"
            class="self-start text-sm font-semibold text-primary hover:underline"
            @click="close"
          >
            {{ profile.label }}
          </RouterLink>
        </div>
      </div>
      <span v-else class="text-lg font-semibold text-surface-900 dark:text-surface-0">{{ t('menu.title') }}</span>
    </template>

    <!-- The rows reach into the drawer's padding, so their icons line up with the header's avatar. -->
    <div class="-mx-3 flex flex-col divide-y divide-surface-200 dark:divide-surface-700">
      <ul v-if="isMember" :class="[SECTION, LIST]">
        <li>
          <MobileMenuItem
            :to="{ name: 'app_messages' }"
            icon="pi pi-envelope"
            :aria-label="notificationStore.unreadMessages > 0 ? t('user_menu.messages_unread', { count: notificationStore.unreadMessages }) : undefined"
            @click="close"
          >
            {{ t('user_menu.messages') }}
            <template v-if="notificationStore.unreadMessages > 0" #end>
              <Badge :value="notificationStore.unreadMessages" severity="danger" class="ml-auto" aria-hidden="true" />
            </template>
          </MobileMenuItem>
        </li>
        <li><MobileMenuNotificationsItem @click="close" /></li>
      </ul>

      <section v-if="isMember" :class="SECTION" :aria-labelledby="bandSpacesTitleId">
        <h2 :id="bandSpacesTitleId" :class="HEADING">{{ t('menu.my_band_spaces') }}</h2>
        <ul :class="LIST" :aria-busy="isLoadingBandSpaces">
          <li v-for="space in bandSpaceStore.spaces" :key="space.id">
            <MobileMenuItem :to="{ name: 'app_band_dashboard', params: { id: space.id } }" @click="close">
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
            <MobileMenuItem :to="{ name: 'app_band_index' }" :icon="noSpaceEntry.icon" @click="close">
              {{ noSpaceEntry.label }}
              <template #end>
                <i :class="CHEVRON" aria-hidden="true" />
              </template>
            </MobileMenuItem>
          </li>
        </ul>
      </section>

      <section :class="SECTION" :aria-labelledby="exploreTitleId">
        <h2 :id="exploreTitleId" :class="HEADING">{{ t('menu.explore') }}</h2>
        <ul :class="LIST">
          <li v-for="link in exploreLinks" :key="link.key">
            <MobileMenuItem :to="link.to" :icon="link.icon" @click="close">{{ link.label }}</MobileMenuItem>
          </li>
        </ul>
      </section>

      <section v-if="isMember" :class="SECTION" :aria-labelledby="accountTitleId">
        <h2 :id="accountTitleId" :class="HEADING">{{ t('menu.my_account') }}</h2>
        <ul :class="LIST">
          <li v-for="entry in accountEntries" :key="entry.key">
            <MobileMenuItem :to="entry.to" :icon="entry.icon" @click="close">{{ entry.label }}</MobileMenuItem>
          </li>
        </ul>
      </section>

      <div v-if="CAN_CHOOSE_LOCALE || isMember" :class="SECTION">
        <div v-if="CAN_CHOOSE_LOCALE" class="flex items-center gap-3 px-3 py-1.5">
          <i class="pi pi-globe w-5 shrink-0 text-center text-base text-surface-700 dark:text-surface-300" aria-hidden="true" />
          <!-- Drawn like a SelectButton without loading one: every page of this layout carries the menu. -->
          <div role="group" :aria-label="t('locale_switcher.menu')" class="flex flex-1 gap-1 rounded-md bg-surface-100 dark:bg-surface-950 p-1">
            <button
              v-for="option in LOCALE_OPTIONS"
              :key="option.locale"
              type="button"
              :lang="option.locale"
              :aria-pressed="option.locale === activeLocale"
              :disabled="isSwitching"
              :class="[
                'flex-1 rounded px-3 py-1 text-sm font-medium transition-colors cursor-pointer disabled:cursor-wait',
                option.locale === activeLocale
                  ? 'bg-surface-0 dark:bg-surface-800 text-surface-900 dark:text-surface-0 shadow-sm'
                  : 'text-surface-600 dark:text-surface-300 hover:text-surface-900 dark:hover:text-surface-0'
              ]"
              @click="option.locale !== activeLocale && chooseLocale(option.locale)"
            >
              {{ option.label }}
            </button>
          </div>
        </div>
        <MobileMenuItem v-if="isMember" icon="pi pi-sign-out" @click="logout">{{ t('user_menu.logout') }}</MobileMenuItem>
      </div>
    </div>

    <template v-if="isVisitor" #footer>
      <div class="flex gap-2">
        <Button
          as="router-link"
          :to="{ name: 'app_login' }"
          :label="t('common.login')"
          severity="info"
          outlined
          class="flex-1"
          @click="close"
        />
        <Button as="router-link" :to="{ name: 'app_register' }" :label="t('common.register')" class="flex-1" @click="close" />
      </div>
    </template>
  </Drawer>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import Drawer from 'primevue/drawer'
import { computed, defineAsyncComponent, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import {
  CAN_CHOOSE_LOCALE,
  LOCALE_OPTIONS,
  useLocaleChoice
} from '../../composables/useLocaleChoice.js'
import { useUserMenu } from '../../composables/useUserMenu.js'
import { useBandSpaceStore } from '../../store/bandSpace/bandSpace.js'
import { useNotificationStore } from '../../store/notification/notification.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { getAvatarStyle } from '../../utils/avatar.js'
import { bandInitial } from '../../utils/userMenu.js'
import MobileMenuItem from './MobileMenuItem.vue'

// Its store is the bell's, which a visitor's bundle does not carry.
const MobileMenuNotificationsItem = defineAsyncComponent(
  () => import('../Notification/MobileMenuNotificationsItem.vue')
)

/** The site's menu below `lg`: a drawer like the Band Space's, with the member's own places first. */
const visible = defineModel('visible', { type: Boolean, default: false })

const SECTION = 'py-2'
const LIST = 'm-0 list-none p-0'
const HEADING = 'm-0 px-3 pt-1.5 pb-1 text-sm font-semibold text-surface-500 dark:text-surface-400'
const CHEVRON = 'pi pi-chevron-right ml-auto text-xs text-surface-500 dark:text-surface-400'
const BAND_MARK =
  'flex size-6 shrink-0 items-center justify-center rounded-md bg-primary-600 dark:bg-primary-400 text-xs font-semibold text-white dark:text-surface-900'
// PrimeVue's own styles win over a size class, so the avatar is sized through its tokens.
const AVATAR = 'shrink-0 [--p-avatar-height:2.5rem] [--p-avatar-width:2.5rem]'

const { t } = useI18n()
const route = useRoute()
const userSecurityStore = useUserSecurityStore()
const notificationStore = useNotificationStore()
const bandSpaceStore = useBandSpaceStore()
const { entries, logout } = useUserMenu()
const { activeLocale, isSwitching, chooseLocale } = useLocaleChoice()

const bandSpacesTitleId = useId()
const exploreTitleId = useId()
const accountTitleId = useId()

const isMember = computed(() => userSecurityStore.isAuthenticated)
// Neither one while the session check is out, so a returning member is not offered to log in.
const isVisitor = computed(
  () => !userSecurityStore.isAuthenticatedLoading && !userSecurityStore.isAuthenticated
)

const username = computed(() => userSecurityStore.user?.username ?? '')
const profile = computed(() => entries.value.find((entry) => entry.key === 'profile'))
const accountEntries = computed(() => entries.value.filter((entry) => entry.key !== 'profile'))

const exploreLinks = computed(() => [
  { key: 'home', label: t('menu.home'), icon: 'pi pi-home', to: { name: 'app_home' } },
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

// PrimeVue names its close button in English whatever the page's language.
const closeButtonProps = computed(() => ({
  severity: 'secondary',
  text: true,
  rounded: true,
  'aria-label': t('menu.close')
}))

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

function close() {
  visible.value = false
}

// Following a link, or going back, never leaves the menu open over the next page.
watch(() => route.fullPath, close)
</script>
