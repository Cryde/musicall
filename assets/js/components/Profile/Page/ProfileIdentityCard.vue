<template>
  <section
    class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm overflow-hidden"
    :class="{ [OWNER_CARD_CLASS]: isOwner }"
  >
    <div
      class="relative h-24 bg-cover bg-center"
      :class="profile.cover_picture_url ? '' : 'bg-gradient-to-r from-primary-500 to-primary-700'"
      :style="profile.cover_picture_url ? { backgroundImage: `url(${profile.cover_picture_url})` } : {}"
    >
      <button
        type="button"
        class="lg:hidden absolute top-3 left-3 bg-black/50 hover:bg-black/70 text-white rounded-full p-2 transition-colors cursor-pointer"
        :aria-label="t('profile.common.back')"
        @click="emit('back')"
      >
        <i class="pi pi-arrow-left" aria-hidden="true" />
      </button>
      <div v-if="isOwner" class="absolute top-3 right-3 flex gap-2">
        <button
          v-if="profile.cover_picture_url"
          type="button"
          :class="COVER_BUTTON_CLASS"
          :title="t('profile.owner.cover.delete')"
          :aria-label="t('profile.owner.cover.delete')"
          @click="emit('delete-cover')"
        >
          <i class="pi pi-trash" aria-hidden="true" />
        </button>
        <button
          type="button"
          :class="COVER_BUTTON_CLASS"
          :title="t('profile.public.edit_cover')"
          :aria-label="t('profile.public.edit_cover')"
          @click="emit('edit-cover')"
        >
          <i class="pi pi-camera" aria-hidden="true" />
        </button>
      </div>
    </div>

    <div class="relative px-5 pb-5 flex flex-col items-center text-center gap-3">
      <ProfileOwnerPencil v-if="isOwner" :label="t('profile.public.edit_profile')" @edit="emit('edit-profile')" />
      <div class="relative -mt-12">
        <img
          v-if="profile.profile_picture_url"
          :src="profile.profile_picture_url"
          :alt="profile.username"
          class="w-24 h-24 rounded-full border-4 border-surface-0 dark:border-surface-900 object-cover cursor-pointer hover:opacity-90 transition-opacity"
          @click="emit('view-picture')"
        />
        <div
          v-else
          class="w-24 h-24 rounded-full border-4 border-surface-0 dark:border-surface-900 flex items-center justify-center text-4xl font-bold"
          :style="getAvatarStyle(profile.username)"
        >
          {{ profile.username.charAt(0).toUpperCase() }}
        </div>
        <button
          v-if="isOwner"
          type="button"
          class="absolute bottom-0 right-0 bg-primary-500 hover:bg-primary-600 text-white rounded-full p-1.5 transition-colors cursor-pointer"
          :title="t('profile.public.edit_picture')"
          :aria-label="t('profile.public.edit_picture')"
          @click="emit('edit-picture')"
        >
          <i class="pi pi-camera text-xs" aria-hidden="true" />
        </button>
      </div>

      <div class="flex flex-col gap-0.5">
        <h1 class="m-0 text-xl font-extrabold leading-tight text-surface-900 dark:text-surface-0 break-words">
          {{ profile.display_name || profile.username }}
        </h1>
        <span class="text-sm text-surface-500 dark:text-surface-400">@{{ profile.username }}</span>
      </div>

      <p v-if="profile.bio" class="m-0 text-sm text-surface-600 dark:text-surface-300 whitespace-pre-line break-words">
        {{ profile.bio }}
      </p>

      <div class="flex flex-wrap justify-center gap-x-4 gap-y-1 text-[13px] text-surface-600 dark:text-surface-300">
        <span v-if="profile.location" class="inline-flex items-center gap-1.5">
          <i class="pi pi-map-marker text-xs text-surface-400" aria-hidden="true" />{{ profile.location }}
        </span>
        <span v-if="memberSinceYear" class="inline-flex items-center gap-1.5">
          <i class="pi pi-calendar text-xs text-surface-400" aria-hidden="true" />{{ t('profile.public.member_since', { year: memberSinceYear }) }}
        </span>
      </div>

      <div class="flex w-full items-center gap-2">
        <Button
          v-if="isOwner"
          ref="viewAsVisitorButton"
          :label="t('profile.owner.view_as_visitor')"
          icon="pi pi-eye"
          severity="secondary"
          outlined
          class="flex-1"
          @click="emit('view-as-visitor')"
        />
        <!-- Shown to the owner looking as a visitor, who cannot write to themselves. -->
        <Button
          v-else-if="canContact || previewing"
          :label="t('profile.common.contact')"
          icon="pi pi-envelope"
          severity="info"
          class="flex-1"
          :disabled="previewing"
          @click="emit('contact')"
        />
        <ReportButton
          target-type="user"
          :target-id="profile.user_id"
          :author-id="profile.user_id"
          :aria-label="t('profile.public.report', { username: profile.username })"
          icon-only
        />
        <BlockUserButton :user-id="profile.user_id" :username="profile.username" icon-only />
      </div>
    </div>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { getAvatarStyle } from '../../../utils/avatar.js'
import { OWNER_CARD_CLASS } from '../../../utils/profileOwner.js'
import ReportButton from '../../Report/ReportButton.vue'
import BlockUserButton from '../../User/Block/BlockUserButton.vue'
import ProfileOwnerPencil from './ProfileOwnerPencil.vue'

/** Who the member is (#1317): the top of the profile's left column, and the page header on a phone. */
const props = defineProps({
  profile: { type: Object, required: true },
  /** The owner's tools: camera buttons, the pencil, « Voir comme un visiteur ». */
  isOwner: { type: Boolean, default: false },
  canContact: { type: Boolean, default: false },
  /** The owner looking at their profile as a visitor would. */
  previewing: { type: Boolean, default: false }
})

const emit = defineEmits([
  'back',
  'contact',
  'edit-profile',
  'view-as-visitor',
  'edit-cover',
  'delete-cover',
  'edit-picture',
  'view-picture'
])
const { t } = useI18n()
const COVER_BUTTON_CLASS =
  'bg-black/50 hover:bg-black/70 text-white rounded-full p-2 transition-colors cursor-pointer'

const memberSinceYear = computed(() =>
  props.profile.member_since ? new Date(props.profile.member_since).getFullYear() : null
)

const viewAsVisitorButton = ref(null)

// Back from the visitor view, focus returns to the button that opened it.
defineExpose({ focusViewAsVisitor: () => viewAsVisitorButton.value?.$el?.focus() })
</script>
