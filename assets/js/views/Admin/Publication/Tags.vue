<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold">Tags</h1>
      <Button label="Nouveau tag" icon="pi pi-plus" @click="showCreateModal = true" />
    </div>

    <div v-if="isLoading && !tags.length" class="space-y-3">
      <div v-for="i in 5" :key="i" class="h-12 bg-surface-100 dark:bg-surface-800 animate-pulse rounded" />
    </div>

    <DataTable
      v-else
      :value="tags"
      :paginator="tags.length > ROWS_PER_PAGE"
      :rows="ROWS_PER_PAGE"
      :rowsPerPageOptions="[10, 25, 50]"
      stripedRows
      class="text-sm"
    >
      <Column field="label" header="Label" sortable />
      <Column field="slug" header="Slug" sortable>
        <template #body="{ data }">
          <code class="text-xs">{{ data.slug }}</code>
        </template>
      </Column>
      <Column field="publication_count" header="Publications" sortable>
        <template #body="{ data }">
          <Tag :value="data.publication_count" :severity="data.publication_count > 0 ? 'info' : 'secondary'" />
        </template>
      </Column>
      <Column field="creation_datetime" header="Créé le" sortable>
        <template #body="{ data }">{{ formatDate(data.creation_datetime) }}</template>
      </Column>
      <Column header="Actions" :style="{ width: '5rem' }">
        <template #body="{ data }">
          <Button
            v-tooltip.left="'Supprimer'"
            icon="pi pi-trash"
            severity="danger"
            size="small"
            text
            rounded
            :loading="removingId === data.id"
            aria-label="Supprimer le tag"
            @click="confirmDelete(data)"
          />
        </template>
      </Column>
      <template #empty>
        <div class="text-center py-8 text-surface-500 dark:text-surface-400">
          Aucun tag pour le moment.
        </div>
      </template>
    </DataTable>

    <Dialog v-model:visible="showCreateModal" modal header="Nouveau tag" :style="{ width: '420px' }">
      <div class="flex flex-col gap-2">
        <label for="new-tag-label" class="font-medium">Label</label>
        <InputText
          id="new-tag-label"
          v-model="newLabel"
          placeholder="Ex : Metal, Interview, Tour…"
          autofocus
          :disabled="isCreating"
          @keyup.enter="handleCreate"
        />
        <small class="text-surface-500">Le slug sera généré automatiquement. Si le tag existe déjà, il sera retourné.</small>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" text :disabled="isCreating" @click="showCreateModal = false" />
        <Button label="Créer" icon="pi pi-plus" :loading="isCreating" :disabled="!canCreate" @click="handleCreate" />
      </template>
    </Dialog>

    <ConfirmDialog />
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import tagsApi from '../../../api/admin/tags.js'
import { formatDate } from '../../../utils/date.js'

const ROWS_PER_PAGE = 25

useTitle('Tags - Admin - MusicAll')

const tags = ref([])
const isLoading = ref(false)
const showCreateModal = ref(false)
const newLabel = ref('')
const isCreating = ref(false)
const removingId = ref(null)

const toast = useToast()
const confirm = useConfirm()

const canCreate = computed(() => newLabel.value.trim().length > 0 && !isCreating.value)

async function loadTags() {
  isLoading.value = true
  try {
    tags.value = await tagsApi.list()
  } catch (e) {
    console.error('Failed to load tags:', e)
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de charger les tags.',
      life: 4000
    })
  } finally {
    isLoading.value = false
  }
}

async function handleCreate() {
  if (!canCreate.value) return
  isCreating.value = true
  try {
    const created = await tagsApi.create(newLabel.value.trim())
    const existing = tags.value.find((t) => t.id === created.id)
    if (existing) {
      toast.add({
        severity: 'info',
        summary: 'Tag existant',
        detail: `Le tag « ${created.label} » existait déjà.`,
        life: 3000
      })
    } else {
      tags.value = [...tags.value, created].sort((a, b) => a.label.localeCompare(b.label))
      toast.add({ severity: 'success', summary: 'Tag créé', life: 3000 })
    }
    showCreateModal.value = false
    newLabel.value = ''
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Action impossible',
      detail: e?.response?.data?.detail || 'Une erreur est survenue.',
      life: 4000
    })
  } finally {
    isCreating.value = false
  }
}

function confirmDelete(tag) {
  const usageHint =
    tag.publication_count > 0
      ? `Ce tag est utilisé sur ${tag.publication_count} publication(s). La liaison sera supprimée.`
      : "Ce tag n'est utilisé sur aucune publication."
  confirm.require({
    message: `Supprimer le tag « ${tag.label} » ? ${usageHint}`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer',
    acceptClass: 'p-button-danger',
    accept: () => handleDelete(tag)
  })
}

async function handleDelete(tag) {
  removingId.value = tag.id
  try {
    await tagsApi.remove(tag.id)
    tags.value = tags.value.filter((t) => t.id !== tag.id)
    toast.add({ severity: 'success', summary: 'Tag supprimé', life: 3000 })
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Action impossible',
      detail: e?.response?.data?.detail || 'Une erreur est survenue.',
      life: 4000
    })
  } finally {
    removingId.value = null
  }
}

onMounted(loadTags)
</script>
