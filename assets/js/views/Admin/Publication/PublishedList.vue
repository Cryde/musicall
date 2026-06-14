<template>
  <div class="flex flex-col gap-6">
    <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-100">
      Supprimer une publication
    </h1>

    <Message severity="warn" :closable="false">
      <strong>Suppression définitive.</strong> Cette action retire la publication, ses images,
      sa couverture, ses commentaires, ses votes et ses statistiques de vues. Aucune annulation possible.
    </Message>

    <div class="flex flex-col gap-2">
      <label for="search-input" class="text-sm font-medium text-surface-700 dark:text-surface-200">
        Rechercher par titre ou contenu
      </label>
      <div class="flex gap-2">
        <InputText
          id="search-input"
          v-model="searchTerm"
          placeholder="Au moins 3 caractères…"
          class="flex-1"
          @keyup.enter="handleSearch"
        />
        <Button
          icon="pi pi-search"
          label="Rechercher"
          :loading="adminPublicationStore.isSearching"
          :disabled="searchTerm.trim().length < 3"
          @click="handleSearch"
        />
      </div>
    </div>

    <DataTable
      :value="adminPublicationStore.searchResults"
      :loading="adminPublicationStore.isSearching"
      stripedRows
      tableStyle="min-width: 50rem"
    >
      <template #empty>
        <div class="text-center py-8 text-surface-500">
          {{ searched ? 'Aucune publication ne correspond à ce terme.' : 'Saisissez un terme et lancez une recherche.' }}
        </div>
      </template>

      <Column field="title" header="Titre" sortable />

      <Column field="category.title" header="Catégorie" sortable />

      <Column field="author.username" header="Auteur" sortable />

      <Column field="publication_datetime" header="Publié le" sortable>
        <template #body="{ data }">
          {{ formatDate(data.publication_datetime) }}
        </template>
      </Column>

      <Column header="Actions" style="width: 200px">
        <template #body="{ data }">
          <div class="flex gap-2">
            <Button
              v-tooltip.top="'Voir la publication'"
              aria-label="Voir la publication"
              icon="pi pi-eye"
              severity="info"
              text
              rounded
              as="router-link"
              :to="{ name: data.category?.is_course ? 'app_course_show' : 'app_publication_show', params: { slug: data.slug } }"
              target="_blank"
            />
            <Button
              v-tooltip.top="'Supprimer définitivement'"
              aria-label="Supprimer définitivement"
              icon="pi pi-trash"
              severity="danger"
              text
              rounded
              @click="confirmDelete(data)"
            />
          </div>
        </template>
      </Column>
    </DataTable>
  </div>
</template>

<script setup>
import { format, parseISO } from 'date-fns'
import { fr } from 'date-fns/locale'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { onUnmounted, ref } from 'vue'
import { useAdminPublicationStore } from '../../../store/admin/publication.js'

const confirm = useConfirm()
const toast = useToast()
const adminPublicationStore = useAdminPublicationStore()

const searchTerm = ref('')
const searched = ref(false)

onUnmounted(() => {
  adminPublicationStore.clearSearch()
})

function formatDate(iso) {
  if (!iso) return '—'
  try {
    return format(parseISO(iso), 'd MMM yyyy', { locale: fr })
  } catch {
    return iso
  }
}

async function handleSearch() {
  if (searchTerm.value.trim().length < 3) return
  searched.value = true
  await adminPublicationStore.searchPublications(searchTerm.value)
}

function confirmDelete(publication) {
  confirm.require({
    message: `Supprimer définitivement la publication « ${publication.title} » ? Tous les commentaires, votes et statistiques seront perdus.`,
    header: 'Confirmation de suppression',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await adminPublicationStore.deletePublication(publication.id)
        toast.add({
          severity: 'success',
          summary: 'Publication supprimée',
          detail: `« ${publication.title} » a été supprimée.`,
          life: 3000
        })
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Une erreur est survenue lors de la suppression.',
          life: 4000
        })
      }
    }
  })
}
</script>
