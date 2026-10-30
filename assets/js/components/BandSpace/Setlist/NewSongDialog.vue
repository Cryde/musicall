<template>
  <NewTitleDialog
    v-model:visible="visible"
    :header="t('band_space_setlist.new_song_dialog.header')"
    :label="t('band_space_setlist.new_song_dialog.title_label')"
    :hint="t('band_space_setlist.new_song_dialog.hint')"
    :required-message="t('band_space_setlist.new_song_dialog.title_required')"
    :create="(title) => songsStore.createSong(bandSpaceId, { title })"
    @created="emit('created', $event)"
  />
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { useBandSongsStore } from '../../../store/bandSpace/bandSpaceSongs.js'
import NewTitleDialog from './NewTitleDialog.vue'

/** A song starts from its title alone (#1067); the caller opens its drawer with what this emits. */
defineProps({
  bandSpaceId: { type: String, required: true }
})

const emit = defineEmits(['created'])
const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const songsStore = useBandSongsStore()
</script>
