<template>
  <div class="p-4">
    <div v-if="isOwner && media.length && addTargets.length" class="flex justify-end mb-3">
      <Button
        :label="t('profile.media.add_creation')"
        icon="pi pi-plus"
        size="small"
        outlined
        @click="isAdding = true"
      />
    </div>

    <div v-if="!media.length" class="flex flex-col items-center gap-3 py-4 text-center">
      <p class="m-0 text-sm text-surface-500 dark:text-surface-400">
        {{ emptyText }}
      </p>
      <Button
        v-if="isOwner && addTargets.length"
        :label="t('profile.media.add_creation')"
        icon="pi pi-plus"
        size="small"
        @click="isAdding = true"
      />
    </div>

    <ul v-else class="grid gap-4 sm:grid-cols-2 list-none m-0 p-0">
      <li v-for="(item, index) in media" :key="item.id" :class="{ 'sm:col-span-2': index === 0 }">
        <button
          type="button"
          class="group relative block w-full aspect-video rounded-lg overflow-hidden bg-surface-100 dark:bg-surface-800 cursor-pointer"
          :aria-label="item.title ? t('profile.media.play', { title: item.title }) : t('profile.media.play_untitled')"
          @click="open(item)"
        >
          <!-- The stored thumbnail only: nothing from the platforms loads before a click. -->
          <img v-if="item.thumbnail_url" :src="item.thumbnail_url" alt="" class="w-full h-full object-cover" loading="lazy" />
          <span
            class="absolute top-2 left-2 inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-black/60 text-white text-xs font-semibold"
          >
            <i :class="mediaPlatformIcon(item.platform)" aria-hidden="true" />{{ item.platform_label }}
          </span>
          <span class="absolute inset-0 flex items-center justify-center bg-black/0 group-hover:bg-black/20 transition-colors" aria-hidden="true">
            <span class="w-12 h-12 rounded-full bg-black/60 text-white flex items-center justify-center">
              <i class="pi pi-play" />
            </span>
          </span>
        </button>
        <div class="flex items-start gap-2 mt-2">
          <span class="flex-1 min-w-0 text-sm font-semibold text-surface-900 dark:text-surface-0 truncate">{{ trackLabel(item) }}</span>
          <Button
            v-if="isOwner"
            icon="pi pi-trash"
            severity="danger"
            text
            rounded
            size="small"
            class="shrink-0"
            :aria-label="t('profile.media.delete_named', { title: trackLabel(item) })"
            :loading="deletingId === item.id"
            @click="confirmDelete(item)"
          />
          <ReportButton
            v-else
            target-type="profile_media"
            :target-id="item.id"
            :author-username="ownerUsername"
            :aria-label="item.title ? t('profile.media.report', { title: item.title }) : t('profile.media.report_untitled')"
            icon-only
            class="shrink-0"
          />
        </div>
      </li>
    </ul>

    <MediaPlayerDialog v-model:visible="isPlaying" :media="playing" />
    <AddMediaDialog v-if="isOwner" v-model:visible="isAdding" :targets="addTargets" @added="emit('changed')" />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useMusicianProfileMediaStore } from '../../../store/user/musicianProfileMedia.js'
import { useTeacherProfileMediaStore } from '../../../store/user/teacherProfileMedia.js'
import { mediaPlatformIcon } from '../../../utils/mediaEmbed.js'
import { trackLabel } from '../../../utils/profileListen.js'
import AddMediaDialog from '../../Media/AddMediaDialog.vue'
import MediaPlayerDialog from '../../Media/MediaPlayerDialog.vue'
import ReportButton from '../../Report/ReportButton.vue'

/**
 * The wall's « Médias » (#1317): the member's musician and teacher media as a grid, the first one wider. It took
 * over from the musician page; its owner adds and removes them here.
 */
const props = defineProps({
  media: { type: Array, default: () => [] },
  isOwner: { type: Boolean, default: false },
  ownerUsername: { type: String, required: true },
  /** The owner's profiles a new media can go to: `musician` and/or `teacher`. */
  addTargets: { type: Array, default: () => [] }
})

const emit = defineEmits(['changed'])
const { t } = useI18n()
const confirm = useConfirm()
const toast = useToast()
// Each media is deleted through the profile that holds it.
const stores = {
  musician: useMusicianProfileMediaStore(),
  teacher: useTeacherProfileMediaStore()
}

// A media belongs to a musician or teacher profile: without one, the owner is told where to start.
const emptyText = computed(() => {
  if (!props.isOwner) return t('profile.wall.empty.media')
  return props.addTargets.length
    ? t('profile.media.empty_own')
    : t('profile.owner.hint.media_no_profile')
})
const isPlaying = ref(false)
const playing = ref(null)
const isAdding = ref(false)
const deletingId = ref(null)

function open(item) {
  playing.value = item
  isPlaying.value = true
}

function confirmDelete(item) {
  confirm.require({
    header: t('profile.media.delete_confirm_header'),
    message: t('profile.media.delete_confirm_message'),
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: t('profile.media.cancel'), severity: 'secondary', outlined: true },
    acceptProps: { label: t('profile.media.delete'), severity: 'danger' },
    accept: () => remove(item)
  })
}

async function remove(item) {
  deletingId.value = item.id
  try {
    await stores[item.profile].deleteMedia(item.id)
    emit('changed')
  } catch (error) {
    toast.add({ severity: 'error', summary: error.message || t('profile.media.error'), life: 5000 })
  } finally {
    deletingId.value = null
  }
}
</script>
