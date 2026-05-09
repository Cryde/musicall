<template>
  <nav class="flex items-center flex-wrap gap-1 text-sm" aria-label="Fil d'Ariane">
    <button
      v-if="segments.length > 0"
      type="button"
      class="text-surface-500 hover:text-surface-800 dark:hover:text-surface-100 hover:underline"
      @click="emit('select', null)"
    >
      Tous les fichiers
    </button>
    <span
      v-else
      class="font-medium text-surface-800 dark:text-surface-100"
    >
      Tous les fichiers
    </span>

    <template v-for="(seg, index) in segments" :key="seg.id">
      <i class="pi pi-chevron-right text-xs text-surface-300"></i>
      <button
        v-if="index < segments.length - 1"
        type="button"
        class="text-surface-500 hover:text-surface-800 dark:hover:text-surface-100 hover:underline truncate max-w-[12rem]"
        @click="emit('select', seg.id)"
      >
        {{ seg.name }}
      </button>
      <span
        v-else
        class="font-medium text-surface-800 dark:text-surface-100 truncate max-w-[16rem]"
      >
        {{ seg.name }}
      </span>
    </template>
  </nav>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  folders: { type: Array, default: () => [] },
  activeFolderId: { type: String, default: null }
})

const emit = defineEmits(['select'])

const segments = computed(() => {
  if (!props.activeFolderId || props.activeFolderId.startsWith('virtual:')) return []

  const flat = flatten(props.folders)
  const byId = new Map(flat.map((f) => [f.id, f]))

  const chain = []
  let current = byId.get(props.activeFolderId) ?? null
  while (current) {
    chain.unshift({ id: current.id, name: current.name })
    current = current.parent_id ? byId.get(current.parent_id) : null
  }
  return chain
})

function flatten(nodes) {
  const out = []
  const walk = (list) => {
    for (const node of list) {
      out.push(node)
      if (Array.isArray(node.children) && node.children.length > 0) {
        walk(node.children)
      }
    }
  }
  walk(nodes)
  return out
}
</script>
