<template>
  <div class="py-6 md:py-10 flex flex-col gap-6">
    <div class="flex flex-col gap-2">
      <h1 class="m-0 text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('profile.services.title') }}
      </h1>
      <p class="m-0 text-surface-500 dark:text-surface-400">
        {{ t('profile.services.subtitle') }}
      </p>
    </div>

    <div v-if="isLoading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl" aria-hidden="true" />
    </div>

    <template v-else>
      <ul v-if="services.length" class="m-0 p-0 list-none flex flex-col gap-4">
        <li
          v-for="service in services"
          :key="service.id"
          class="flex flex-col md:flex-row md:items-center gap-4 rounded-xl bg-surface-0 dark:bg-surface-900 shadow-sm p-4 md:p-6"
        >
          <div class="flex items-start gap-4 flex-1 min-w-0">
            <span :class="[ICON_CLASS, SERVICE_KINDS[service.kind].iconClass]">
              <i :class="[SERVICE_KINDS[service.kind].icon, 'text-lg']" aria-hidden="true" />
            </span>
            <div class="flex-1 min-w-0 flex flex-col gap-2">
              <div class="flex flex-wrap items-center gap-2">
                <h2 class="m-0 text-base font-semibold text-surface-900 dark:text-surface-0 break-words">
                  {{ service.headline || t(SERVICE_KINDS[service.kind].labelKey) }}
                </h2>
                <Tag
                  :value="service.status === 'published' ? t('profile.services.status.published') : t('profile.services.status.draft')"
                  :severity="service.status === 'published' ? 'success' : 'warn'"
                />
              </div>
              <div
                class="h-2 w-full max-w-80 rounded-full bg-surface-200 dark:bg-surface-700 overflow-hidden"
                role="progressbar"
                :aria-valuenow="service.completeness"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="t('profile.services.completeness', { percent: service.completeness })"
              >
                <span class="block h-full bg-green-600 dark:bg-green-500" :style="{ width: `${service.completeness}%` }" />
              </div>
              <p class="m-0 text-sm text-surface-600 dark:text-surface-400">{{ cardHint(service) }}</p>
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-2 shrink-0">
            <Button
              v-if="serviceStage(service) === 'ready'"
              :label="t('profile.services.publish')"
              icon="pi pi-check"
              severity="success"
              :loading="busyServiceId === service.id"
              @click="publish(service)"
            />
            <Button
              as="router-link"
              :to="SERVICE_KINDS[service.kind].page(username)"
              :label="t('profile.services.view')"
              icon="pi pi-external-link"
              severity="secondary"
              outlined
            />
            <Button
              :label="t('profile.services.edit')"
              icon="pi pi-pencil"
              :loading="teacherProfileStore.isLoading"
              @click="openTeachingForm"
            />
            <Button
              icon="pi pi-ellipsis-v"
              severity="secondary"
              text
              rounded
              aria-haspopup="true"
              :aria-label="t('profile.services.more')"
              @click="openMenu($event, service)"
            />
          </div>
        </li>
      </ul>
      <Menu ref="menuRef" :model="menuItems" popup />

      <section v-if="addableKinds.length" class="flex flex-col gap-4" aria-labelledby="services-add-title">
        <h2 id="services-add-title" class="m-0 text-lg font-semibold text-surface-900 dark:text-surface-0">
          {{ t('profile.services.add_title') }}
        </h2>
        <ul class="m-0 p-0 list-none grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
          <li
            v-for="kind in addableKinds"
            :key="kind"
            class="flex flex-col items-start gap-2 rounded-xl bg-surface-0 dark:bg-surface-900 shadow-sm p-5"
          >
            <span :class="[ICON_CLASS, 'mb-2', SERVICE_KINDS[kind].iconClass]">
              <i :class="[SERVICE_KINDS[kind].icon, 'text-lg']" aria-hidden="true" />
            </span>
            <h3 class="m-0 text-base font-semibold text-surface-900 dark:text-surface-0">{{ t(SERVICE_KINDS[kind].labelKey) }}</h3>
            <p class="m-0 text-sm text-surface-600 dark:text-surface-400 flex-1">{{ t(SERVICE_KINDS[kind].descriptionKey) }}</p>
            <Button
              :label="t('profile.services.add')"
              icon="pi pi-plus"
              text
              class="-ml-3"
              :loading="addingKind === kind"
              :aria-label="t('profile.services.add_kind', { kind: t(SERVICE_KINDS[kind].labelKey) })"
              @click="add(kind)"
            />
          </li>
        </ul>
      </section>
    </template>

    <EditTeacherProfileModal
      v-model:visible="showTeachingForm"
      :teacher-profile="teacherProfileStore.profile"
      @saved="handleTeachingSaved"
    />
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import servicesApi from '../../../api/user/services.js'
import EditTeacherProfileModal from '../../../components/Teacher/EditTeacherProfileModal.vue'
import { SERVICE_KINDS, SERVICE_PART_KEYS } from '../../../constants/serviceKinds.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { useTeacherProfileStore } from '../../../store/user/teacherProfile.js'
import { kindsToAdd, serviceStage } from '../../../utils/myServices.js'

/**
 * « Mes services » (#1406): the services a member offers, how complete each one is and what it needs before it can
 * be published, and one card per kind they do not offer yet. Teaching is the only kind so far, edited in the teacher
 * form; each other kind brings its own form (#683).
 */
const { t, locale } = useI18n()
useTitle(() => t('profile.services.page_title'))
const toast = useToast()
const confirm = useConfirm()
const userSecurityStore = useUserSecurityStore()
const teacherProfileStore = useTeacherProfileStore()

const ICON_CLASS = 'flex items-center justify-center w-12 h-12 shrink-0 rounded-xl'

const services = ref([])
const isLoading = ref(true)
const addingKind = ref(null)
const busyServiceId = ref(null)
const showTeachingForm = ref(false)
const menuRef = ref(null)
const menuService = ref(null)

const username = computed(() => userSecurityStore.user?.username)
const addableKinds = computed(() => kindsToAdd(Object.keys(SERVICE_KINDS), services.value))
const menuItems = computed(() => {
  const service = menuService.value
  if (!service) return []
  return [
    ...(service.status === 'published'
      ? [
          {
            label: t('profile.services.unpublish'),
            icon: 'pi pi-eye-slash',
            command: () => unpublish(service)
          }
        ]
      : []),
    {
      label: t('profile.services.delete'),
      icon: 'pi pi-trash',
      command: () => confirmDelete(service)
    }
  ]
})

function cardHint(service) {
  const percent = service.completeness
  switch (serviceStage(service)) {
    case 'published':
      return t('profile.services.completeness', { percent })
    case 'ready':
      return t('profile.services.completeness_ready', { percent })
    default:
      return t('profile.services.completeness_missing', {
        percent,
        parts: new Intl.ListFormat(locale.value, { type: 'conjunction' }).format(
          service.missing_to_publish.map((part) => t(SERVICE_PART_KEYS[part]))
        )
      })
  }
}

async function loadServices() {
  try {
    services.value = await servicesApi.getMyServices()
  } catch {
    toast.add({ severity: 'error', summary: t('profile.services.load_error'), life: 5000 })
  }
}

function showError(summaryKey, error) {
  toast.add({ severity: 'error', summary: t(summaryKey), detail: error.message, life: 5000 })
}

// A new service starts as an empty draft, then its form opens to fill it in.
async function add(kind) {
  addingKind.value = kind
  try {
    services.value = [...services.value, await servicesApi.addService(kind)]
    await openTeachingForm()
  } catch (error) {
    showError('profile.services.add_error', error)
  } finally {
    addingKind.value = null
  }
}

async function openTeachingForm() {
  try {
    await teacherProfileStore.loadMyProfile()
    showTeachingForm.value = true
  } catch {
    toast.add({ severity: 'error', summary: t('profile.services.form_error'), life: 5000 })
  }
}

function handleTeachingSaved() {
  loadServices()
  toast.add({ severity: 'success', summary: t('profile.services.saved'), life: 5000 })
}

async function changeStatus(service, request, successKey, errorKey) {
  busyServiceId.value = service.id
  try {
    const changed = await request(service.id)
    services.value = services.value.map((item) => (item.id === changed.id ? changed : item))
    toast.add({ severity: 'success', summary: t(successKey), life: 5000 })
  } catch (error) {
    showError(errorKey, error)
  } finally {
    busyServiceId.value = null
  }
}

function publish(service) {
  return changeStatus(
    service,
    servicesApi.publishService,
    'profile.services.published',
    'profile.services.publish_error'
  )
}

function unpublish(service) {
  return changeStatus(
    service,
    servicesApi.unpublishService,
    'profile.services.unpublished',
    'profile.services.unpublish_error'
  )
}

function openMenu(event, service) {
  menuService.value = service
  menuRef.value.toggle(event)
}

function confirmDelete(service) {
  confirm.require({
    header: t('profile.services.delete_title'),
    message: t('profile.services.delete_confirm'),
    icon: 'pi pi-exclamation-triangle',
    rejectProps: {
      label: t('profile.services.cancel'),
      severity: 'secondary',
      outlined: true
    },
    acceptProps: { label: t('profile.services.delete'), severity: 'danger' },
    accept: () => deleteService(service)
  })
}

async function deleteService(service) {
  try {
    await servicesApi.deleteService(service.id)
    services.value = services.value.filter((item) => item.id !== service.id)
    teacherProfileStore.clear()
    toast.add({ severity: 'success', summary: t('profile.services.deleted'), life: 5000 })
  } catch (error) {
    showError('profile.services.delete_error', error)
  }
}

onMounted(async () => {
  await loadServices()
  isLoading.value = false
})

onUnmounted(() => {
  teacherProfileStore.clear()
})
</script>
