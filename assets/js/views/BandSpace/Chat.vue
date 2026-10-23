<template>
  <div
    class="bg-surface-0 dark:bg-surface-900 rounded-2xl overflow-hidden flex flex-col h-[calc(100vh-16rem)] min-h-[400px]"
  >
    <BandChatPanel :band-space-id="bandSpaceId" :focus-message-id="focusMessageId" />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import BandChatPanel from '../../components/BandSpace/Chat/BandChatPanel.vue'

const route = useRoute()
// Read once: AppBandLayout keys <router-view> on the space id, so this view is remounted rather than
// reused when the member switches band.
const bandSpaceId = route.params.id

const focusMessageId = computed(() =>
  typeof route.query.message === 'string' ? route.query.message : null
)
</script>
