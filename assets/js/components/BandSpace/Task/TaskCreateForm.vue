<template>
  <Drawer v-model:visible="visibleModel" position="right" header="Nouvelle tâche" class="w-full md:w-[28rem]">
    <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium">Titre</label>
        <InputText v-model="form.title" placeholder="Ex : Réserver la salle" />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium">Description</label>
        <Textarea v-model="form.description" rows="3" placeholder="Détails de la tâche..." autoResize />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium">Statut</label>
          <Select
            v-model="form.status"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium">Priorité</label>
          <Select
            v-model="form.priority"
            :options="priorityOptions"
            optionLabel="label"
            optionValue="value"
          />
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium">Catégorie</label>
        <Select
          v-model="form.categoryId"
          :options="categories"
          optionLabel="name"
          optionValue="id"
          placeholder="Aucune"
          showClear
        />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium">Assignés</label>
        <MultiSelect
          v-model="form.assigneeIds"
          :options="members"
          optionLabel="username"
          optionValue="user_id"
          placeholder="Sélectionner des membres"
          display="chip"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium">Date d'échéance</label>
        <DatePicker v-model="form.dueDate" dateFormat="yy-mm-dd" showIcon showButtonBar />
      </div>

      <Button type="submit" label="Créer la tâche" :loading="tasksStore.isCreating" :disabled="!form.title.trim()" />
    </form>
  </Drawer>
</template>

<script setup>
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import Drawer from 'primevue/drawer'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { computed, reactive, watch } from 'vue'
import { useBandTasksStore } from '../../../store/bandSpace/bandSpaceTasks.js'

const props = defineProps({
  visible: { type: Boolean, default: false },
  bandSpaceId: { type: String, required: true }
})

const emit = defineEmits(['update:visible', 'created'])
const toast = useToast()
const tasksStore = useBandTasksStore()

const visibleModel = computed({
  get: () => props.visible,
  set: (val) => emit('update:visible', val)
})

const categories = computed(() => tasksStore.categories)
const members = computed(() => tasksStore.members)

const statusOptions = [
  { label: 'À faire', value: 'todo' },
  { label: 'En cours', value: 'in_progress' },
  { label: 'Terminé', value: 'done' }
]

const priorityOptions = [
  { label: 'Normal', value: 'normal' },
  { label: 'Haute', value: 'high' },
  { label: 'Urgent', value: 'urgent' }
]

const form = reactive({
  title: '',
  description: '',
  status: 'todo',
  priority: 'normal',
  categoryId: null,
  assigneeIds: [],
  dueDate: null
})

watch(
  () => props.visible,
  (val) => {
    if (val) {
      form.title = ''
      form.description = ''
      form.status = 'todo'
      form.priority = 'normal'
      form.categoryId = null
      form.assigneeIds = []
      form.dueDate = null
    }
  }
)

async function handleSubmit() {
  if (!form.title.trim()) return
  const payload = {
    title: form.title.trim(),
    status: form.status,
    priority: form.priority
  }
  if (form.description) payload.description = form.description
  if (form.categoryId) payload.category_id = form.categoryId
  if (form.assigneeIds.length > 0) payload.assignee_ids = form.assigneeIds
  if (form.dueDate) {
    const d = new Date(form.dueDate)
    payload.due_date = d.toISOString().split('T')[0]
  }

  try {
    await tasksStore.createTask(props.bandSpaceId, payload)
    emit('created')
    visibleModel.value = false
    toast.add({ severity: 'success', summary: 'Tâche créée', life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  }
}
</script>
