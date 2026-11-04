<template>
  <!-- On a phone the chat is the whole screen under the band's header, its composer at the bottom, as
       in the app; from `md` up it stays a card in the page. -->
  <div
    class="bg-surface-0 dark:bg-surface-900 overflow-hidden flex flex-col max-md:fixed max-md:inset-x-0 max-md:top-16 max-md:bottom-0 max-md:z-20 max-md:pb-[env(safe-area-inset-bottom)] md:rounded-2xl md:h-[calc(100vh-16rem)] md:min-h-[400px]"
  >
    <BandChatPanel :band-space-id="bandSpaceId" :focus-message-id="focusMessageId" />
  </div>
</template>

<script setup>
import { useMediaQuery, useScrollLock } from '@vueuse/core'
import { computed, watchEffect } from 'vue'
import { useRoute } from 'vue-router'
import BandChatPanel from '../../components/BandSpace/Chat/BandChatPanel.vue'

const route = useRoute()
// Read once: AppBandLayout keys <router-view> on the space id, so this view is remounted rather than
// reused when the member switches band.
const bandSpaceId = route.params.id

const focusMessageId = computed(() =>
  typeof route.query.message === 'string' ? route.query.message : null
)

// The page under the phone's full screen chat stays put while the conversation scrolls. Tailwind's `md`
// is 48rem: keep this query in step with the `max-md:` classes above.
const isFullScreen = useMediaQuery('(width < 48rem)')
const isPageLocked = useScrollLock(typeof document === 'undefined' ? null : document.body)
watchEffect(() => {
  isPageLocked.value = isFullScreen.value
})
</script>
