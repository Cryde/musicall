<template>
  <div class="flex-auto py-6 lg:py-8 px-4 lg:px-20">
    <div class="mb-6 lg:mb-8">
      <h1 class="text-2xl lg:text-3xl font-semibold text-surface-900 dark:text-surface-0">
        Tableau de bord
      </h1>
      <p v-if="currentSpace?.name" class="text-surface-500 dark:text-surface-400 mt-1">
        {{ currentSpace.name }}
      </p>
    </div>

    <div v-if="bandSpaceId" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 flex flex-col gap-6">
        <ActivityWidget :band-space-id="bandSpaceId" />
        <AgendaWidget :band-space-id="bandSpaceId" />
      </div>
      <div class="flex flex-col gap-6">
        <TasksWidget :band-space-id="bandSpaceId" />
        <StorageWidget :band-space-id="bandSpaceId" />
        <FinanceWidget :band-space-id="bandSpaceId" />
        <InvitationsWidget v-if="isAdmin" :band-space-id="bandSpaceId" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import ActivityWidget from '../../components/BandSpace/Dashboard/ActivityWidget.vue'
import AgendaWidget from '../../components/BandSpace/Dashboard/AgendaWidget.vue'
import FinanceWidget from '../../components/BandSpace/Dashboard/FinanceWidget.vue'
import InvitationsWidget from '../../components/BandSpace/Dashboard/InvitationsWidget.vue'
import StorageWidget from '../../components/BandSpace/Dashboard/StorageWidget.vue'
import TasksWidget from '../../components/BandSpace/Dashboard/TasksWidget.vue'
import { useBandSpaceNavigation } from '../../composables/useBandSpaceNavigation.js'

const route = useRoute()
const { currentSpace } = useBandSpaceNavigation()

const bandSpaceId = computed(() => route.params.id || null)
const isAdmin = computed(() => currentSpace.value?.role === 'admin')
</script>
