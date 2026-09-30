<template>
  <div class="max-w-6xl w-full mx-auto py-6 lg:py-12 flex flex-col gap-24">
    <section class="flex flex-col gap-10">
      <div class="flex flex-col gap-4 max-w-3xl">
        <span class="text-sm font-bold tracking-wider uppercase text-primary">Band Space · gratuit</span>
        <h1 class="m-0 text-4xl lg:text-5xl font-extrabold leading-tight text-surface-900 dark:text-surface-0">
          Votre groupe mérite mieux qu'une conversation de groupe.
        </h1>
        <p class="m-0 text-lg leading-relaxed text-surface-600 dark:text-surface-300">
          Répondez à trois questions : on vous montre l'espace de votre groupe, et vous pouvez déjà
          cocher, réordonner et ajouter.
        </p>
      </div>

      <div class="grid lg:grid-cols-[26rem_minmax(0,1fr)] gap-8 items-start">
        <form
          class="flex flex-col gap-7 p-6 lg:p-8 rounded-2xl bg-surface-0 dark:bg-surface-900"
          @submit.prevent="handleCreate"
        >
          <div class="flex flex-col gap-2.5">
            <label for="demo-band-name" :class="QUESTION">1 · Le nom du groupe</label>
            <InputText
              id="demo-band-name"
              v-model="bandName"
              placeholder="Ex : ElectricNight"
              maxlength="40"
              size="large"
              class="w-full"
            />
          </div>

          <fieldset class="m-0 p-0 border-0 flex flex-col gap-2.5">
            <legend :class="[QUESTION, 'mb-2.5']">2 · Vous êtes combien ?</legend>
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
            <legend :class="[QUESTION, 'mb-1']">3 · Qu'est-ce qui coince aujourd'hui ?</legend>
            <span class="text-sm text-surface-600 dark:text-surface-400">Plusieurs réponses possibles.</span>
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
              Le nom est pré-rempli à la création. Rien n'est enregistré avant.
            </span>
            <span v-if="!userSecurityStore.isAuthenticated" class="text-sm text-center text-surface-600 dark:text-surface-400">
              Déjà un compte ?
              <RouterLink :to="{ name: 'app_login' }" class="font-semibold text-primary hover:underline">Se connecter</RouterLink>
            </span>
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

    <section class="grid lg:grid-cols-3 gap-5" aria-label="Pourquoi un Band Space">
      <div v-for="reason in REASONS" :key="reason.title" class="flex flex-col gap-3 p-7 rounded-2xl bg-surface-0 dark:bg-surface-900">
        <i :class="[reason.icon, '!text-2xl', reason.accent]" aria-hidden="true" />
        <h2 class="m-0 text-lg font-bold text-surface-900 dark:text-surface-0">{{ reason.title }}</h2>
        <p class="m-0 leading-relaxed text-surface-600 dark:text-surface-300">{{ reason.body }}</p>
      </div>
    </section>

    <section class="flex flex-col md:flex-row md:items-center justify-between gap-8 p-8 lg:p-14 rounded-3xl bg-surface-0 dark:bg-surface-900">
      <div class="flex flex-col gap-2.5">
        <h2 class="m-0 text-3xl font-extrabold text-surface-900 dark:text-surface-0">{{ closingTitle }}</h2>
        <p class="m-0 text-lg text-surface-600 dark:text-surface-300">
          Créez l'espace, invitez les autres, et travaillez à plusieurs dès la prochaine répète.
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
import { useRoute, useRouter } from 'vue-router'
import BandSpaceDemoPreview from '../../components/BandSpace/Presentation/BandSpaceDemoPreview.vue'
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

const REASONS = [
  {
    icon: 'pi pi-envelope',
    accent: 'text-primary',
    title: 'Invitez le groupe par mail',
    body: "Chaque membre reçoit un lien et voit tout de suite l'agenda, les fichiers et les setlists. Vous choisissez qui est admin."
  },
  {
    icon: 'pi pi-sync',
    accent: 'text-fuchsia-700 dark:text-fuchsia-300',
    title: 'Plusieurs groupes ?',
    body: "Un Band Space par groupe, on passe de l'un à l'autre en un clic. Chaque groupe ne voit que le sien."
  },
  {
    icon: 'pi pi-lock',
    accent: 'text-teal-700 dark:text-teal-300',
    title: 'Privé à votre groupe',
    body: "Rien n'est public. Pas de pub, pas de revente de données. Gratuit, et ça le restera."
  }
]

const route = useRoute()
const router = useRouter()
const bandSpaceStore = useBandSpaceStore()
const userSecurityStore = useUserSecurityStore()

const bandName = ref('')
const bandSize = ref(4)
const pickedPains = ref(['agenda', 'finances'])

const closingTitle = computed(() => {
  const name = bandName.value.trim()
  return `${name || 'Votre groupe'} n'attend plus que vous.`
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
