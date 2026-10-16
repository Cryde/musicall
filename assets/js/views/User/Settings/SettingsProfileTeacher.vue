<template>
  <div class="flex flex-col gap-6">
    <div v-if="isLoading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl"></i>
    </div>

    <!-- No profile - CTA to create -->
    <template v-else-if="!hasProfile">
      <div class="flex flex-col items-center justify-center py-12 text-center">
        <div class="flex items-center justify-center w-20 h-20 rounded-full bg-primary-100 dark:bg-primary-900/30 mb-6">
          <i class="pi pi-graduation-cap text-3xl text-primary-600 dark:text-primary-400" />
        </div>
        <h3 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-2">
          {{ t('profile.settings.teacher.empty_title') }}
        </h3>
        <p class="text-surface-500 dark:text-surface-400 mb-6 max-w-md">
          {{ t('profile.settings.teacher.empty_help') }}
        </p>
        <Button
          :label="t('profile.settings.teacher.create')"
          icon="pi pi-plus"
          @click="showEditModal = true"
        />
      </div>
    </template>

    <!-- Profile exists - show details -->
    <template v-else>
      <!-- Description -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.teacher.description') }}
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.description" class="text-surface-900 dark:text-surface-0 whitespace-pre-line">
            {{ truncateDescription(profile.description) }}
          </div>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            {{ t('profile.settings.teacher.not_set') }}
          </span>
        </div>
      </div>

      <!-- Years of experience -->
      <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.teacher.experience') }}
          </div>
        </div>
        <div class="md:w-2/3">
          <span v-if="profile.years_of_experience" class="text-surface-900 dark:text-surface-0">
            {{ t('profile.settings.teacher.years', profile.years_of_experience) }}
          </span>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            {{ t('profile.settings.teacher.not_set') }}
          </span>
        </div>
      </div>

      <!-- Course title -->
      <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.teacher.course_title') }}
          </div>
        </div>
        <div class="md:w-2/3">
          <span v-if="profile.course_title" class="text-surface-900 dark:text-surface-0">
            {{ profile.course_title }}
          </span>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            {{ t('profile.settings.teacher.not_set') }}
          </span>
        </div>
      </div>

      <!-- Student levels -->
      <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.teacher.student_levels') }}
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.student_levels?.length" class="flex flex-wrap gap-2">
            <Tag
              v-for="level in profile.student_levels"
              :key="level"
              :value="getStudentLevelLabel(level)"
              severity="secondary"
            />
          </div>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            {{ t('profile.settings.teacher.student_levels_empty') }}
          </span>
        </div>
      </div>

      <!-- Age groups -->
      <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.teacher.age_groups') }}
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.age_groups?.length" class="flex flex-wrap gap-2">
            <Tag
              v-for="ageGroup in profile.age_groups"
              :key="ageGroup"
              :value="getAgeGroupLabel(ageGroup)"
              severity="secondary"
            />
          </div>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            {{ t('profile.settings.teacher.age_groups_empty') }}
          </span>
        </div>
      </div>

      <!-- Locations -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.teacher.locations') }}
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.locations?.length" class="flex flex-col gap-1">
            <div v-for="(loc, index) in profile.locations" :key="index" class="text-surface-900 dark:text-surface-0">
              <span v-if="loc.type === 'teacher_place'">
                <i class="pi pi-home mr-1 text-surface-500" />{{ t('profile.teacher.location_type.teacher_place') }}<span v-if="loc.city"> ({{ loc.city }})</span>
              </span>
              <span v-else-if="loc.type === 'student_place'">
                <i class="pi pi-car mr-1 text-surface-500" />{{ t('profile.teacher.location_type.student_place') }}<span v-if="loc.radius"> ({{ t('profile.teacher.radius_km', { radius: loc.radius }) }})</span><span v-if="loc.city"> - {{ loc.city }}</span>
              </span>
              <span v-else-if="loc.type === 'online'">
                <i class="pi pi-video mr-1 text-surface-500" />{{ t('profile.teacher.location_type.online') }}
              </span>
            </div>
          </div>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            {{ t('profile.settings.teacher.not_set') }}
          </span>
        </div>
      </div>

      <!-- Instruments -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.teacher.instruments') }}
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.instruments?.length" class="flex flex-col gap-2">
            <div
              v-for="instrument in profile.instruments"
              :key="instrument.instrument_id"
              class="text-surface-900 dark:text-surface-0"
            >
              {{ instrument.instrument_name }}
            </div>
          </div>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            {{ t('profile.settings.teacher.instruments_empty') }}
          </span>
        </div>
      </div>

      <!-- Styles -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.teacher.styles') }}
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.styles?.length" class="flex flex-wrap gap-2">
            <Tag
              v-for="style in profile.styles"
              :key="style.id"
              :value="style.name"
              severity="info"
            />
          </div>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            {{ t('profile.settings.teacher.styles_empty') }}
          </span>
        </div>
      </div>

      <!-- Trial offer -->
      <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.teacher.trial') }}
          </div>
        </div>
        <div class="md:w-2/3">
          <span v-if="profile.offers_trial" class="text-surface-900 dark:text-surface-0">
            <i class="pi pi-check-circle text-green-500 mr-1" />
            {{ t('profile.settings.teacher.trial_offered') }}
            <span v-if="profile.trial_price != null">
              - {{ profile.trial_price === 0 ? t('profile.settings.teacher.trial_free') : formatPrice(profile.trial_price) }}
            </span>
          </span>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            {{ t('profile.settings.teacher.trial_not_offered') }}
          </span>
        </div>
      </div>

      <!-- Pricing -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.teacher.pricing') }}
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.pricing?.length" class="flex flex-col gap-1">
            <div v-for="pricing in profile.pricing" :key="pricing.id" class="text-surface-900 dark:text-surface-0">
              <span class="font-medium">{{ getSessionDurationLabel(pricing.duration) }}</span>
              <span class="text-surface-500 dark:text-surface-400 ml-2">{{ formatPrice(pricing.price) }}</span>
            </div>
          </div>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            {{ t('profile.settings.teacher.pricing_empty') }}
          </span>
        </div>
      </div>

      <!-- Availability -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.teacher.availability') }}
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.availability?.length" class="flex flex-col gap-1">
            <div v-for="slot in profile.availability" :key="slot.id" class="text-surface-900 dark:text-surface-0">
              <span class="font-medium">{{ getDayOfWeekLabel(slot.day_of_week) }}</span>
              <span class="text-surface-500 dark:text-surface-400 ml-2">{{ slot.start_time }} - {{ slot.end_time }}</span>
            </div>
          </div>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            {{ t('profile.settings.teacher.availability_empty') }}
          </span>
        </div>
      </div>

      <!-- Packages -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.teacher.packages') }}
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.packages?.length" class="flex flex-col gap-2">
            <div v-for="pkg in profile.packages" :key="pkg.id" class="text-surface-900 dark:text-surface-0">
              <span class="font-medium">{{ pkg.title }}</span>
              <span class="text-surface-500 dark:text-surface-400 ml-2">
                {{ t('profile.settings.teacher.package_summary', { sessions: pkg.sessions_count, price: formatPrice(pkg.price) }) }}
              </span>
            </div>
          </div>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            {{ t('profile.settings.teacher.packages_empty') }}
          </span>
        </div>
      </div>

      <!-- Social links -->
      <div class="flex flex-col md:flex-row gap-2 md:gap-4 py-3 border-b border-surface-200 dark:border-surface-700">
        <div class="md:w-1/3">
          <div class="text-surface-600 dark:text-surface-400 font-medium">
            {{ t('profile.settings.teacher.social_links') }}
          </div>
        </div>
        <div class="md:w-2/3">
          <div v-if="profile.social_links?.length" class="flex flex-col gap-1">
            <a
              v-for="link in profile.social_links"
              :key="link.platform"
              :href="link.url"
              target="_blank"
              rel="noopener noreferrer"
              class="flex items-center gap-2 text-primary-600 dark:text-primary-400 hover:underline"
            >
              <span class="font-medium">{{ getSocialPlatformLabel(link.platform) }}</span>
              <i class="pi pi-external-link text-xs" />
            </a>
          </div>
          <span v-else class="text-surface-500 dark:text-surface-400 text-sm">
            {{ t('profile.settings.teacher.social_links_empty') }}
          </span>
        </div>
      </div>

      <!-- Action buttons -->
      <div class="flex justify-between items-center">
        <Button
          :label="t('profile.settings.teacher.delete_profile')"
          icon="pi pi-trash"
          severity="danger"
          text
          :loading="isDeleting"
          @click="showDeleteConfirm = true"
        />
        <div class="flex items-center gap-2">
          <RouterLink :to="{ name: 'app_user_teacher_profile', params: { username: userSecurityStore.user.username } }">
            <Button
              :label="t('profile.settings.teacher.view_profile')"
              icon="pi pi-external-link"
              severity="secondary"
              outlined
            />
          </RouterLink>
          <Button
            :label="t('profile.settings.teacher.edit')"
            icon="pi pi-pencil"
            @click="showEditModal = true"
          />
        </div>
      </div>

      <!-- Media showcase section -->
      <div class="mt-8 pt-6 border-t border-surface-200 dark:border-surface-700">
        <TeacherMediaShowcase :is-own-profile="true" />
      </div>
    </template>

    <!-- Delete confirmation dialog -->
    <Dialog
      v-model:visible="showDeleteConfirm"
      modal
      :header="t('profile.settings.teacher.delete_title')"
      :style="{ width: '450px' }"
    >
      <div class="flex items-start gap-4">
        <i class="pi pi-exclamation-triangle text-4xl text-red-500 shrink-0" />
        <div class="flex flex-col gap-2">
          <span>
            {{ t('profile.settings.teacher.delete_confirm') }}
          </span>
          <span class="text-surface-500 dark:text-surface-400 text-sm">
            {{ t('profile.settings.teacher.delete_media_notice') }}
          </span>
          <span class="text-red-500 text-sm font-medium">
            {{ t('profile.settings.teacher.delete_irreversible') }}
          </span>
        </div>
      </div>
      <template #footer>
        <Button
          :label="t('profile.settings.teacher.cancel')"
          text
          @click="showDeleteConfirm = false"
        />
        <Button
          :label="t('profile.settings.teacher.delete')"
          severity="danger"
          :loading="isDeleting"
          @click="handleDeleteProfile"
        />
      </template>
    </Dialog>

    <!-- Edit/Create modal -->
    <EditTeacherProfileModal
      v-model:visible="showEditModal"
      :teacher-profile="profile"
      @saved="handleProfileSaved"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import EditTeacherProfileModal from '../../../components/Teacher/EditTeacherProfileModal.vue'
import TeacherMediaShowcase from '../../../components/Teacher/TeacherMediaShowcase.vue'
import {
  getAgeGroupLabel,
  getDayOfWeekLabel,
  getSessionDurationLabel,
  getSocialPlatformLabel,
  getStudentLevelLabel
} from '../../../constants/teacherProfile.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { useTeacherProfileStore } from '../../../store/user/teacherProfile.js'
import { useTeacherProfileMediaStore } from '../../../store/user/teacherProfileMedia.js'
import { formatEurosFromCents } from '../../../utils/currency.js'

const teacherProfileStore = useTeacherProfileStore()
const userSecurityStore = useUserSecurityStore()
const teacherProfileMediaStore = useTeacherProfileMediaStore()
const toast = useToast()
const { t } = useI18n()

const isLoading = ref(true)
const showEditModal = ref(false)
const showDeleteConfirm = ref(false)

const isDeleting = computed(() => teacherProfileStore.isDeleting)

const profile = computed(() => teacherProfileStore.profile)
const hasProfile = computed(() => profile.value !== null)

function truncateDescription(text, maxLength = 200) {
  if (!text || text.length <= maxLength) return text
  return `${text.substring(0, maxLength)}...`
}

function formatPrice(priceInCents) {
  if (priceInCents == null) return ''
  return formatEurosFromCents(priceInCents)
}

async function loadProfile() {
  isLoading.value = true
  try {
    await teacherProfileStore.loadMyProfile()
  } catch (error) {
    // 404 means no profile exists, which is fine
    if (error.response?.status !== 404) {
      toast.add({
        severity: 'error',
        summary: t('profile.settings.teacher.error'),
        detail: t('profile.settings.teacher.load_error'),
        life: 5000
      })
    }
  } finally {
    isLoading.value = false
  }
}

function handleProfileSaved() {
  loadProfile()
  toast.add({
    severity: 'success',
    summary: t('profile.settings.teacher.saved'),
    detail: t('profile.settings.teacher.saved_detail'),
    life: 5000
  })
}

async function handleDeleteProfile() {
  try {
    await teacherProfileStore.deleteProfile()
    showDeleteConfirm.value = false
    toast.add({
      severity: 'success',
      summary: t('profile.settings.teacher.deleted'),
      detail: t('profile.settings.teacher.deleted_detail'),
      life: 5000
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('profile.settings.teacher.error'),
      detail: t('profile.settings.teacher.delete_error'),
      life: 5000
    })
  }
}

onMounted(() => {
  loadProfile()
})

onUnmounted(() => {
  teacherProfileStore.clear()
  teacherProfileMediaStore.clear()
})
</script>
