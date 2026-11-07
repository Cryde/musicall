<template>
  <section
    v-if="content"
    class="relative bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5"
    :class="{ [OWNER_CARD_CLASS]: isOwner }"
    aria-labelledby="profile-links-title"
  >
    <ProfileOwnerPencil v-if="isOwner" :label="t('profile.public.edit_social_links')" @edit="emit('edit')" />
    <h2 id="profile-links-title" class="m-0 mb-3 text-[15px] font-bold text-surface-900 dark:text-surface-0">
      {{ t('profile.public.social_links') }}
    </h2>
    <div v-if="content === 'items'" class="flex flex-wrap gap-2">
      <a
        v-for="link in links"
        :key="link.url"
        :href="link.url"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm bg-surface-100 dark:bg-surface-800 hover:bg-surface-200 dark:hover:bg-surface-700 transition-colors text-surface-700 dark:text-surface-300"
        :aria-label="t('profile.public.visit', { platform: link.platform_label })"
      >
        <i :class="['pi', socialPlatformIcon(link.platform)]" aria-hidden="true" />
        <span>{{ link.platform_label }}</span>
      </a>
    </div>
    <p v-else class="m-0 text-sm text-surface-500 dark:text-surface-400">
      {{ t('profile.public.social_links_empty') }}
    </p>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { OWNER_CARD_CLASS, ownerCardContent } from '../../../utils/profileOwner.js'
import { socialPlatformIcon } from '../../../utils/socialLinks.js'
import ProfileOwnerPencil from './ProfileOwnerPencil.vue'

/** The member's links elsewhere (#1317); the owner edits them from here. */
const props = defineProps({
  links: { type: Array, default: () => [] },
  isOwner: { type: Boolean, default: false }
})

const emit = defineEmits(['edit'])
const { t } = useI18n()
const content = computed(() => ownerCardContent(props.links, props.isOwner))
</script>
