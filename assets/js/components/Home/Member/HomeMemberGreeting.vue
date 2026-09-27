<template>
  <section class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5">
    <div class="flex flex-col gap-1.5">
      <h1 class="m-0 text-3xl lg:text-4xl font-extrabold text-surface-900 dark:text-surface-0">
        {{ greeting }}, {{ name }}
      </h1>
      <p class="m-0 text-lg text-surface-700 dark:text-surface-300">
        {{ hasBand ? 'Voici ce qui bouge pour vous et votre groupe.' : 'Trouvez les musiciens et les groupes qui vous correspondent.' }}
      </p>
    </div>
    <div class="flex flex-wrap gap-2.5">
      <Button label="Poster une annonce" icon="pi pi-plus" severity="info" @click="$emit('post-announce')" />
      <Button label="Poster une découverte" icon="pi pi-plus" severity="secondary" outlined @click="$emit('post-discovery')" />
      <!-- A topic belongs to a forum, so this opens the forum list for now (#1080). -->
      <Button
        as="router-link"
        :to="{ name: 'app_forum_index' }"
        label="Nouveau sujet"
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
import { useUserSecurityStore } from '../../../store/user/security.js'
import { greetingFor } from '../../../utils/memberHome.js'

defineProps({
  /** Whether the member already has a Band Space, which is what the page talks about first. */
  hasBand: { type: Boolean, default: false }
})

defineEmits(['post-announce', 'post-discovery'])

const userSecurityStore = useUserSecurityStore()

// Read once: a greeting that flipped from « Bonjour » to « Bonsoir » under the reader would be odd.
const greeting = greetingFor(new Date())
const name = computed(() => userSecurityStore.user?.username ?? '')
</script>
