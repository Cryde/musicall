<template>
  <!-- A song's own folders (#1451), hung off the same rail as the Files folders. -->
  <ul class="flex flex-col">
    <li v-for="(node, index) in nodes" :key="node.folder.id" class="flex">
      <FolderTreeRail :is-last="index === nodes.length - 1" />
      <div class="min-w-0 flex-1 pt-1">
        <button
          type="button"
          class="flex h-8 w-full items-center gap-2 rounded-md px-2 text-left text-sm transition-colors duration-150"
          :class="rowClasses(songFolderId(songId, node.folder.id))"
          :aria-current="activeFolderId === songFolderId(songId, node.folder.id) ? 'true' : null"
          @click="emit('select', songFolderId(songId, node.folder.id))"
        >
          <i class="pi pi-folder text-xs" aria-hidden="true"></i>
          <span class="flex-1 truncate">{{ node.folder.name }}</span>
          <span v-if="counts.get(node.folder.id)" class="shrink-0 text-xs text-surface-500 tabular-nums">
            {{ counts.get(node.folder.id) }}
          </span>
        </button>
        <SongFolderTreeNodes
          v-if="node.folders.length > 0"
          :song-id="songId"
          :nodes="node.folders"
          :counts="counts"
          :active-folder-id="activeFolderId"
          :row-classes="rowClasses"
          @select="emit('select', $event)"
        />
      </div>
    </li>
  </ul>
</template>

<script setup>
import { songFolderId } from '../../../constants/folderSelection.js'
import FolderTreeRail from './FolderTreeRail.vue'

defineProps({
  songId: { type: String, required: true },
  /** Nodes of buildSongFileTree(). */
  nodes: { type: Array, required: true },
  /** Folder id to the files sitting directly in it. */
  counts: { type: Map, required: true },
  activeFolderId: { type: String, default: null },
  /** The tree's own row classes for a selection, active or not. */
  rowClasses: { type: Function, required: true }
})

const emit = defineEmits(['select'])
</script>
