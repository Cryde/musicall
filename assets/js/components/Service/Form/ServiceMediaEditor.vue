<template>
  <ServiceFormSection icon="pi pi-video" :title="t('profile.service_form.media')" class="gap-3">
    <ul v-if="media.length" class="flex flex-col gap-2">
      <li v-for="item in media" :key="item.id" class="flex items-center justify-between gap-3 p-3 bg-surface-50 dark:bg-surface-800 rounded-lg">
        <span class="min-w-0 truncate text-surface-900 dark:text-surface-0">{{ item.title || item.url }}</span>
        <Button icon="pi pi-trash" severity="danger" text rounded :aria-label="t('profile.service_form.remove_media', { title: item.title || item.url })" :disabled="isBusy" @click="remove(item)" />
      </li>
    </ul>
    <div class="flex flex-col sm:flex-row gap-2">
      <InputText v-model="url" type="url" class="flex-1" :placeholder="urlLabel" :aria-label="urlLabel" :disabled="isBusy" />
      <Button :label="t('profile.service_form.add_media')" icon="pi pi-plus" severity="secondary" :loading="isBusy" :disabled="!url.trim()" @click="add" />
    </div>
  </ServiceFormSection>
</template>

<script setup>
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { useToast } from 'primevue/usetoast'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import serviceMediaApi from '../../../api/service/serviceMedia.js'
import ServiceFormSection from './ServiceFormSection.vue'

/** A service's media in its form (#1410, #1411): each one added and removed at once. */
const props = defineProps({
  serviceId: { type: String, required: true },
  /** Names the platforms the kind takes. */
  urlLabel: { type: String, required: true }
})
/** `changed` whenever a media was written, for the page to refresh once the form closes. */
const emit = defineEmits(['changed'])
const media = defineModel('media', { type: Array, required: true })

const { t } = useI18n()
const toast = useToast()

const url = ref('')
const isBusy = ref(false)

/** One action at a time; a refusal is shown and leaves the list as it was. */
async function whileBusy(action) {
  isBusy.value = true
  try {
    await action()
    emit('changed')
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 6000 })
  } finally {
    isBusy.value = false
  }
}

function add() {
  return whileBusy(async () => {
    const added = await serviceMediaApi.addMedia(props.serviceId, { url: url.value.trim() })
    media.value = [...media.value, added]
    url.value = ''
  })
}

function remove(item) {
  return whileBusy(async () => {
    await serviceMediaApi.deleteMedia(props.serviceId, item.id)
    media.value = media.value.filter((other) => other.id !== item.id)
  })
}
</script>
