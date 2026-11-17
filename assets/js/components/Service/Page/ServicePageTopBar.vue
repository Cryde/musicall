<template>
  <!-- The way back: to the profile when the visitor came from it, otherwise into the kind's search. -->
  <div class="flex items-center gap-2 text-sm text-surface-500 dark:text-surface-400 min-h-10">
    <Button
      icon="pi pi-arrow-left"
      severity="secondary"
      text
      rounded
      class="md:hidden! -ml-2"
      :aria-label="t('profile.common.back')"
      @click="emit('back')"
    />
    <router-link
      v-if="cameFromProfile"
      :to="{ name: 'app_user_public_profile', params: { username } }"
      class="inline-flex items-center gap-2 hover:text-primary max-md:hidden"
    >
      <i class="pi pi-arrow-left text-xs" aria-hidden="true" />{{ backToProfileLabel }}
    </router-link>
    <nav v-else :aria-label="breadcrumbLabel" class="max-md:hidden">
      <ol class="flex items-center gap-2">
        <template v-for="(crumb, index) in crumbs" :key="index">
          <li v-if="index === 0" :aria-current="crumb.current ? 'page' : undefined">
            <router-link v-if="crumb.to" :to="crumb.to" class="hover:text-primary">{{ crumb.label }}</router-link>
            <template v-else>{{ crumb.label }}</template>
          </li>
          <li v-else class="flex items-center gap-2" :aria-current="crumb.current ? 'page' : undefined">
            <i class="pi pi-chevron-right text-[10px]" aria-hidden="true" /><router-link
              v-if="crumb.to"
              :to="crumb.to"
              class="hover:text-primary"
            >{{ crumb.label }}</router-link><template v-else>{{ crumb.label }}</template>
          </li>
        </template>
      </ol>
    </nav>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { useI18n } from 'vue-i18n'

defineProps({
  username: { type: String, required: true },
  cameFromProfile: { type: Boolean, required: true },
  backToProfileLabel: { type: String, required: true },
  breadcrumbLabel: { type: String, required: true },
  /** `{ label, to?, current? }`, the kind's search first. */
  crumbs: { type: Array, required: true }
})

const emit = defineEmits(['back'])
const { t } = useI18n()
</script>
