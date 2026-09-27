<template>
  <section
    class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 p-5"
    aria-labelledby="home-member-teacher-title"
  >
    <div class="flex flex-col gap-0.5">
      <div class="flex flex-wrap items-center gap-2">
        <h2 id="home-member-teacher-title" class="m-0 text-base font-bold text-surface-900 dark:text-surface-0">Professeurs</h2>
        <span class="rounded-full bg-primary-100 dark:bg-primary-400/15 px-2.5 py-0.5 text-xs font-bold text-primary-800 dark:text-primary-300">Bientôt</span>
      </div>
      <p class="m-0 text-sm text-surface-700 dark:text-surface-300">La recherche de professeurs par instrument et par ville arrive bientôt.</p>
    </div>
    <router-link
      v-if="hasTeacherProfile !== null"
      :to="hasTeacherProfile ? { name: 'app_user_teacher_profile', params: { username } } : { name: 'app_user_settings_profile_teacher' }"
      class="inline-flex items-center gap-1.5 shrink-0 text-sm font-semibold text-primary hover:underline"
    >
      {{ hasTeacherProfile ? 'Voir mon profil prof' : 'Créer mon profil prof' }}
      <i class="pi pi-arrow-right text-xs" aria-hidden="true" />
    </router-link>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import teacherProfileApi from '../../../api/user/teacherProfile.js'
import { useUserSecurityStore } from '../../../store/user/security.js'

/** The teachers, for a member (#1078): their own profile, or the way to create one. */
const userSecurityStore = useUserSecurityStore()
const username = computed(() => userSecurityStore.user?.username)
// null until known, so the link does not flip from « Créer » to « Voir » under the reader.
const hasTeacherProfile = ref(null)

onMounted(async () => {
  try {
    await teacherProfileApi.getMyTeacherProfile()
    hasTeacherProfile.value = true
  } catch {
    // A member without one gets a 404.
    hasTeacherProfile.value = false
  }
})
</script>
