<template>
  <NewTitleDialog
    v-model:visible="visible"
    :header="t('band_space_setlist.new_lyrics_dialog.header')"
    :label="t('band_space_setlist.new_lyrics_dialog.title_label')"
    :hint="t('band_space_setlist.new_lyrics_dialog.hint')"
    :required-message="t('band_space_setlist.new_lyrics_dialog.title_required')"
    :create="(title) => lyricsStore.createLyrics(bandSpaceId, { title })"
    @created="emit('created', $event)"
  />
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { useBandLyricsStore } from '../../../../store/bandSpace/bandSpaceLyrics.js'
import NewTitleDialog from '../NewTitleDialog.vue'

/** New lyrics start from a working title (#1261); the caller opens them straight in the editor. */
defineProps({
  bandSpaceId: { type: String, required: true }
})

const emit = defineEmits(['created'])
const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const lyricsStore = useBandLyricsStore()
</script>
