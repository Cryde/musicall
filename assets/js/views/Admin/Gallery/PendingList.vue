<template>
  <div class="flex flex-col gap-6">
    <div class="flex items-center gap-4">
      <RouterLink :to="{ name: 'admin_dashboard' }" class="text-surface-500 hover:text-surface-700 dark:hover:text-surface-300">
        <i class="pi pi-arrow-left" />
      </RouterLink>
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-100">Galeries en attente de validation</h1>
    </div>

    <DataTable
      :value="adminGalleryStore.pendingGalleries"
      :loading="adminGalleryStore.isLoading"
      stripedRows
      tableStyle="min-width: 50rem"
    >
      <template #empty>
        <div class="text-center py-8 text-surface-500">
          Il n'y a pas de galeries en attente de validation.
        </div>
      </template>

      <Column field="title" header="Titre" sortable />

      <Column field="author.username" header="Auteur" sortable />

      <Column field="image_count" header="Images" sortable style="width: 100px" />

      <Column header="Actions" style="width: 200px">
        <template #body="{ data }">
          <div class="flex gap-2">
            <Button
              icon="pi pi-eye"
              severity="info"
              text
              rounded
              v-tooltip.top="'Voir la galerie'"
              as="router-link"
              :to="{ name: 'app_user_gallery_preview', params: { id: data.id } }"
              target="_blank"
            />
            <Button
              icon="pi pi-check"
              severity="success"
              text
              rounded
              v-tooltip.top="'Valider la galerie'"
              @click="confirmApprove(data)"
            />
            <Button
              icon="pi pi-times"
              severity="danger"
              text
              rounded
              v-tooltip.top="'Rejeter la galerie'"
              @click="confirmReject(data)"
            />
          </div>
        </template>
      </Column>
    </DataTable>

    <ConfirmDialog />
  </div>
</template>

<script setup>
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import ConfirmDialog from 'primevue/confirmdialog'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { onMounted } from 'vue'
import { useAdminGalleryStore } from '../../../store/admin/gallery.js'
import { useNotificationStore } from '../../../store/notification/notification.js'

const confirm = useConfirm()
const toast = useToast()
const adminGalleryStore = useAdminGalleryStore()
const notificationStore = useNotificationStore()

onMounted(async () => {
  await adminGalleryStore.loadPendingGalleries()
})

function confirmApprove(gallery) {
  confirm.require({
    message: `Valider la galerie "${gallery.title}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-check-circle',
    acceptLabel: 'Valider',
    rejectLabel: 'Annuler',
    acceptClass: 'p-button-success',
    accept: async () => {
      try {
        await adminGalleryStore.approveGallery(gallery.id)
        await notificationStore.loadNotifications()
        toast.add({
          severity: 'success',
          summary: 'Galerie validée',
          detail: `La galerie "${gallery.title}" a été validée.`,
          life: 3000
        })
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Une erreur est survenue lors de la validation.',
          life: 3000
        })
      }
    }
  })
}

function confirmReject(gallery) {
  confirm.require({
    message: `Rejeter la galerie "${gallery.title}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Rejeter',
    rejectLabel: 'Annuler',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await adminGalleryStore.rejectGallery(gallery.id)
        await notificationStore.loadNotifications()
        toast.add({
          severity: 'info',
          summary: 'Galerie rejetée',
          detail: `La galerie "${gallery.title}" a été rejetée.`,
          life: 3000
        })
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Une erreur est survenue lors du rejet.',
          life: 3000
        })
      }
    }
  })
}
</script>
