<template>
  <section
    v-if="links.length || isOwner"
    class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5"
    aria-labelledby="profile-links-title"
  >
    <div class="flex items-center justify-between mb-3">
      <h2 id="profile-links-title" class="m-0 text-[15px] font-bold text-surface-900 dark:text-surface-0">
        {{ t('profile.public.social_links') }}
      </h2>
      <Button
        v-if="isOwner"
        icon="pi pi-pencil"
        text
        rounded
        size="small"
        :aria-label="t('profile.public.edit_social_links')"
        @click="emit('edit')"
      />
    </div>
    <div v-if="links.length" class="flex flex-wrap gap-2">
      <a
        v-for="link in links"
        :key="link.url"
        :href="link.url"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm bg-surface-100 dark:bg-surface-800 hover:bg-surface-200 dark:hover:bg-surface-700 transition-colors text-surface-700 dark:text-surface-300"
        :aria-label="t('profile.public.visit', { platform: link.platform_label })"
      >
        <i :class="['pi', platformIcon(link.platform)]" aria-hidden="true" />
        <span>{{ link.platform_label }}</span>
      </a>
    </div>
    <p v-else class="m-0 text-sm text-surface-500 dark:text-surface-400">
      {{ t('profile.public.social_links_empty') }}
    </p>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import { useI18n } from 'vue-i18n'

/** The member's links elsewhere (#1317); the owner edits them from here. */
defineProps({
  links: { type: Array, default: () => [] },
  isOwner: { type: Boolean, default: false }
})

const emit = defineEmits(['edit'])
const { t } = useI18n()

const PLATFORM_ICONS = {
  youtube: 'pi-youtube',
  soundcloud: 'pi-cloud',
  instagram: 'pi-instagram',
  facebook: 'pi-facebook',
  twitter: 'pi-twitter',
  tiktok: 'pi-video',
  spotify: 'pi-spotify',
  bandcamp: 'pi-headphones',
  website: 'pi-globe'
}

function platformIcon(platform) {
  return PLATFORM_ICONS[platform] || 'pi-link'
}
</script>
