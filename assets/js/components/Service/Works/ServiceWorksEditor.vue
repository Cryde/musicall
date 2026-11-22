<template>
  <ServiceFormSection icon="pi pi-star" :title="t('profile.service_works.section')" class="gap-3">
    <p class="text-sm text-surface-600 dark:text-surface-400">{{ t(helpKey, { max: MAX_WORKS }) }}</p>

    <ul v-if="works.length" class="flex flex-col gap-2">
      <li v-for="(work, index) in works" :key="work.id" class="p-3 bg-surface-50 dark:bg-surface-800 rounded-lg">
        <ServiceWorkForm
          v-if="editingId === work.id"
          :service-id="serviceId"
          :media-label-key="mediaLabelKey"
          :work="work"
          @saved="saved"
          @changed="replace"
          @cancel="editingId = null"
        />
        <div v-else class="flex items-center gap-3">
          <img v-if="work.images.length" :src="work.images[0].url" alt="" class="size-12 shrink-0 rounded-md object-cover" loading="lazy" />
          <span v-else class="size-12 shrink-0 rounded-md bg-surface-200 dark:bg-surface-700 flex items-center justify-center">
            <i class="pi pi-image text-surface-400" aria-hidden="true" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="font-medium text-surface-900 dark:text-surface-0 truncate">{{ work.title }}</p>
            <p v-if="workByline(work)" class="text-sm text-surface-500 dark:text-surface-400 truncate">{{ workByline(work) }}</p>
          </div>
          <div class="flex shrink-0">
            <Button icon="pi pi-arrow-up" text rounded severity="secondary" :aria-label="t('profile.service_works.move_up', { title: work.title })" :disabled="isBusy || index === 0" @click="move(index, -1)" />
            <Button icon="pi pi-arrow-down" text rounded severity="secondary" :aria-label="t('profile.service_works.move_down', { title: work.title })" :disabled="isBusy || index === works.length - 1" @click="move(index, 1)" />
            <Button icon="pi pi-pencil" text rounded severity="secondary" :aria-label="t('profile.service_works.edit', { title: work.title })" :disabled="isBusy" @click="editingId = work.id" />
            <Button icon="pi pi-trash" text rounded severity="danger" :aria-label="t('profile.service_works.remove', { title: work.title })" :disabled="isBusy" @click="confirmRemove(work)" />
          </div>
        </div>
      </li>
    </ul>

    <div v-if="editingId === NEW" class="p-3 bg-surface-50 dark:bg-surface-800 rounded-lg">
      <ServiceWorkForm :service-id="serviceId" :media-label-key="mediaLabelKey" @saved="added" @cancel="editingId = null" />
    </div>
    <Button
      v-else-if="works.length < MAX_WORKS"
      :label="t('profile.service_works.add')"
      icon="pi pi-plus"
      severity="secondary"
      size="small"
      class="w-fit"
      :disabled="isBusy"
      @click="editingId = NEW"
    />
    <p v-else class="text-sm text-surface-500 dark:text-surface-400">{{ t('profile.service_works.limit_reached', { max: MAX_WORKS }) }}</p>
  </ServiceFormSection>
</template>

<script setup>
import Button from 'primevue/button'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import serviceWorkApi from '../../../api/service/serviceWork.js'
import { movedIds, orderedBy } from '../../../utils/listOrder.js'
import { MAX_WORKS, workByline } from '../../../utils/serviceWorks.js'
import ServiceFormSection from '../Form/ServiceFormSection.vue'
import ServiceWorkForm from './ServiceWorkForm.vue'

/** « Réalisations » in a service's form (#1407): every change is sent at once, as « Avant, après » is. */
const props = defineProps({
  serviceId: { type: String, required: true },
  /** What the kind's works are, with `{max}`: records and projects unless said otherwise (#1411). */
  helpKey: { type: String, default: 'profile.service_works.help' },
  /** The platforms a work's embed takes, as the kind names them. */
  mediaLabelKey: { type: String, default: 'profile.service_works.media_url' }
})
/** `changed` whenever a work or an image was written, for the page to refresh once the form closes. */
const emit = defineEmits(['changed'])
const works = defineModel('works', { type: Array, required: true })

/** The id the form of a work not saved yet is open under. */
const NEW = 'new'

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const editingId = ref(null)
const isBusy = ref(false)

function replace(work) {
  works.value = works.value.map((item) => (item.id === work.id ? work : item))
  emit('changed')
}

function saved(work) {
  replace(work)
  editingId.value = null
}

/** Its form stays open on the stored work, which can now take images. */
function added(work) {
  works.value = [...works.value, work]
  editingId.value = work.id
  emit('changed')
}

/** One list action at a time; a refusal is shown and leaves the list as it was. */
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

function move(index, step) {
  const ids = movedIds(
    works.value.map((item) => item.id),
    works.value[index].id,
    step
  )
  if (!ids) return
  return whileBusy(async () => {
    await serviceWorkApi.reorderWorks(props.serviceId, ids)
    works.value = orderedBy(works.value, ids)
  })
}

function confirmRemove(work) {
  confirm.require({
    message: t('profile.service_works.remove_confirm', { title: work.title }),
    header: t('profile.service_works.remove', { title: work.title }),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('profile.service_works.remove_accept'),
    rejectLabel: t('profile.service_works.cancel'),
    acceptClass: 'p-button-danger',
    accept: () => remove(work)
  })
}

function remove(work) {
  return whileBusy(async () => {
    await serviceWorkApi.deleteWork(props.serviceId, work.id)
    works.value = works.value.filter((item) => item.id !== work.id)
  })
}
</script>
