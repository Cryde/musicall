<template>
  <div class="flex-auto py-6 lg:py-8 px-8 lg:px-20">
    <div class="grid grid-cols-12 gap-4 py-6 lg:py-8">
      <div class="col-span-12 lg:col-span-10 xl:col-span-10 flex-auto">
        <div class="flex flex-col lg:flex-row gap-6">
          <!-- Sidebar menu -->
          <div class="lg:w-56 shrink-0">
            <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4">
              <nav class="flex flex-row lg:flex-col gap-1">
                <button
                  v-for="section in sections"
                  :key="section.key"
                  @click="activeSection = section.key"
                  :class="[
                    'w-full text-left px-4 py-2.5 rounded-lg transition-colors duration-150 text-sm font-medium',
                    activeSection === section.key
                      ? 'bg-primary text-primary-contrast'
                      : 'text-surface-600 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800'
                  ]"
                >
                  {{ section.label }}
                </button>
              </nav>
            </div>
          </div>

          <!-- Content area -->
          <div class="flex-1 min-w-0">
            <MembersSection v-if="activeSection === 'members'" />
            <ComingSoonSection v-else :title="activeSectionLabel" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import ComingSoonSection from '../../components/BandSpace/Settings/ComingSoonSection.vue'
import MembersSection from '../../components/BandSpace/Settings/MembersSection.vue'

const sections = [
  { key: 'members', label: 'Membres' },
  { key: 'general', label: 'Général' },
  { key: 'danger', label: 'Zone de danger' }
]

const activeSection = ref('members')

const activeSectionLabel = computed(
  () => sections.find((s) => s.key === activeSection.value)?.label ?? ''
)
</script>
