<template>
  <div>
    <!-- The heading is the band's name alone. The switch is its own button, whose hit area covers the
         whole row so the name reads as tappable, as in the app. -->
    <div
      class="relative -mx-2 -my-1.5 flex max-w-full items-center gap-3 rounded-xl px-2 py-1.5 transition-colors duration-150 hover:bg-surface-0/60 dark:hover:bg-surface-900"
    >
      <span
        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-lg font-bold text-white dark:bg-primary-400 dark:text-surface-900"
        aria-hidden="true"
      >
        {{ bandInitial(currentSpace?.name ?? '') }}
      </span>
      <h1 class="m-0 min-w-0 truncate text-xl font-semibold text-surface-900 dark:text-surface-0">
        {{ currentSpace?.name }}
      </h1>
      <button
        type="button"
        class="-ml-1.5 flex shrink-0 cursor-pointer items-center rounded-lg p-1.5 text-surface-500 after:absolute after:inset-0 after:rounded-xl after:content-[''] dark:text-surface-400"
        aria-haspopup="menu"
        :aria-expanded="isMenuOpen"
        :aria-label="t('home.member.band_space.switch')"
        :disabled="bandSpaceStore.isCreating"
        @click="menu.toggle($event)"
      >
        <i class="pi pi-chevron-down text-xs" aria-hidden="true" />
      </button>
    </div>
    <!-- Under the name, past the band's mark (44px and a 12px gap). -->
    <p v-if="subtitle" class="m-0 mt-0.5 pl-14 text-sm text-surface-600 dark:text-surface-400">{{ subtitle }}</p>
    <Menu ref="menu" :model="menuItems" popup @show="isMenuOpen = true" @hide="isMenuOpen = false" />
  </div>
</template>

<script setup>
import Menu from 'primevue/menu'
import { computed, ref, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBandSpaceNavigation } from '../../../composables/useBandSpaceNavigation.js'
import { useBandSpaceStore } from '../../../store/bandSpace/bandSpace.js'
import { bandSubtitle } from '../../../utils/bandHomeStatus.js'
import { bandInitial } from '../../../utils/userMenu.js'

/**
 * The band home's title, as the app's header: the band's mark and name, its members and the member's
 * other bands. The name switches band, which a phone otherwise only does from the menu.
 */
const props = defineProps({
  memberCount: { type: Number, default: 0 }
})

const { t } = useI18n()
const bandSpaceStore = useBandSpaceStore()
const { currentSpace, currentSpaceId, navigateToSpace } = useBandSpaceNavigation()

const menu = useTemplateRef('menu')
const isMenuOpen = ref(false)

const subtitle = computed(() => bandSubtitle(props.memberCount, bandSpaceStore.spacesCount - 1))

// The blank fixed-width icon keeps every name on the same column as the ticked one.
const menuItems = computed(() => [
  ...bandSpaceStore.spaces.map((space) => ({
    label: space.name,
    icon: space.id === currentSpaceId.value ? 'pi pi-fw pi-check' : 'pi pi-fw',
    command: () => {
      if (space.id !== currentSpaceId.value) navigateToSpace(space.id)
    }
  })),
  { separator: true },
  {
    label: t('band_space.selector.create'),
    icon: 'pi pi-fw pi-plus',
    command: () => bandSpaceStore.openCreateModal()
  }
])
</script>
