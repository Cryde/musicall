<template>
    <nav class="relative flex items-center justify-between gap-8 px-8 lg:px-20 py-4 bg-surface-0 dark:bg-surface-900">
      <div class="flex items-center gap-4">
        <div class="bg-[#5b87ae] dark:bg-transparent rounded-xs px-4 py-2">
          <img
            src="../../../image/logo.png"
            alt="Logo"
            class="h-4 w-auto"
          />
        </div>
      </div>
      <span
        v-styleclass="{
            selector: '@next',
            enterFromClass: 'hidden',
            enterActiveClass: 'animate-fadein',
            leaveToClass: 'hidden',
            leaveActiveClass: 'animate-fadeout',
            hideOnOutsideClick: true
          }"
        class="cursor-pointer block lg:hidden text-surface-900 dark:text-surface-100"
      >
        <i class="pi pi-bars text-xl! leading-normal!"/>
      </span>

      <div
          class="hidden lg:flex flex-1 items-center justify-between absolute lg:static w-full bg-surface-0 dark:bg-surface-900 left-0 top-full z-100 shadow lg:shadow-none border lg:border-0 border-surface-800"
      >
        <div class="flex-1 flex items-start gap-4 px-6 lg:px-0 py-4 lg:py-0 flex-col lg:flex-row">

          <Select
            :modelValue="currentSpace"
            :options="selectOptions"
            optionLabel="name"
            optionGroupLabel="label"
            optionGroupChildren="items"
            placeholder="Selectionnez votre band space"
            class="w-full md:w-56 mr-2"
            :disabled="bandSpaceStore.isCreating"
            @change="handleSpaceChange"
          >
            <template #optiongroup="slotProps">
              <div class="flex items-center" v-if="slotProps.option.label">
                <div>{{ slotProps.option.label }}</div>
              </div>
            </template>
            <template #option="slotProps">
              <div class="flex items-center gap-2">
                <i v-if="slotProps.option.isCreateAction" class="pi pi-plus" />
                <span :class="{ 'font-semibold': slotProps.option.isCreateAction }">{{ slotProps.option.name }}</span>
              </div>
            </template>
          </Select>

          <template v-if="currentSpaceId">
            <RouterLink
              v-for="(item, i) in navs"
              :key="i"
              :to="item.to"
              custom
              v-slot="{ isExactActive, href, navigate }"
            >
              <a
                :href="href"
                @click="(e) => { if (!bandSpaceStore.isCreating) navigate(e) }"
                :class="[
                  'flex items-center gap-2 p-2 rounded-lg transition-colors duration-150 border w-full lg:w-auto',
                  bandSpaceStore.isCreating
                  ? 'cursor-not-allowed opacity-50'
                  : 'cursor-pointer',
                  isExactActive
                  ? 'bg-surface-100 dark:bg-surface-800 border-surface-200 dark:border-surface-700'
                  : 'border-transparent hover:bg-surface-50 dark:hover:bg-surface-800 hover:border-surface-200 dark:hover:border-surface-700'
                ]"
              >
                <span class="font-medium">{{ item.label }}</span>
              </a>
            </RouterLink>
          </template>

          <RouterLink :to="{name: 'app_home'}" custom v-slot="{ href, navigate }">
            <a
              :href="href"
              @click="(e) => { if (!bandSpaceStore.isCreating) navigate(e) }"
              :class="[
                'flex items-center lg:ml-10 text-xs gap-2 p-2 rounded-lg transition-colors duration-150 border w-full lg:w-auto border-transparent',
                bandSpaceStore.isCreating
                ? 'cursor-not-allowed opacity-50'
                : 'cursor-pointer hover:underline'
              ]"
            >
              <span class="font-medium">back to musicall</span>
            </a>
          </RouterLink>
        </div>
          <template v-if="!userSecurityStore.isAuthenticatedLoading">
          <div v-if="userSecurityStore.isAuthenticated">
              <Avatar :label="userSecurityStore.user.username.charAt(0)" class="mr-2 cursor-pointer" shape="circle"  @click="$refs.userMenu.toggle($event)" />
              <Menu ref="userMenu" :popup="true" :model="menuItems" />
          </div>
          <div v-else class="flex border-t lg:border-t-0 border-surface py-4 lg:py-0 mt-4 lg:mt-0 gap-4">
              <Button asChild v-slot="slotProps" severity="info" text>
                  <RouterLink :to="{name: 'app_login'}" :class="slotProps.class">Se connecter</RouterLink>
              </Button>
              <Button label="Register" severity="info" />
          </div>
          </template>
      </div>
    </nav>

    <CreateBandSpaceModal @created="handleBandSpaceCreated" />
</template>
<script setup>
import Menu from 'primevue/menu'
import Select from 'primevue/select'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBandSpaceStore } from '../../store/bandSpace/bandSpace.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import CreateBandSpaceModal from '../../components/BandSpace/CreateBandSpaceModal.vue'

const CREATE_ACTION_ID = '__create__'
const LAST_BAND_SPACE_KEY = 'lastBandSpaceId'

const bandSpaceStore = useBandSpaceStore()
const userSecurityStore = useUserSecurityStore()
const route = useRoute()
const router = useRouter()

const menuItems = ref([])

// Get current space from URL
const currentSpaceId = computed(() => route.params.id)

const currentSpace = computed(() => {
  if (!currentSpaceId.value) return null
  return bandSpaceStore.spaces.find(s => s.id === currentSpaceId.value) || null
})

const selectOptions = computed(() => {
  const options = []

  if (bandSpaceStore.spaces.length > 0) {
    options.push({
      label: '',
      items: bandSpaceStore.spaces
    })
  }

  options.push({
    label: '',
    items: [
      { id: CREATE_ACTION_ID, name: 'Créer un Band Space', isCreateAction: true }
    ]
  })

  return options
})

// Save last used space to localStorage when route changes
watch(currentSpaceId, (newId) => {
  if (newId) {
    localStorage.setItem(LAST_BAND_SPACE_KEY, newId)
  }
}, { immediate: true })

function handleSpaceChange(event) {
  const selected = event.value
  if (!selected) return

  if (selected.id === CREATE_ACTION_ID) {
    bandSpaceStore.openCreateModal()
    return
  }

  // Navigate to the selected space's dashboard
  router.push({ name: 'app_band_dashboard', params: { id: selected.id } })
}

function handleBandSpaceCreated(newSpace) {
  // Navigate to the newly created space
  router.push({ name: 'app_band_dashboard', params: { id: newSpace.id } })
}

onMounted(() => {
  nextTick(() => {
    menuItems.value = [
      {
        label: userSecurityStore?.user?.username,
        items: [
          {
            label: 'Se déconnecter',
            icon: 'pi pi-sign-out',
            command: () => {
              userSecurityStore.logout()
            }
          }
        ]
      }
    ]
  })
})

const navs = computed(() => [
  {
    label: 'Dashboard',
    to: { name: 'app_band_dashboard', params: { id: currentSpaceId.value } }
  },
  {
    label: 'Agenda',
    to: { name: 'app_band_agenda', params: { id: currentSpaceId.value } }
  },
  {
    label: 'Notes',
    to: { name: 'app_band_notes', params: { id: currentSpaceId.value } }
  },
  {
    label: 'Social',
    to: { name: 'app_band_social', params: { id: currentSpaceId.value } }
  },
  {
    label: 'Fichiers',
    to: { name: 'app_band_files', params: { id: currentSpaceId.value } }
  },
  {
    label: 'Paramètres',
    to: { name: 'app_band_parameters', params: { id: currentSpaceId.value } }
  }
])
</script>
