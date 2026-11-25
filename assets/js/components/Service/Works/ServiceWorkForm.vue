<template>
  <form class="flex flex-col gap-4" @submit.prevent="save">
    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <div class="grid gap-4 md:grid-cols-2">
      <div class="flex flex-col gap-2">
        <label :for="`${idPrefix}-title`" :class="LABEL_CLASS">{{ t('profile.service_works.title') }}</label>
        <InputText :id="`${idPrefix}-title`" v-model="form.title" :maxlength="120" :placeholder="t('profile.service_works.title_placeholder')" :disabled="isSaving" required />
      </div>
      <div class="flex flex-col gap-2">
        <label :for="`${idPrefix}-credit`" :class="LABEL_CLASS">{{ t('profile.service_works.credit') }}</label>
        <InputText :id="`${idPrefix}-credit`" v-model="form.credit" :maxlength="160" :placeholder="t('profile.service_works.credit_placeholder')" :disabled="isSaving" />
      </div>
      <div class="flex flex-col gap-2">
        <label :for="`${idPrefix}-year`" :class="LABEL_CLASS">{{ t('profile.service_works.year') }}</label>
        <InputNumber v-model="form.year" fluid :input-id="`${idPrefix}-year`" :use-grouping="false" :min="1900" :max="2100" :disabled="isSaving" />
      </div>
      <div class="flex flex-col gap-2">
        <label :for="`${idPrefix}-link`" :class="LABEL_CLASS">{{ t('profile.service_works.link') }}</label>
        <InputText :id="`${idPrefix}-link`" v-model="form.link_url" type="url" :maxlength="500" placeholder="https://" :disabled="isSaving" />
      </div>
    </div>
    <div class="flex flex-col gap-2">
      <label :for="`${idPrefix}-media`" :class="LABEL_CLASS">{{ t(mediaLabelKey) }}</label>
      <InputText :id="`${idPrefix}-media`" v-model="form.media_url" type="url" :maxlength="500" placeholder="https://" :disabled="isSaving" />
    </div>

    <!-- Images: sent one by one as soon as chosen, so only once the work exists -->
    <fieldset v-if="work" class="flex flex-col gap-2">
      <legend :class="[LABEL_CLASS, 'mb-2']">{{ t('profile.service_works.images') }}</legend>
      <ul v-if="work.images.length" class="flex flex-wrap gap-3">
        <li v-for="(image, index) in work.images" :key="image.id" class="flex flex-col items-center gap-1">
          <img :src="image.url" :alt="t('profile.service_works.image_alt', { number: index + 1, title: work.title })" class="size-20 rounded-md object-cover" loading="lazy" />
          <div class="flex">
            <Button icon="pi pi-arrow-left" text rounded size="small" severity="secondary" :aria-label="t('profile.service_works.move_image_before', { number: index + 1 })" :disabled="isImageBusy || index === 0" @click="moveImage(index, -1)" />
            <Button icon="pi pi-trash" text rounded size="small" severity="danger" :aria-label="t('profile.service_works.remove_image', { number: index + 1 })" :disabled="isImageBusy" @click="removeImage(image)" />
            <Button icon="pi pi-arrow-right" text rounded size="small" severity="secondary" :aria-label="t('profile.service_works.move_image_after', { number: index + 1 })" :disabled="isImageBusy || index === work.images.length - 1" @click="moveImage(index, 1)" />
          </div>
        </li>
      </ul>
      <label v-if="work.images.length < MAX_WORK_IMAGES" class="w-fit">
        <span class="sr-only">{{ t('profile.service_works.add_images') }}</span>
        <input type="file" multiple :accept="WORK_IMAGE_ACCEPT" class="text-sm" :disabled="isImageBusy" @change="uploadImages" />
      </label>
      <p v-if="isImageBusy" class="text-sm text-surface-500 dark:text-surface-400"><i class="pi pi-spin pi-spinner mr-1" aria-hidden="true" />{{ t('profile.service_works.uploading') }}</p>
      <small class="text-surface-500 dark:text-surface-400">{{ t('profile.service_works.images_help', { max: MAX_WORK_IMAGES }) }}</small>
      <small class="text-surface-500 dark:text-surface-400">{{ t('profile.service_works.images_rights') }}</small>
    </fieldset>
    <p v-else class="text-sm text-surface-500 dark:text-surface-400">{{ t('profile.service_works.images_after_save') }}</p>

    <div class="flex justify-end gap-2">
      <Button type="button" :label="t('profile.service_works.cancel')" severity="secondary" text :disabled="isSaving" @click="emit('cancel')" />
      <Button type="submit" :label="t('profile.service_works.save')" icon="pi pi-check" :loading="isSaving" :disabled="!form.title.trim()" />
    </div>
  </form>
</template>

<script setup>
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import { useToast } from 'primevue/usetoast'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import serviceWorkApi from '../../../api/service/serviceWork.js'
import { movedIds, orderedBy } from '../../../utils/listOrder.js'
import {
  MAX_WORK_IMAGES,
  WORK_IMAGE_ACCEPT,
  workForm,
  workPayload
} from '../../../utils/serviceWorks.js'

/** One work of « Réalisations » (#1407): its fields, saved together, and its images, each sent at once. */
const props = defineProps({
  serviceId: { type: String, required: true },
  /** Null for a new work. */
  work: { type: Object, default: null },
  /** The platforms the embed takes, as the kind names them (ServiceWorksEditor's default). */
  mediaLabelKey: { type: String, required: true }
})
/** `saved` with the work as stored, `changed` with it once an image was added, moved or removed. */
const emit = defineEmits(['saved', 'changed', 'cancel'])

const LABEL_CLASS = 'font-medium text-surface-900 dark:text-surface-0'

const { t } = useI18n()
const toast = useToast()

const idPrefix = `work-${props.work?.id ?? 'new'}`
const form = ref(workForm(props.work))
const isSaving = ref(false)
const isImageBusy = ref(false)
const error = ref('')

async function save() {
  isSaving.value = true
  error.value = ''
  try {
    const payload = workPayload(form.value)
    const saved = props.work
      ? await serviceWorkApi.updateWork(props.serviceId, props.work.id, payload)
      : await serviceWorkApi.createWork(props.serviceId, payload)
    emit('saved', saved)
  } catch (e) {
    error.value = e.message
  } finally {
    isSaving.value = false
  }
}

/** One image action at a time; a refusal is shown and leaves the images as they were. */
async function whileImageBusy(action) {
  isImageBusy.value = true
  try {
    await action()
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 6000 })
  } finally {
    isImageBusy.value = false
  }
}

/** One at a time, in the order picked, stopping at the first refusal: the rest would be refused too. */
function uploadImages(event) {
  const files = [...(event.target.files ?? [])].slice(0, MAX_WORK_IMAGES - props.work.images.length)
  event.target.value = ''
  if (!files.length) return
  return whileImageBusy(async () => {
    let images = props.work.images
    for (const file of files) {
      const image = await serviceWorkApi.uploadImage(props.serviceId, props.work.id, file)
      images = [...images, { id: image.id, url: image.url }]
      emit('changed', { ...props.work, images })
    }
  })
}

function removeImage(image) {
  return whileImageBusy(async () => {
    await serviceWorkApi.deleteImage(props.serviceId, props.work.id, image.id)
    emit('changed', {
      ...props.work,
      images: props.work.images.filter((item) => item.id !== image.id)
    })
  })
}

function moveImage(index, step) {
  const images = props.work.images
  const ids = movedIds(
    images.map((item) => item.id),
    images[index].id,
    step
  )
  if (!ids) return
  return whileImageBusy(async () => {
    await serviceWorkApi.reorderImages(props.serviceId, props.work.id, ids)
    emit('changed', { ...props.work, images: orderedBy(images, ids) })
  })
}
</script>
