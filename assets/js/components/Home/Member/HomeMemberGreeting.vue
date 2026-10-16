<template>
  <section class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5">
    <div class="flex flex-col gap-1.5">
      <h1 class="m-0 text-3xl lg:text-4xl font-extrabold text-surface-900 dark:text-surface-0">
        {{ greeting }}
      </h1>
      <p class="m-0 text-lg text-surface-700 dark:text-surface-300">
        {{ hasBand ? t('home.member.intro_band') : t('home.member.intro_search') }}
      </p>
    </div>
    <div class="flex flex-wrap gap-2.5">
      <Button :label="t('home.member.post_announce')" icon="pi pi-plus" severity="info" @click="$emit('post-announce')" />
      <Button :label="t('home.member.post_discovery')" icon="pi pi-plus" severity="secondary" outlined @click="$emit('post-discovery')" />
      <!-- A topic belongs to a forum, so this opens the forum list for now (#1080). -->
      <Button
        as="router-link"
        :to="{ name: 'app_forum_index' }"
        :label="t('home.member.new_topic')"
        icon="pi pi-plus"
        severity="secondary"
        outlined
      />
    </div>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { greetingFor } from '../../../utils/memberHome.js'

defineProps({
  /** Whether the member already has a Band Space, which is what the page talks about first. */
  hasBand: { type: Boolean, default: false }
})

defineEmits(['post-announce', 'post-discovery'])

const { t } = useI18n()
const userSecurityStore = useUserSecurityStore()

// Read once: a greeting that flipped from « Bonjour » to « Bonsoir » under the reader would be odd.
const greetedAt = new Date()
const name = computed(() => userSecurityStore.user?.username ?? '')
const greeting = computed(() => greetingFor(greetedAt, name.value))
</script>
