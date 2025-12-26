<template>
  <nav class="relative flex items-center justify-between gap-8 px-8 lg:px-20 py-4 bg-surface-0 dark:bg-surface-900">
    <div class="flex items-center gap-4">
      <div class="bg-[#5b87ae] dark:bg-transparent rounded-xs px-4 py-2">
        <img
          src="../../../image/logo.png"
          alt="Logo"
          class="h-4 w-auto"
        />
      </div>
    </div>

    <span
      v-styleclass="{
        selector: '@next',
        enterFromClass: 'hidden',
        enterActiveClass: 'animate-fadein',
        leaveToClass: 'hidden',
        leaveActiveClass: 'animate-fadeout',
        hideOnOutsideClick: true
      }"
      class="cursor-pointer block lg:hidden text-surface-900 dark:text-surface-100"
    >
      <i class="pi pi-bars text-xl! leading-normal!" />
    </span>

    <div
      class="hidden lg:flex flex-1 items-center justify-between absolute lg:static w-full bg-surface-0 dark:bg-surface-900 left-0 top-full z-100 shadow lg:shadow-none border lg:border-0 border-surface-800"
    >
      <div class="flex-1 flex items-start gap-4 px-6 lg:px-0 py-4 lg:py-0 flex-col lg:flex-row">
        <BandSpaceSelector class="mr-2" />

        <BandNavigation :disabled="bandSpaceStore.isCreating" />

        <RouterLink :to="{ name: 'app_home' }" custom v-slot="{ href, navigate }">
          <a
            :href="href"
            @click="(e) => { if (!bandSpaceStore.isCreating) navigate(e) }"
            :class="[
              'flex items-center lg:ml-10 text-xs gap-2 p-2 rounded-lg transition-colors duration-150 border w-full lg:w-auto border-transparent',
              bandSpaceStore.isCreating
                ? 'cursor-not-allowed opacity-50'
                : 'cursor-pointer hover:underline'
            ]"
          >
            <span class="font-medium">back to musicall</span>
          </a>
        </RouterLink>
      </div>

      <UserMenu />
    </div>
  </nav>

  <CreateBandSpaceModal @created="handleBandSpaceCreated" />
</template>

<script setup>
import BandNavigation from '../../components/BandSpace/BandNavigation.vue'
import BandSpaceSelector from '../../components/BandSpace/BandSpaceSelector.vue'
import CreateBandSpaceModal from '../../components/BandSpace/CreateBandSpaceModal.vue'
import UserMenu from '../../components/BandSpace/UserMenu.vue'
import { useBandSpaceNavigation } from '../../composables/useBandSpaceNavigation.js'
import { BAND_SPACE_ROUTES } from '../../constants/bandSpace.js'
import { useBandSpaceStore } from '../../store/bandSpace/bandSpace.js'

const bandSpaceStore = useBandSpaceStore()
const { navigateToSpace } = useBandSpaceNavigation()

function handleBandSpaceCreated(newSpace) {
  navigateToSpace(newSpace.id, BAND_SPACE_ROUTES.DASHBOARD)
}
</script>
