<template>
  <!-- The wall's « Voir l'annonce » scrolls here (#1316): announces have no page of their own. -->
  <section
    v-if="announces.length || isOwner"
    id="annonces"
    class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 scroll-mt-20 lg:scroll-mt-6"
    aria-labelledby="profile-announces-title"
  >
    <h2 id="profile-announces-title" class="m-0 mb-3 text-[15px] font-bold text-surface-900 dark:text-surface-0">
      {{ t('profile.public.announces') }}
    </h2>

    <div v-if="!announces.length" class="flex flex-col items-center py-4 text-center gap-3">
      <span class="flex items-center justify-center w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-900/30" aria-hidden="true">
        <i class="pi pi-megaphone text-xl text-primary-600 dark:text-primary-400" />
      </span>
      <p class="m-0 text-sm text-surface-500 dark:text-surface-400">{{ t('profile.public.announces_empty') }}</p>
      <Button
        :label="t('profile.public.add_announce')"
        icon="pi pi-plus"
        size="small"
        @click="$router.push({ name: 'app_user_announces' })"
      />
    </div>
    <div v-else class="flex flex-col gap-3">
      <MusicianAnnounceItem
        v-for="announce in announces"
        :key="announce.id"
        :announce="announce"
        :contactable="contactable"
        :compact="compact"
        @contact="emit('contact', $event)"
      />
    </div>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import { useI18n } from 'vue-i18n'
import MusicianAnnounceItem from '../../User/Profile/MusicianAnnounceItem.vue'

/** The member's announces, each open to « Contacter » about it (#1317). */
defineProps({
  announces: { type: Array, default: () => [] },
  isOwner: { type: Boolean, default: false },
  /** False on the member's own profile, viewed as a visitor too: nobody writes to themselves. */
  contactable: { type: Boolean, default: true },
  compact: { type: Boolean, default: false }
})

const emit = defineEmits(['contact'])
const { t } = useI18n()
</script>
