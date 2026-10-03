<template>
  <div class="flex flex-col gap-10 lg:gap-14">
    <HomeMemberGreeting
      :has-band="layout === MEMBER_LAYOUT_BAND"
      @post-announce="showAnnounceModal = true"
      @post-discovery="videoStore.openModal()"
    />

    <!-- Nothing until the spaces are known, as the layout depends on them. -->
    <div v-if="layout === null" class="flex flex-col gap-5" aria-busy="true">
      <div class="h-72 rounded-2xl bg-surface-100 dark:bg-surface-900 animate-pulse" />
    </div>

    <!-- A band first: what is next for it, then the rest. -->
    <template v-else-if="layout === MEMBER_LAYOUT_BAND">
      <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1.45fr)_minmax(0,0.55fr)] gap-5">
        <HomeMemberBandSpace />
        <div class="flex flex-col gap-5">
          <HomeMemberMessages />
          <HomeMemberActivity />
        </div>
      </div>
      <HomeMemberAnnounces :key="announcesKey" @contact-announce="openMessageTo" />
    </template>

    <!-- No band yet: most likely still looking, so the search first and the Band Space as one line. -->
    <template v-else>
      <HomeMemberSearch />
      <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1.45fr)_minmax(0,0.55fr)] gap-5">
        <HomeMemberAnnounces
          :key="announcesKey"
          :limit="SEARCH_LAYOUT_ANNOUNCES"
          :columns="2"
          @contact-announce="openMessageTo"
        />
        <div class="flex flex-col gap-5">
          <HomeMemberMessages />
          <HomeMemberActivity />
        </div>
      </div>
      <HomeMemberMyAnnounces :key="announcesKey" />
    </template>

    <!-- Below the fold: asked for once it comes into view, not with the rest of the page. -->
    <div v-if="layout !== null" ref="lowerBlocks" class="flex flex-col gap-5 min-h-64">
      <div v-if="showLowerBlocks" class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <HomeMemberDiscoveries :key="discoveriesKey" />
        <div class="flex flex-col gap-5">
          <HomeMemberForum />
          <HomeMemberTeacher />
        </div>
      </div>
      <HomeMemberBandSpaceStrip v-if="showLowerBlocks && layout === MEMBER_LAYOUT_SEARCH" />
    </div>

    <AddDiscoverModal @published="discoveriesKey++" />
    <SendMessageModal v-if="showMessageModal" v-model:visible="showMessageModal" :selected-recipient="selectedRecipient" />
    <AddAnnounceModal v-if="showAnnounceModal" v-model:visible="showAnnounceModal" @created="announcesKey++" />
  </div>
</template>

<script setup>
import { useElementVisibility } from '@vueuse/core'
import { defineAsyncComponent, onMounted, ref, useTemplateRef, watch } from 'vue'
import HomeMemberActivity from '../../components/Home/Member/HomeMemberActivity.vue'
import HomeMemberAnnounces from '../../components/Home/Member/HomeMemberAnnounces.vue'
import HomeMemberBandSpace from '../../components/Home/Member/HomeMemberBandSpace.vue'
import HomeMemberBandSpaceStrip from '../../components/Home/Member/HomeMemberBandSpaceStrip.vue'
import HomeMemberDiscoveries from '../../components/Home/Member/HomeMemberDiscoveries.vue'
import HomeMemberForum from '../../components/Home/Member/HomeMemberForum.vue'
import HomeMemberGreeting from '../../components/Home/Member/HomeMemberGreeting.vue'
import HomeMemberMessages from '../../components/Home/Member/HomeMemberMessages.vue'
import HomeMemberMyAnnounces from '../../components/Home/Member/HomeMemberMyAnnounces.vue'
import HomeMemberSearch from '../../components/Home/Member/HomeMemberSearch.vue'
import HomeMemberTeacher from '../../components/Home/Member/HomeMemberTeacher.vue'
import AddDiscoverModal from '../../components/Publication/AddDiscoverModal.vue'
import { useBandSpaceStore } from '../../store/bandSpace/bandSpace.js'
import { useVideoStore } from '../../store/publication/video.js'
import {
  MEMBER_LAYOUT_BAND,
  MEMBER_LAYOUT_SEARCH,
  memberLayoutFor
} from '../../utils/memberHome.js'

/**
 * The homepage as a logged in member sees it (#1078): their band first when they have one, the
 * search first when they do not yet.
 */
const SEARCH_LAYOUT_ANNOUNCES = 6

// Only opened on a click: kept out of the page's first bundle.
const SendMessageModal = defineAsyncComponent(
  () => import('../../components/Message/SendMessageModal.vue')
)
const AddAnnounceModal = defineAsyncComponent(() => import('../User/Announce/AddAnnounceModal.vue'))

const videoStore = useVideoStore()
const bandSpaceStore = useBandSpaceStore()

const layout = ref(null)

onMounted(async () => {
  try {
    await bandSpaceStore.loadMyBandSpaces()
  } catch {
    // Read as no space: the search layout still works, and the menu still leads to the Band Space.
  }
  layout.value = memberLayoutFor(bandSpaceStore.spaces)
})

const showAnnounceModal = ref(false)
const showMessageModal = ref(false)
const selectedRecipient = ref(null)
// Bumped to reload a block after the member posted to it.
const announcesKey = ref(0)
const discoveriesKey = ref(0)

const lowerBlocks = useTemplateRef('lowerBlocks')
const lowerBlocksVisible = useElementVisibility(lowerBlocks, { rootMargin: '200px' })
// Kept once shown: scrolling back up must not unmount and reload them.
const showLowerBlocks = ref(false)
watch(lowerBlocksVisible, (visible) => {
  if (visible) showLowerBlocks.value = true
})

function openMessageTo(author) {
  selectedRecipient.value = author
  showMessageModal.value = true
}
</script>
