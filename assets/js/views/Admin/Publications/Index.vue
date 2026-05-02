<template>
  <div class="flex flex-col gap-6">
    <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-100">
      Gestion des publications
    </h1>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <AdminModuleCard
        label="Publications en attente"
        description="Modérer les publications soumises par les utilisateurs"
        icon="pi-file-edit"
        color="#6366f1"
        route="admin_publications_pending"
        :badge-count="notificationStore.pendingPublications"
      />

      <AdminModuleCard
        label="Galeries en attente"
        description="Modérer les galeries d'images soumises"
        icon="pi-images"
        color="#06b6d4"
        route="admin_galleries_pending"
        :badge-count="notificationStore.pendingGalleries"
      />

      <AdminModuleCard
        label="Cours"
        description="Les cours partagent la même infrastructure que les publications"
        icon="pi-book"
        color="#22c55e"
        coming-soon
      />

      <AdminModuleCard
        label="Derniers commentaires"
        description="Vue d'ensemble des commentaires récents et leur modération"
        icon="pi-comment"
        color="#f59e0b"
        coming-soon
      />
    </div>
  </div>
</template>

<script setup>
import AdminModuleCard from '../../../components/Admin/AdminModuleCard.vue'
import { useNotificationStore } from '../../../store/notification/notification.js'

const notificationStore = useNotificationStore()
</script>
