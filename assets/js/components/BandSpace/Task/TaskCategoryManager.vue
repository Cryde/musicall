<template>
  <Drawer v-model:visible="visibleModel" position="right" header="Gérer les catégories" class="w-full md:w-[24rem]">
    <div class="flex flex-col gap-4">
      <!-- Existing categories -->
      <div class="flex flex-col gap-2">
        <div
          v-for="category in categories"
          :key="category.id"
          class="flex items-center gap-3 p-2 rounded-lg bg-surface-50 dark:bg-surface-800"
        >
          <span
            class="w-3 h-3 rounded-full flex-shrink-0"
            :style="{ backgroundColor: category.color }"
          ></span>

          <template v-if="editingId === category.id">
            <InputText
              v-model="editingName"
              class="flex-1 text-sm"
              size="small"
              @keydown.enter="saveRename(category)"
              @keydown.escape="cancelRename"
            />
            <Button icon="pi pi-check" aria-label="Valider" text rounded size="small" @click="saveRename(category)" />
            <Button icon="pi pi-times" aria-label="Annuler" text rounded size="small" @click="cancelRename" />
          </template>
          <template v-else>
            <span
              class="flex-1 text-sm text-surface-700 dark:text-surface-200 cursor-pointer"
              @click="startRename(category)"
            >
              {{ category.name }}
            </span>
            <Button
              icon="pi pi-trash"
              text
              rounded
              size="small"
              severity="danger"
              v-tooltip.left="'Supprimer'"
              aria-label="Supprimer"
              @click="handleDelete(category)"
            />
          </template>
        </div>
      </div>

      <!-- Create new -->
      <div class="border-t border-surface-200 dark:border-surface-700 pt-4">
        <h4 class="text-sm font-medium text-surface-700 dark:text-surface-200 mb-2">Nouvelle catégorie</h4>
        <form @submit.prevent="handleCreate" class="flex gap-2">
          <InputText
            v-model="newName"
            placeholder="Nom de la catégorie"
            class="flex-1"
            size="small"
          />
          <Button
            type="submit"
            label="Créer"
            size="small"
            :disabled="!newName.trim()"
            :loading="isCreating"
          />
        </form>
      </div>
    </div>

  </Drawer>
</template>

<script setup>
import Button from 'primevue/button'
import Drawer from 'primevue/drawer'
import InputText from 'primevue/inputtext'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useBandTasksStore } from '../../../store/bandSpace/bandSpaceTasks.js'

const props = defineProps({
  visible: { type: Boolean, default: false },
  bandSpaceId: { type: String, required: true }
})

const emit = defineEmits(['update:visible'])
const confirm = useConfirm()
const toast = useToast()
const tasksStore = useBandTasksStore()

const visibleModel = computed({
  get: () => props.visible,
  set: (val) => emit('update:visible', val)
})

const categories = computed(() => tasksStore.categories)

const newName = ref('')
const isCreating = ref(false)
const editingId = ref(null)
const editingName = ref('')

function startRename(category) {
  editingId.value = category.id
  editingName.value = category.name
}

function cancelRename() {
  editingId.value = null
  editingName.value = ''
}

async function saveRename(category) {
  if (!editingName.value.trim()) return
  try {
    await tasksStore.updateCategory(props.bandSpaceId, category.id, {
      name: editingName.value.trim()
    })
    cancelRename()
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  }
}

async function handleCreate() {
  if (!newName.value.trim()) return
  isCreating.value = true
  try {
    await tasksStore.createCategory(props.bandSpaceId, { name: newName.value.trim() })
    newName.value = ''
    toast.add({ severity: 'success', summary: 'Catégorie créée', life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  } finally {
    isCreating.value = false
  }
}

function handleDelete(category) {
  const linkedCount = tasksStore.tasks.filter((t) => t.category_id === category.id).length
  const message =
    linkedCount > 0
      ? `Supprimer la catégorie "${category.name}" ? ${linkedCount} tâche(s) deviendront sans catégorie.`
      : `Supprimer la catégorie "${category.name}" ?`

  confirm.require({
    message,
    header: 'Confirmer la suppression',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await tasksStore.deleteCategory(props.bandSpaceId, category.id)
        toast.add({ severity: 'success', summary: 'Catégorie supprimée', life: 3000 })
      } catch (e) {
        toast.add({ severity: 'error', summary: e.message, life: 5000 })
      }
    }
  })
}
</script>
