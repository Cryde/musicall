<template>
  <Drawer v-model:visible="visibleModel" position="right" :header="t('band_space_tasks.common.new_task')" class="w-full! md:w-[28rem]!">
    <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium">{{ t('band_space_tasks.field.title') }}</label>
        <InputText
          v-model="form.title"
          :placeholder="t('band_space_tasks.create_form.title_placeholder')"
          :aria-label="t('band_space_tasks.field.title')"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium">{{ t('band_space_tasks.field.description') }}</label>
        <Textarea
          v-model="form.description"
          rows="3"
          :placeholder="t('band_space_tasks.create_form.description_placeholder')"
          autoResize
          :aria-label="t('band_space_tasks.field.description')"
        />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium">{{ t('band_space_tasks.field.status') }}</label>
          <Select
            v-model="form.status"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            :aria-label="t('band_space_tasks.field.status')"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium">{{ t('band_space_tasks.field.priority') }}</label>
          <Select
            v-model="form.priority"
            :options="priorityOptions"
            optionLabel="label"
            optionValue="value"
            :aria-label="t('band_space_tasks.field.priority')"
          />
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium">{{ t('band_space_tasks.field.category') }}</label>
        <Select
          v-model="form.categoryId"
          :options="categories"
          optionLabel="name"
          optionValue="id"
          :placeholder="t('band_space_tasks.field.no_category')"
          showClear
          :aria-label="t('band_space_tasks.field.category')"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium">{{ t('band_space_tasks.field.assignees') }}</label>
        <MultiSelect
          v-model="form.assigneeIds"
          :options="members"
          optionLabel="display_name"
          optionValue="user_id"
          :placeholder="t('band_space_tasks.field.select_members')"
          display="chip"
          :aria-label="t('band_space_tasks.field.assignees')"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium">{{ t('band_space_tasks.field.due_date') }}</label>
        <DatePicker
          v-model="form.dueDate"
          dateFormat="yy-mm-dd"
          showIcon
          showButtonBar
          :aria-label="t('band_space_tasks.field.due_date')"
        />
      </div>

      <Button
        type="submit"
        :label="t('band_space_tasks.create_form.submit')"
        :loading="tasksStore.isCreating"
        :disabled="!form.title.trim()"
      />
    </form>
  </Drawer>
</template>

<script setup>
import { format } from 'date-fns'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import Drawer from 'primevue/drawer'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBandTasksStore } from '../../../store/bandSpace/bandSpaceTasks.js'

const props = defineProps({
  visible: { type: Boolean, default: false },
  bandSpaceId: { type: String, required: true }
})

const emit = defineEmits(['update:visible', 'created'])
const toast = useToast()
const { t } = useI18n()
const tasksStore = useBandTasksStore()

const visibleModel = computed({
  get: () => props.visible,
  set: (val) => emit('update:visible', val)
})

const categories = computed(() => tasksStore.categories)
const members = computed(() => tasksStore.members)

const statusOptions = computed(() => [
  { label: t('band_space_tasks.status.todo'), value: 'todo' },
  { label: t('band_space_tasks.status.in_progress'), value: 'in_progress' },
  { label: t('band_space_tasks.status.done'), value: 'done' }
])

const priorityOptions = computed(() => [
  { label: t('band_space_tasks.priority.normal'), value: 'normal' },
  { label: t('band_space_tasks.priority.high'), value: 'high' },
  { label: t('band_space_tasks.priority.urgent'), value: 'urgent' }
])

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
    payload.due_date = format(form.dueDate, 'yyyy-MM-dd')
  }

  try {
    await tasksStore.createTask(props.bandSpaceId, payload)
    emit('created')
    visibleModel.value = false
    toast.add({
      severity: 'success',
      summary: t('band_space_tasks.create_form.created'),
      life: 3000
    })
  } catch (e) {
    toast.add({ severity: 'error', summary: e.message, life: 5000 })
  }
}
</script>
