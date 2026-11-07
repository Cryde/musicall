<template>
  <section class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5 max-sm:flex-row max-sm:items-center max-sm:justify-between max-sm:gap-3">
    <div class="flex flex-col gap-1.5">
      <h1 class="m-0 text-[1.75rem] sm:text-3xl lg:text-4xl font-semibold tracking-tight text-surface-900 dark:text-surface-0">
        {{ t('home.member.title') }}
      </h1>
      <p class="max-sm:hidden m-0 text-lg text-surface-600 dark:text-surface-300">
        {{ hasBand ? t('home.member.intro_band') : t('home.member.intro_search') }}
      </p>
    </div>
    <!-- On a phone the three actions wait behind one button, beside the title rather than three rows below it. -->
    <template v-if="isPhone">
      <Button
        :label="t('home.member.publish')"
        icon="pi pi-plus"
        severity="info"
        size="small"
        aria-haspopup="menu"
        :aria-expanded="isPublishMenuOpen"
        class="shrink-0"
        @click="publishMenu.toggle($event)"
      />
      <!-- PrimeVue's popup does not tell its button whether it is open, so the state is kept here. -->
      <Menu ref="publishMenu" :model="publishItems" popup @show="isPublishMenuOpen = true" @hide="isPublishMenuOpen = false" />
    </template>
    <div v-else class="flex flex-wrap gap-2.5">
      <Button :label="t('home.member.post_announce')" icon="pi pi-plus" severity="info" @click="$emit('post-announce')" />
      <Button :label="t('home.member.post_discovery')" icon="pi pi-plus" severity="secondary" outlined @click="$emit('post-discovery')" />
      <Button :label="t('home.member.new_topic')" icon="pi pi-plus" severity="secondary" outlined @click="$emit('new-topic')" />
    </div>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import { computed, ref, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useIsPhone } from '../../../composables/useIsPhone.js'

defineProps({
  /** Whether the member already has a Band Space, which is what the page talks about first. */
  hasBand: { type: Boolean, default: false }
})

const emit = defineEmits(['post-announce', 'post-discovery', 'new-topic'])

const { t } = useI18n()
const isPhone = useIsPhone()

const publishMenu = useTemplateRef('publishMenu')
const isPublishMenuOpen = ref(false)
const publishItems = computed(() => [
  {
    label: t('home.member.publish_menu.announce'),
    icon: 'pi pi-megaphone',
    command: () => emit('post-announce')
  },
  {
    label: t('home.member.publish_menu.discovery'),
    icon: 'pi pi-video',
    command: () => emit('post-discovery')
  },
  {
    label: t('home.member.publish_menu.topic'),
    icon: 'pi pi-comments',
    command: () => emit('new-topic')
  }
])
</script>
