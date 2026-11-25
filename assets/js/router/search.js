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
    component: () => import('../views/Search/TeacherSearch.vue'),
    meta: { isAuthRequired: false }
  },
  {
    path: '/rechercher-un-ingenieur-du-son',
    name: 'app_search_sound_engineer',
    component: () => import('../views/Search/SpecialitySearch.vue'),
    props: { kind: 'sound_engineering' },
    meta: { isAuthRequired: false }
  },
  {
    path: '/rechercher-un-photographe',
    name: 'app_search_photographer',
    component: () => import('../views/Search/SpecialitySearch.vue'),
    props: { kind: 'photography' },
    meta: { isAuthRequired: false }
  },
  {
    path: '/rechercher-un-videaste',
    name: 'app_search_videographer',
    component: () => import('../views/Search/SpecialitySearch.vue'),
    props: { kind: 'video' },
    meta: { isAuthRequired: false }
  },
  {
    path: '/rechercher-un-graphiste',
    name: 'app_search_graphic_designer',
    component: () => import('../views/Search/SpecialitySearch.vue'),
    props: { kind: 'graphic_design' },
    meta: { isAuthRequired: false }
  },
  {
    path: '/rechercher-un-webmaster',
    name: 'app_search_webmaster',
    component: () => import('../views/Search/SpecialitySearch.vue'),
    props: { kind: 'web' },
    meta: { isAuthRequired: false }
  },
  {
    path: '/rechercher-un-producteur',
    name: 'app_search_producer',
    component: () => import('../views/Search/SpecialitySearch.vue'),
    props: { kind: 'production' },
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
