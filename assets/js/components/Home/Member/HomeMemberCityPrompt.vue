<template>
  <form
    class="flex flex-col gap-3 rounded-xl border border-primary-200 dark:border-surface-700 bg-primary-50 dark:bg-surface-900 p-4"
    aria-labelledby="home-member-city-title"
    @submit.prevent="save"
  >
    <div class="flex flex-col gap-1">
      <label id="home-member-city-title" for="home-member-city" class="font-semibold text-surface-900 dark:text-surface-0">
        {{ t('home.member.city_prompt.label') }}
      </label>
      <span class="text-sm text-surface-700 dark:text-surface-300">
        {{ t('home.member.city_prompt.intro') }}
      </span>
    </div>
    <div class="flex flex-col sm:flex-row gap-2">
      <CityAutoComplete v-model="city" input-id="home-member-city" :placeholder="t('home.member.city_prompt.placeholder')" class="sm:flex-1" />
      <div class="flex gap-2">
        <Button type="submit" :label="t('home.member.city_prompt.save')" :loading="isSaving" :disabled="!isCityPicked" />
        <Button v-if="cancellable" type="button" :label="t('home.member.city_prompt.cancel')" severity="secondary" :disabled="isSaving" @click="$emit('cancel')" />
      </div>
    </div>
    <small class="text-surface-600 dark:text-surface-300">{{ t('home.member.city_prompt.hint') }}</small>
    <small v-if="error" role="alert" class="text-red-700 dark:text-red-400">{{ error }}</small>
  </form>
</template>

<script setup>
import Button from 'primevue/button'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import profileApi from '../../../api/user/profile.js'
import { profileCity, profileLocationPayload } from '../../../utils/profileLocation.js'
import CityAutoComplete from '../../Global/CityAutoComplete.vue'

/** Asks a member for their city on the home (#1079) and saves it as their profile location. */
const props = defineProps({
  /** The city the member already gave, when they change it. */
  currentCity: { type: Object, default: null },
  /** Offered when the member changes a city they already gave. */
  cancellable: { type: Boolean, default: false }
})

const emit = defineEmits(['saved', 'cancel'])

const { t } = useI18n()

const city = ref(props.currentCity)
const isSaving = ref(false)
const error = ref(null)
// Only a city chosen in the list has coordinates to search around, and saving the same one is moot.
const isCityPicked = computed(
  () => city.value !== null && typeof city.value === 'object' && city.value !== props.currentCity
)

async function save() {
  if (!isCityPicked.value) return
  isSaving.value = true
  error.value = null
  try {
    const profile = await profileApi.updateMyProfile(profileLocationPayload(city.value))
    emit('saved', profileCity(profile))
  } catch {
    error.value = t('home.member.city_prompt.error')
  } finally {
    isSaving.value = false
  }
}
</script>
