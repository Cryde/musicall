<template>
  <div class="py-6 md:py-10">
    <div class="flex flex-col gap-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex flex-col gap-2">
          <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">Mes galeries photos</h1>
          <p class="text-surface-500 dark:text-surface-400">
            <template v-if="userGalleriesStore.galleries.length > 0">
              Vous avez {{ userGalleriesStore.galleries.length }} galerie(s)
            </template>
            <template v-else> Gerez vos galeries photos </template>
          </p>
        </div>
        <Button label="Ajouter une galerie" icon="pi pi-images" severity="info" @click="openAddModal" />
      </div>

      <div v-if="userGalleriesStore.isLoading" class="flex justify-center py-12">
        <ProgressSpinner />
      </div>

      <div
        v-else-if="userGalleriesStore.galleries.length === 0"
        class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm"
      >
        <div class="flex flex-col items-center justify-center py-12 text-surface-500 dark:text-surface-400">
          <i class="pi pi-images text-4xl mb-4" />
          <p class="text-lg font-medium">Vous n'avez pas encore de galeries</p>
          <p class="text-sm mt-2">Cliquez sur "Ajouter une galerie" pour en creer une</p>
        </div>
      </div>

      <template v-else>
        <DataTable :value="userGalleriesStore.galleries" stripedRows tableStyle="min-width: 50rem">
          <Column header="Couverture" style="width: 80px">
            <template #body="{ data }">
              <img
                v-if="data.cover_image_url"
                :src="data.cover_image_url"
                :alt="data.title"
                class="w-12 h-12 object-cover rounded"
              />
              <div
                v-else
                class="w-12 h-12 bg-surface-200 dark:bg-surface-700 rounded flex items-center justify-center"
              >
                <i class="pi pi-image text-surface-400" />
              </div>
            </template>
          </Column>

          <Column field="title" header="Titre" style="min-width: 200px">
            <template #body="{ data }">
              <span class="font-medium">{{ data.title }}</span>
            </template>
          </Column>

          <Column field="image_count" header="Photos" style="min-width: 100px">
            <template #body="{ data }">
              {{ data.image_count }} photo(s)
            </template>
          </Column>

          <Column field="status_label" header="Statut" style="min-width: 120px">
            <template #body="{ data }">
              <Tag :value="data.status_label" :severity="getStatusSeverity(data.status)" />
            </template>
          </Column>

          <Column header="Actions" style="min-width: 180px">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button
                  v-if="data.status === STATUS_DRAFT"
                  v-tooltip.top="'Soumettre la galerie'"
                  icon="pi pi-send"
                  severity="success"
                  text
                  rounded
                  size="small"
                  @click="handleSubmit(data)"
                />
                <Button
                  v-if="data.slug"
                  v-tooltip.top="'Voir la galerie'"
                  icon="pi pi-eye"
                  severity="info"
                  text
                  rounded
                  size="small"
                  @click="handleView(data)"
                />
                <Button
                  v-if="data.status === STATUS_DRAFT"
                  v-tooltip.top="'Modifier la galerie'"
                  icon="pi pi-pencil"
                  severity="secondary"
                  text
                  rounded
                  size="small"
                  @click="handleEdit(data)"
                />
                <Button
                  v-if="data.status === STATUS_DRAFT"
                  v-tooltip.top="'Supprimer la galerie'"
                  icon="pi pi-trash"
                  severity="danger"
                  text
                  rounded
                  size="small"
                  @click="confirmDelete(data)"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </div>

    <!-- Add Gallery Modal -->
    <Dialog
      v-model:visible="showAddModal"
      modal
      header="Nouvelle galerie"
      :style="{ width: '25rem' }"
    >
      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2">
          <label for="title" class="font-medium">Titre de la galerie</label>
          <InputText
            id="title"
            v-model="newGalleryTitle"
            placeholder="Entrez le titre"
            :invalid="submitted && !newGalleryTitle.trim()"
          />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" text @click="closeAddModal" />
        <Button label="Creer" icon="pi pi-check" :loading="isCreating" @click="createGallery" />
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
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useUserGalleriesStore } from '../../../store/gallery/userGalleries.js'

useTitle('Mes galeries photos - MusicAll')

const STATUS_DRAFT = 1
const STATUS_ONLINE = 0
const STATUS_PENDING = 2

const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const userGalleriesStore = useUserGalleriesStore()

const showAddModal = ref(false)
const newGalleryTitle = ref('')
const isCreating = ref(false)
const submitted = ref(false)

onMounted(async () => {
  await userGalleriesStore.loadGalleries()
})

onUnmounted(() => {
  userGalleriesStore.clear()
})

function getStatusSeverity(status) {
  switch (status) {
    case STATUS_ONLINE:
      return 'success'
    case STATUS_PENDING:
      return 'warn'
    default:
      return 'secondary'
  }
}

function openAddModal() {
  showAddModal.value = true
  newGalleryTitle.value = ''
  submitted.value = false
}

function closeAddModal() {
  showAddModal.value = false
  newGalleryTitle.value = ''
  submitted.value = false
}

async function createGallery() {
  submitted.value = true
  if (!newGalleryTitle.value.trim()) return

  isCreating.value = true
  try {
    const gallery = await userGalleriesStore.createGallery(newGalleryTitle.value)
    trackUmamiEvent('gallery-create')
    closeAddModal()
    toast.add({
      severity: 'success',
      summary: 'Succes',
      detail: 'Galerie creee avec succes',
      life: 3000
    })
    // Redirect to edit page
    router.push({ name: 'app_user_gallery_edit', params: { id: gallery.id } })
  } catch {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de creer la galerie',
      life: 3000
    })
  } finally {
    isCreating.value = false
  }
}

function handleView(gallery) {
  if (gallery.status === STATUS_ONLINE) {
    router.push({ name: 'app_gallery_show', params: { slug: gallery.slug } })
  } else {
    router.push({ name: 'app_user_gallery_preview', params: { id: gallery.id } })
  }
}

function handleEdit(gallery) {
  router.push({ name: 'app_user_gallery_edit', params: { id: gallery.id } })
}

async function handleSubmit(gallery) {
  confirm.require({
    message:
      'Une fois mise en ligne vous ne pourrez plus modifier la galerie. Etes-vous sur de vouloir soumettre ?',
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Soumettre',
    accept: async () => {
      try {
        await userGalleriesStore.submitGallery(gallery.id)
        toast.add({
          severity: 'success',
          summary: 'Succes',
          detail: 'Galerie soumise pour validation',
          life: 3000
        })
      } catch (e) {
        const message = e.response?.data?.detail || 'Impossible de soumettre la galerie'
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: message,
          life: 5000
        })
      }
    }
  })
}

function confirmDelete(gallery) {
  confirm.require({
    message: 'Etes-vous sur de vouloir supprimer cette galerie ?',
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await userGalleriesStore.deleteGallery(gallery.id)
        toast.add({
          severity: 'success',
          summary: 'Succes',
          detail: 'Galerie supprimee avec succes',
          life: 3000
        })
      } catch {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Impossible de supprimer la galerie',
          life: 3000
        })
      }
    }
  })
}
</script>
