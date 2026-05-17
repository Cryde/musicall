export default {
  path: 'admin',
  component: () => import('../components/AppAdminLayout.vue'),
  meta: { isAuthRequired: true, isAdminRequired: true },
  children: [
    {
      name: 'admin_dashboard',
      path: '',
      component: () => import('../views/Admin/Dashboard.vue')
    },
    {
      name: 'admin_users_dashboard',
      path: 'users',
      component: () => import('../views/Admin/UserDashboard.vue')
    },
    {
      name: 'admin_publications_index',
      path: 'publications',
      component: () => import('../views/Admin/Publications/Index.vue')
    },
    {
      name: 'admin_publications_pending',
      path: 'publications/pending',
      component: () => import('../views/Admin/Publication/PendingList.vue')
    },
    {
      name: 'admin_publications_delete',
      path: 'publications/delete',
      component: () => import('../views/Admin/Publication/PublishedList.vue')
    },
    {
      name: 'admin_publications_tags',
      path: 'publications/tags',
      component: () => import('../views/Admin/Publication/Tags.vue')
    },
    {
      name: 'admin_galleries_pending',
      path: 'galleries/pending',
      component: () => import('../views/Admin/Gallery/PendingList.vue')
    },
    {
      name: 'admin_annuaire_index',
      path: 'annuaire',
      component: () => import('../views/Admin/Annuaire/Index.vue')
    },
    {
      name: 'admin_forum_index',
      path: 'forum',
      component: () => import('../views/Admin/Forum/Index.vue')
    },
    {
      name: 'admin_band_space_coming_soon',
      path: 'band-spaces',
      component: () => import('../views/Admin/ComingSoonPage.vue'),
      props: {
        title: 'Band Space',
        icon: 'pi-objects-column',
        description: 'Espaces de groupe et leurs activités.'
      }
    }
  ]
}
