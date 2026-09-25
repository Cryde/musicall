<template>
  <!-- Deleted from Files, or not loadable: the bubble says so rather than drawing a broken image. -->
  <span
    v-if="!image.is_available || hasFailed"
    class="mb-2 last:mb-0 flex items-center gap-2 text-sm italic opacity-80"
  >
    <i class="pi pi-image text-xs" aria-hidden="true" />
    Image supprimée
  </span>
  <!-- A fixed height so a page of images does not push the conversation down as each one loads. -->
  <Image
    v-else
    :src="src"
    alt="Image envoyée dans la conversation"
    preview
    class="mb-2 last:mb-0 block"
    image-class="h-48 w-auto max-w-64 rounded-lg object-cover"
    loading="lazy"
    :pt="{ original: { class: 'max-h-[90vh]! max-w-[90vw]! object-contain' } }"
    @error="hasFailed = true"
  />
</template>

<script setup>
import Image from 'primevue/image'
import { computed, ref } from 'vue'
import bandSpaceChatApi from '../../../api/bandSpace/band-space-chat.js'

const props = defineProps({
  /** The `image` of one ChatMessage, straight from the API. */
  image: { type: Object, required: true },
  bandSpaceId: { type: String, required: true }
})

const hasFailed = ref(false)
const src = computed(() => bandSpaceChatApi.imageUrl(props.bandSpaceId, props.image.file_id))
</script>
