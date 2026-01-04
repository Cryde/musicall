export default [
  {
    name: 'admin_dashboard',
    path: 'admin',
    component: () => import('../views/Admin/Dashboard.vue'),
    meta: { isAuthRequired: true, isAdminRequired: true }
  },
  {
    name: 'admin_publications_pending',
    path: 'admin/publications/pending',
    component: () => import('../views/Admin/Publication/PendingList.vue'),
    meta: { isAuthRequired: true, isAdminRequired: true }
  }
]
