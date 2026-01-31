// Mapping of URL slug to instrument slug in database
export const instrumentRouteMapping = {
  guitariste: 'guitare',
  batteur: 'batterie',
  bassiste: 'basse',
  chanteur: 'chant',
  pianiste: 'piano'
}

export default [
  {
    path: '/rechercher-un-musicien',
    name: 'app_search_musician',
    component: () => import('../views/Search/Index.vue'),
    meta: { isAuthRequired: false }
  },
  {
    path: '/rechercher-un-professeur',
    name: 'app_search_teacher',
    component: () => import('../views/Search/TeacherSearchTeaser.vue'),
    meta: { isAuthRequired: false }
  },
  {
    path: '/rechercher-un-guitariste',
    name: 'app_search_guitarist',
    component: () => import('../views/Search/Index.vue'),
    meta: { isAuthRequired: false, instrumentSlug: 'guitare' }
  },
  {
    path: '/rechercher-un-batteur',
    name: 'app_search_drummer',
    component: () => import('../views/Search/Index.vue'),
    meta: { isAuthRequired: false, instrumentSlug: 'batterie' }
  },
  {
    path: '/rechercher-un-bassiste',
    name: 'app_search_bassist',
    component: () => import('../views/Search/Index.vue'),
    meta: { isAuthRequired: false, instrumentSlug: 'basse' }
  },
  {
    path: '/rechercher-un-chanteur',
    name: 'app_search_singer',
    component: () => import('../views/Search/Index.vue'),
    meta: { isAuthRequired: false, instrumentSlug: 'chant' }
  },
  {
    path: '/rechercher-un-pianiste',
    name: 'app_search_pianist',
    component: () => import('../views/Search/Index.vue'),
    meta: { isAuthRequired: false, instrumentSlug: 'piano' }
  }
]
