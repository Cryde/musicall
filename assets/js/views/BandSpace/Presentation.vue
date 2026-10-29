<template>
  <div class="max-w-6xl w-full mx-auto py-6 lg:py-12 flex flex-col gap-24">
    <section class="flex flex-col gap-10">
      <div class="flex flex-col gap-4 max-w-3xl">
        <span class="text-sm font-bold tracking-wider uppercase text-primary">{{ t('home.band_space_presentation.eyebrow') }}</span>
        <h1 class="m-0 text-4xl lg:text-5xl font-extrabold leading-tight text-surface-900 dark:text-surface-0">
          {{ t('home.band_space_presentation.title') }}
        </h1>
        <p class="m-0 text-lg leading-relaxed text-surface-600 dark:text-surface-300">
          {{ t('home.band_space_presentation.intro') }}
        </p>
      </div>

      <div class="grid lg:grid-cols-[26rem_minmax(0,1fr)] gap-8 items-start">
        <form
          class="flex flex-col gap-7 p-6 lg:p-8 rounded-2xl bg-surface-0 dark:bg-surface-900"
          @submit.prevent="handleCreate"
        >
          <div class="flex flex-col gap-2.5">
            <label for="demo-band-name" :class="QUESTION">{{ t('home.band_space_presentation.form.name_question') }}</label>
            <InputText
              id="demo-band-name"
              v-model="bandName"
              :placeholder="t('home.band_space_presentation.form.name_placeholder')"
              maxlength="40"
              size="large"
              class="w-full"
            />
          </div>

          <fieldset class="m-0 p-0 border-0 flex flex-col gap-2.5">
            <legend :class="[QUESTION, 'mb-2.5']">{{ t('home.band_space_presentation.form.size_question') }}</legend>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="size in DEMO_SIZES"
                :key="size"
                type="button"
                :aria-pressed="size === bandSize"
                :class="[PILL, size === bandSize ? PILL_ON : PILL_OFF]"
                @click="bandSize = size"
              >
                {{ sizeLabel(size) }}
              </button>
            </div>
          </fieldset>

          <fieldset class="m-0 p-0 border-0 flex flex-col gap-2.5">
            <legend :class="[QUESTION, 'mb-1']">{{ t('home.band_space_presentation.form.pains_question') }}</legend>
            <span class="text-sm text-surface-600 dark:text-surface-400">{{ t('home.band_space_presentation.form.pains_hint') }}</span>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="pain in DEMO_PAINS"
                :key="pain.key"
                type="button"
                :aria-pressed="pickedPains.includes(pain.key)"
                :class="[PILL, pickedPains.includes(pain.key) ? PILL_ON : PILL_OFF]"
                @click="togglePain(pain.key)"
              >
                {{ pain.label }}
              </button>
            </div>
          </fieldset>

          <div class="flex flex-col gap-3">
            <Button type="submit" :label="ctaLabel(bandName)" icon="pi pi-plus" size="large" class="w-full" />
            <span class="text-sm text-center text-surface-600 dark:text-surface-400">
              {{ t('home.band_space_presentation.form.prefill_hint') }}
            </span>
            <i18n-t
              v-if="!userSecurityStore.isAuthenticated"
              keypath="home.band_space_presentation.form.have_account"
              tag="span"
              class="text-sm text-center text-surface-600 dark:text-surface-400"
            >
              <template #login>
                <RouterLink :to="{ name: 'app_login' }" class="font-semibold text-primary hover:underline">{{ t('common.login') }}</RouterLink>
              </template>
            </i18n-t>
          </div>
        </form>

        <BandSpaceDemoPreview
          :name="bandName"
          :size="bandSize"
          :picked-pains="pickedPains"
          :requested-tab="route.query.module"
        />
      </div>
    </section>

    <section class="grid lg:grid-cols-3 gap-5" :aria-label="t('home.band_space_presentation.reasons_aria_label')">
      <div v-for="reason in reasons" :key="reason.title" class="flex flex-col gap-3 p-7 rounded-2xl bg-surface-0 dark:bg-surface-900">
        <i :class="[reason.icon, '!text-2xl', reason.accent]" aria-hidden="true" />
        <h2 class="m-0 text-lg font-bold text-surface-900 dark:text-surface-0">{{ reason.title }}</h2>
        <p class="m-0 leading-relaxed text-surface-600 dark:text-surface-300">{{ reason.body }}</p>
      </div>
    </section>

    <BandSpaceMobileApp />

    <section class="flex flex-col md:flex-row md:items-center justify-between gap-8 p-8 lg:p-14 rounded-3xl bg-surface-0 dark:bg-surface-900">
      <div class="flex flex-col gap-2.5">
        <h2 class="m-0 text-3xl font-extrabold text-surface-900 dark:text-surface-0">{{ closingTitle }}</h2>
        <p class="m-0 text-lg text-surface-600 dark:text-surface-300">
          {{ t('home.band_space_presentation.closing.body') }}
        </p>
      </div>
      <Button :label="ctaLabel(bandName)" icon="pi pi-plus" size="large" class="shrink-0" @click="handleCreate" />
    </section>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import BandSpaceDemoPreview from '../../components/BandSpace/Presentation/BandSpaceDemoPreview.vue'
import BandSpaceMobileApp from '../../components/BandSpace/Presentation/BandSpaceMobileApp.vue'
import { useBandSpaceStore } from '../../store/bandSpace/bandSpace.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { ctaLabel, DEMO_PAINS, DEMO_SIZES, sizeLabel } from '../../utils/bandSpaceDemo.js'
import { openDraftNameStorage, saveDraftName } from '../../utils/bandSpaceDraftName.js'

const QUESTION = 'text-sm font-bold tracking-wider uppercase text-primary'
const PILL =
  'h-11 min-w-14 px-4 rounded-lg border text-[15px] font-semibold cursor-pointer transition-colors'
const PILL_ON = 'bg-primary border-primary text-primary-contrast'
const PILL_OFF =
  'bg-transparent border-surface-300 dark:border-surface-600 text-surface-700 dark:text-surface-200 hover:border-primary'

const { t } = useI18n()

const reasons = computed(() => [
  {
    icon: 'pi pi-envelope',
    accent: 'text-primary',
    title: t('home.band_space_presentation.reasons.invite_title'),
    body: t('home.band_space_presentation.reasons.invite_body')
  },
  {
    icon: 'pi pi-sync',
    accent: 'text-fuchsia-700 dark:text-fuchsia-300',
    title: t('home.band_space_presentation.reasons.several_bands_title'),
    body: t('home.band_space_presentation.reasons.several_bands_body')
  },
  {
    icon: 'pi pi-lock',
    accent: 'text-teal-700 dark:text-teal-300',
    title: t('home.band_space_presentation.reasons.private_title'),
    body: t('home.band_space_presentation.reasons.private_body')
  }
])

const route = useRoute()
const router = useRouter()
const bandSpaceStore = useBandSpaceStore()
const userSecurityStore = useUserSecurityStore()

const bandName = ref('')
const bandSize = ref(4)
const pickedPains = ref(['agenda', 'finances'])

const closingTitle = computed(() => {
  const name = bandName.value.trim()
  return name
    ? t('home.band_space_presentation.closing.title_named', { name })
    : t('home.band_space_presentation.closing.title_default')
})

function togglePain(key) {
  pickedPains.value = pickedPains.value.includes(key)
    ? pickedPains.value.filter((picked) => picked !== key)
    : [...pickedPains.value, key]
}

// A visitor signs up first and finds the name waiting in the create modal afterwards. A member goes
// straight to the band layout, which mounts the modal already open.
function handleCreate() {
  saveDraftName(openDraftNameStorage(), bandName.value)

  if (!userSecurityStore.isAuthenticated) {
    router.push({ name: 'app_register' })
    return
  }

  bandSpaceStore.openCreateModal()
  router.push({ name: 'app_band_index' })
}
</script>
