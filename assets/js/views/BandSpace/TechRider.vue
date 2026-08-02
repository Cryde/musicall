<template>
  <div>
    <div
      v-if="techRidersStore.loadError"
      class="flex flex-col items-center justify-center min-h-[400px] p-8 gap-4"
    >
      <Message severity="error" :closable="false">{{ techRidersStore.loadError }}</Message>
      <Button label="Réessayer" icon="pi pi-refresh" severity="secondary" @click="load" />
    </div>

    <div v-else-if="techRidersStore.isLoading" class="p-8 flex justify-center">
      <ProgressSpinner style="width: 2.5rem; height: 2.5rem" />
    </div>

    <div v-else-if="!hasAnyRider" class="flex flex-col gap-4">
      <div>
        <h1 class="text-2xl font-bold">Tech riders</h1>
        <p class="text-surface-600 dark:text-surface-300 mt-1">
          Les documents techniques envoyés aux salles avant un concert.
        </p>
      </div>
      <div
        class="bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 p-10 text-center"
      >
        <i
          class="pi pi-sliders-h text-4xl text-surface-400 dark:text-surface-500"
          aria-hidden="true"
        />
        <p class="mt-3 font-medium">Aucun tech rider</p>
        <p class="mt-1 text-surface-600 dark:text-surface-300">
          Créez votre premier tech rider pour centraliser vos besoins techniques.
        </p>
        <Button
          label="Nouveau tech rider"
          icon="pi pi-plus"
          class="mt-4"
          @click="openCreateDialog"
        />
      </div>
    </div>

    <div v-else class="flex flex-col gap-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3 min-w-0">
          <h1 class="text-2xl font-bold shrink-0">Tech riders</h1>
          <TechRiderSelector
            :live-riders="[...techRidersStore.liveRiders]"
            :archived-riders="[...techRidersStore.archivedRiders]"
            :selected-id="selectedRiderId"
            @select="selectRider"
            @create="openCreateDialog"
          />
        </div>
        <div v-if="rider && !isArchived" class="flex items-center gap-2">
          <Button
            label="Renommer"
            icon="pi pi-pencil"
            severity="secondary"
            outlined
            @click="openRenameDialog"
          />
          <Button
            label="Archiver"
            icon="pi pi-inbox"
            severity="secondary"
            outlined
            @click="confirmArchive"
          />
        </div>
      </div>

      <Message v-if="isArchived" severity="warn" :closable="false">
        <div class="flex flex-wrap items-center justify-between gap-3 w-full">
          <span>Ce tech rider est archivé. Restaurez-le pour pouvoir le modifier.</span>
          <Button label="Restaurer" icon="pi pi-replay" size="small" @click="handleUnarchive" />
        </div>
      </Message>

      <div
        class="bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 p-4"
      >
        <div v-if="techRidersStore.isLoadingActive" class="p-8 flex justify-center">
          <ProgressSpinner style="width: 2.5rem; height: 2.5rem" />
        </div>

        <template v-else-if="rider">
          <p class="text-surface-600 dark:text-surface-300 text-sm mb-3">
            Créé le {{ formatDate(rider.creation_datetime) }}
            {{ rider.created_by_username ? `par ${rider.created_by_username}` : '' }}
          </p>

          <Tabs v-model:value="activeTab">
            <TabList>
              <Tab value="informations">Informations</Tab>
              <Tab value="stage-plot">Plan de scène</Tab>
              <Tab value="patch-list">Patch list</Tab>
              <Tab value="documents">Documents</Tab>
            </TabList>

            <TabPanels>
              <TabPanel value="informations">
                <p class="text-surface-600 dark:text-surface-300 py-6 text-center">
                  Les sections du tech rider arriveront prochainement.
                </p>
              </TabPanel>
              <TabPanel value="stage-plot">
                <p class="text-surface-600 dark:text-surface-300 py-6 text-center">
                  Le plan de scène arrivera prochainement.
                </p>
              </TabPanel>
              <TabPanel value="patch-list">
                <p class="text-surface-600 dark:text-surface-300 py-6 text-center">
                  La patch list arrivera prochainement.
                </p>
              </TabPanel>
              <TabPanel value="documents">
                <p class="text-surface-600 dark:text-surface-300 py-6 text-center">
                  Les documents arriveront prochainement.
                </p>
              </TabPanel>
            </TabPanels>
          </Tabs>
        </template>
      </div>
    </div>

    <TechRiderFormDialog
      v-model:visible="formDialogOpen"
      :band-space-id="bandSpaceId"
      :mode="formDialogMode"
      :rider-id="formDialogMode === 'rename' ? selectedRiderId : null"
      :initial-name="formDialogMode === 'rename' ? (rider?.name ?? '') : ''"
      @saved="handleSaved"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import TechRiderFormDialog from '../../components/BandSpace/TechRider/TechRiderFormDialog.vue'
import TechRiderSelector from '../../components/BandSpace/TechRider/TechRiderSelector.vue'
import { LAST_TECH_RIDER_KEY } from '../../constants/bandSpace.js'
import { useBandTechRidersStore } from '../../store/bandSpace/bandSpaceTechRiders.js'

const TABS = ['informations', 'stage-plot', 'patch-list', 'documents']

const route = useRoute()
const router = useRouter()
const techRidersStore = useBandTechRidersStore()
const confirm = useConfirm()
const toast = useToast()

const bandSpaceId = computed(() => route.params.id)
const rider = computed(() => techRidersStore.activeTechRider)
const isArchived = computed(() => Boolean(rider.value?.archive_datetime))
const hasAnyRider = computed(
  () => techRidersStore.liveRiders.length > 0 || techRidersStore.archivedRiders.length > 0
)

const selectedRiderId = ref(null)
const formDialogOpen = ref(false)
const formDialogMode = ref('create')
const activeTab = ref(tabFromQuery())

function tabFromQuery() {
  const requested = route.query.tab
  return typeof requested === 'string' && TABS.includes(requested) ? requested : TABS[0]
}

/** localStorage key is per space: each band remembers its own rider. */
function rememberedKey() {
  return `${LAST_TECH_RIDER_KEY}:${bandSpaceId.value}`
}

/**
 * Resolution order: the URL wins so a shared link lands where the sender was, then the
 * rider last looked at in this space, then the newest live one. A remembered rider is
 * honoured even once archived, because that is still where the user left off and the
 * archived banner explains the state.
 */
function resolveInitialRider() {
  const fromUrl = typeof route.query.rider === 'string' ? route.query.rider : null
  if (fromUrl && techRidersStore.findRider(fromUrl)) {
    return fromUrl
  }

  const remembered = localStorage.getItem(rememberedKey())
  if (remembered && techRidersStore.findRider(remembered)) {
    return remembered
  }

  return techRidersStore.liveRiders[0]?.id ?? techRidersStore.archivedRiders[0]?.id ?? null
}

function selectRider(riderId) {
  if (!riderId || riderId === selectedRiderId.value) return
  selectedRiderId.value = riderId
  localStorage.setItem(rememberedKey(), riderId)
  syncQuery()
  techRidersStore.fetchActive(bandSpaceId.value, riderId)
}

// Rider and tab both live in the query so a reload or a shared link restores the whole view.
function syncQuery() {
  const next = { ...route.query, rider: selectedRiderId.value ?? undefined, tab: activeTab.value }
  if (route.query.rider !== next.rider || route.query.tab !== next.tab) {
    router.replace({ query: next })
  }
}

async function load() {
  if (!bandSpaceId.value) return
  await techRidersStore.fetchRiders(bandSpaceId.value)

  const initial = resolveInitialRider()
  if (initial) {
    selectRider(initial)
  }
}

function openCreateDialog() {
  formDialogMode.value = 'create'
  formDialogOpen.value = true
}

function openRenameDialog() {
  formDialogMode.value = 'rename'
  formDialogOpen.value = true
}

function handleSaved(saved) {
  if (formDialogMode.value === 'create') {
    selectRider(saved.id)
  }
}

function confirmArchive() {
  confirm.require({
    message: `« ${rider.value.name} » sera déplacé dans les archives. Vous pourrez le restaurer plus tard.`,
    header: 'Archiver le tech rider',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Archiver',
    rejectLabel: 'Annuler',
    accept: async () => {
      try {
        await techRidersStore.archiveTechRider(bandSpaceId.value, selectedRiderId.value)
        toast.add({ severity: 'success', summary: 'Tech rider archivé', life: 3000 })
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
      }
    }
  })
}

async function handleUnarchive() {
  try {
    await techRidersStore.unarchiveTechRider(bandSpaceId.value, selectedRiderId.value)
    toast.add({ severity: 'success', summary: 'Tech rider restauré', life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
  }
}

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  })
}

watch(activeTab, syncQuery)

watch(
  () => route.query.tab,
  () => {
    activeTab.value = tabFromQuery()
  }
)

// Switching band space keeps this route mounted, only the id param changes.
watch(bandSpaceId, () => {
  techRidersStore.clearActive()
  selectedRiderId.value = null
  load()
})

onMounted(load)
</script>
