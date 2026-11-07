<template>
  <div class="flex flex-col gap-6">
    <div class="flex flex-col gap-2">
      <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('profile.settings.profile.title') }}
      </h2>
      <p class="text-surface-500 dark:text-surface-400 text-sm">
        {{ t('profile.settings.profile.subtitle') }}
      </p>
    </div>

    <div v-if="isLoading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl" aria-hidden="true" />
    </div>

    <!-- The links below need the member's username. -->
    <div v-else-if="username" class="flex flex-col divide-y divide-surface-200 dark:divide-surface-700">
      <!-- Everything shown on the profile is edited there (#1317). -->
      <section :class="ROW_CLASS">
        <div class="md:w-1/3">
          <h3 :class="LABEL_CLASS">{{ t('profile.settings.profile.sections.profile') }}</h3>
        </div>
        <div class="md:w-2/3 flex flex-col items-start gap-3">
          <p class="m-0 text-sm text-surface-600 dark:text-surface-400">
            {{ t('profile.settings.profile.edit_on_profile') }}
          </p>
          <Button
            as="router-link"
            :to="{ name: 'app_user_public_profile', params: { username } }"
            :label="t('profile.settings.profile.open_profile')"
            icon="pi pi-user"
          />
        </div>
      </section>

      <!-- Left out until the profile says what it is: a guess could save the wrong visibility. -->
      <section v-if="savedIsPublic !== null" :class="ROW_CLASS">
        <div class="md:w-1/3">
          <h3 :class="LABEL_CLASS">{{ t('profile.settings.profile_privacy.visibility') }}</h3>
          <p class="text-sm text-surface-500 dark:text-surface-400 mt-1 mb-0">
            {{ t('profile.settings.profile_privacy.visibility_help') }}
          </p>
        </div>
        <div class="md:w-2/3 flex flex-col gap-4">
          <div class="flex items-center gap-3">
            <ToggleSwitch
              v-model="isPublic"
              :disabled="isSavingVisibility"
              :aria-label="t('profile.settings.profile_privacy.visibility')"
            />
            <span class="text-surface-700 dark:text-surface-300">
              {{ isPublic ? t('profile.settings.profile_privacy.public') : t('profile.settings.profile_privacy.private') }}
            </span>
          </div>
          <div class="p-4 rounded-lg" :class="isPublic ? 'bg-green-50 dark:bg-green-900/20' : 'bg-surface-100 dark:bg-surface-800'">
            <div class="flex items-start gap-3">
              <i
                :class="['pi text-lg mt-0.5', isPublic ? 'pi-globe text-green-600 dark:text-green-400' : 'pi-lock text-surface-500']"
                aria-hidden="true"
              />
              <div>
                <p class="m-0 font-medium text-surface-900 dark:text-surface-0">
                  {{ isPublic ? t('profile.settings.profile_privacy.visible_title') : t('profile.settings.profile_privacy.hidden_title') }}
                </p>
                <p class="mb-0 text-sm text-surface-600 dark:text-surface-400 mt-1">
                  {{ isPublic ? t('profile.settings.profile_privacy.visible_help') : t('profile.settings.profile_privacy.hidden_help') }}
                </p>
              </div>
            </div>
          </div>
          <div>
            <Button
              :label="t('profile.settings.profile_privacy.save')"
              icon="pi pi-check"
              :loading="isSavingVisibility"
              :disabled="isPublic === savedIsPublic"
              @click="saveVisibility"
            />
          </div>
        </div>
      </section>

      <section :class="ROW_CLASS">
        <div class="md:w-1/3">
          <h3 :class="LABEL_CLASS">{{ t('profile.settings.profile.sections.musician') }}</h3>
        </div>
        <div class="md:w-2/3 flex flex-col items-start gap-3">
          <template v-if="musicianProfileStore.profile">
            <Button
              as="router-link"
              :to="{ name: 'app_user_public_profile', params: { username }, query: { voir: 'medias' } }"
              :label="t('profile.settings.musician.view_profile')"
              icon="pi pi-external-link"
              severity="secondary"
              outlined
            />
            <Button
              :label="t('profile.settings.musician.delete_title')"
              icon="pi pi-trash"
              severity="danger"
              text
              :loading="musicianProfileStore.isDeleting"
              @click="confirmDelete('musician')"
            />
          </template>
          <template v-else>
            <p class="m-0 text-sm text-surface-600 dark:text-surface-400">{{ t('profile.settings.musician.empty_help') }}</p>
            <Button :label="t('profile.settings.musician.create')" icon="pi pi-plus" @click="showCreateMusician = true" />
          </template>
        </div>
      </section>

      <section :class="ROW_CLASS">
        <div class="md:w-1/3">
          <h3 :class="LABEL_CLASS">{{ t('profile.settings.profile.sections.teacher') }}</h3>
        </div>
        <div class="md:w-2/3 flex flex-col items-start gap-3">
          <template v-if="teacherProfileStore.profile">
            <div class="w-full">
              <p class="m-0 text-sm font-semibold text-surface-900 dark:text-surface-0">
                {{ completenessLabel }}
              </p>
              <div
                class="h-2 rounded-full bg-surface-200 dark:bg-surface-700 overflow-hidden my-2"
                role="progressbar"
                :aria-valuenow="completeness.percent"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="completenessLabel"
              >
                <span class="block h-full bg-green-500" :style="{ width: `${completeness.percent}%` }" />
              </div>
              <p v-if="completeness.missing.length" class="m-0 text-sm text-surface-500 dark:text-surface-400">
                {{ t('profile.settings.teacher.completeness_hint', { parts: missingParts }) }}
              </p>
            </div>
            <Button
              as="router-link"
              :to="{ name: 'app_user_teacher_profile', params: { username } }"
              :label="t('profile.settings.teacher.view_page')"
              icon="pi pi-external-link"
              severity="secondary"
              outlined
            />
            <Button
              :label="t('profile.settings.teacher.delete_title')"
              icon="pi pi-trash"
              severity="danger"
              text
              :loading="teacherProfileStore.isDeleting"
              @click="confirmDelete('teacher')"
            />
          </template>
          <template v-else>
            <p class="m-0 text-sm text-surface-600 dark:text-surface-400">{{ t('profile.settings.teacher.empty_help') }}</p>
            <Button :label="t('profile.settings.teacher.create')" icon="pi pi-plus" @click="showCreateTeacher = true" />
          </template>
        </div>
      </section>
    </div>

    <EditMusicianProfileModal v-model:visible="showCreateMusician" :musician-profile="null" @saved="handleCreated('musician')" />
    <EditTeacherProfileModal v-model:visible="showCreateTeacher" :teacher-profile="null" @saved="handleCreated('teacher')" />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import ToggleSwitch from 'primevue/toggleswitch'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import profileApi from '../../../api/user/profile.js'
import EditTeacherProfileModal from '../../../components/Teacher/EditTeacherProfileModal.vue'
import EditMusicianProfileModal from '../../../components/User/Profile/EditMusicianProfileModal.vue'
import { useMusicianProfileStore } from '../../../store/user/musicianProfile.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { useTeacherProfileStore } from '../../../store/user/teacherProfile.js'
import { teacherProfileCompleteness } from '../../../utils/teacherPage.js'

/**
 * The profile settings (#1317): what the profile is and who sees it. Its content is edited on the profile itself,
 * so only its visibility and the musician and teacher profiles' creation and deletion stay here.
 */
const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const userSecurityStore = useUserSecurityStore()
const musicianProfileStore = useMusicianProfileStore()
const teacherProfileStore = useTeacherProfileStore()

const ROW_CLASS = 'flex flex-col md:flex-row md:items-start gap-4 py-5 first:pt-0'
const LABEL_CLASS = 'm-0 text-base font-medium text-surface-600 dark:text-surface-400'
const COMPLETENESS_PART_LABELS = {
  course_title: 'profile.settings.teacher.course_title',
  description: 'profile.settings.teacher.description',
  instruments: 'profile.settings.teacher.instruments',
  student_levels: 'profile.settings.teacher.student_levels',
  age_groups: 'profile.settings.teacher.age_groups',
  level_goals: 'profile.settings.teacher.level_goals',
  songs: 'profile.settings.teacher.songs',
  locations: 'profile.settings.teacher.locations',
  availability: 'profile.settings.teacher.availability',
  pricing: 'profile.settings.teacher.pricing'
}
// Each kind of profile: its store and its messages, as full keys for the i18n check.
const KINDS = {
  musician: {
    store: musicianProfileStore,
    messages: {
      loadError: 'profile.settings.musician.load_error',
      deleteTitle: 'profile.settings.musician.delete_title',
      deleteConfirm: 'profile.settings.musician.delete_confirm',
      deleteMediaNotice: 'profile.settings.musician.delete_media_notice',
      deleteIrreversible: 'profile.settings.musician.delete_irreversible',
      cancel: 'profile.settings.musician.cancel',
      delete: 'profile.settings.musician.delete',
      deleted: 'profile.settings.musician.deleted',
      deletedDetail: 'profile.settings.musician.deleted_detail',
      deleteError: 'profile.settings.musician.delete_error',
      saved: 'profile.settings.musician.saved',
      savedDetail: 'profile.settings.musician.saved_detail'
    }
  },
  teacher: {
    store: teacherProfileStore,
    messages: {
      loadError: 'profile.settings.teacher.load_error',
      deleteTitle: 'profile.settings.teacher.delete_title',
      deleteConfirm: 'profile.settings.teacher.delete_confirm',
      deleteMediaNotice: 'profile.settings.teacher.delete_media_notice',
      deleteIrreversible: 'profile.settings.teacher.delete_irreversible',
      cancel: 'profile.settings.teacher.cancel',
      delete: 'profile.settings.teacher.delete',
      deleted: 'profile.settings.teacher.deleted',
      deletedDetail: 'profile.settings.teacher.deleted_detail',
      deleteError: 'profile.settings.teacher.delete_error',
      saved: 'profile.settings.teacher.saved',
      savedDetail: 'profile.settings.teacher.saved_detail'
    }
  }
}

const isLoading = ref(true)
const isPublic = ref(null)
const savedIsPublic = ref(null)
const isSavingVisibility = ref(false)
const showCreateMusician = ref(false)
const showCreateTeacher = ref(false)

const username = computed(() => userSecurityStore.user?.username)
const completeness = computed(() => teacherProfileCompleteness(teacherProfileStore.profile))
const completenessLabel = computed(() =>
  t('profile.settings.teacher.completeness', { percent: completeness.value.percent })
)
// The first two missing parts, named as the lessons page form names them.
const missingParts = computed(() =>
  new Intl.ListFormat(locale.value, { type: 'conjunction' }).format(
    completeness.value.missing.slice(0, 2).map((part) => t(COMPLETENESS_PART_LABELS[part]))
  )
)

// A 404 means the member has no such profile yet.
async function loadKind(kind) {
  try {
    await KINDS[kind].store.loadMyProfile()
  } catch (error) {
    if (error.response?.status !== 404) {
      toast.add({ severity: 'error', summary: t(KINDS[kind].messages.loadError), life: 5000 })
    }
  }
}

async function loadVisibility() {
  try {
    const profile = await profileApi.getMyProfile()
    isPublic.value = profile.is_public
    savedIsPublic.value = profile.is_public
  } catch {
    toast.add({
      severity: 'error',
      summary: t('profile.settings.profile_privacy.load_error'),
      life: 5000
    })
  }
}

async function saveVisibility() {
  isSavingVisibility.value = true
  try {
    await profileApi.updateMyProfile({ is_public: isPublic.value })
    savedIsPublic.value = isPublic.value
    toast.add({
      severity: 'success',
      summary: t('profile.settings.profile_privacy.saved'),
      detail: t('profile.settings.profile_privacy.saved_detail'),
      life: 5000
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('profile.settings.profile_privacy.error'),
      detail: error.message || t('profile.settings.profile_privacy.save_error'),
      life: 5000
    })
  } finally {
    isSavingVisibility.value = false
  }
}

function confirmDelete(kind) {
  const messages = KINDS[kind].messages
  confirm.require({
    header: t(messages.deleteTitle),
    message: [
      t(messages.deleteConfirm),
      t(messages.deleteMediaNotice),
      t(messages.deleteIrreversible)
    ].join(' '),
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: t(messages.cancel), severity: 'secondary', outlined: true },
    acceptProps: { label: t(messages.delete), severity: 'danger' },
    accept: () => deleteKind(kind)
  })
}

async function deleteKind(kind) {
  const messages = KINDS[kind].messages
  try {
    await KINDS[kind].store.deleteProfile()
    toast.add({
      severity: 'success',
      summary: t(messages.deleted),
      detail: t(messages.deletedDetail),
      life: 5000
    })
  } catch {
    toast.add({ severity: 'error', summary: t(messages.deleteError), life: 5000 })
  }
}

function handleCreated(kind) {
  const { messages } = KINDS[kind]
  loadKind(kind)
  toast.add({
    severity: 'success',
    summary: t(messages.saved),
    detail: t(messages.savedDetail),
    life: 5000
  })
}

onMounted(async () => {
  await Promise.all([loadVisibility(), loadKind('musician'), loadKind('teacher')])
  isLoading.value = false
})

onUnmounted(() => {
  musicianProfileStore.clear()
  teacherProfileStore.clear()
})
</script>
