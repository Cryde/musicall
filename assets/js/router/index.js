import { createRouter, createWebHistory } from 'vue-router'
import course from './course.js'
import forum from './forum.js'
import publication from './publication'
import search from './search.js'
import user from './user.js'

const routes = [
  {
    path: '/',
    name: 'app_layout',
    component: () => import('../components/AppBaseLayout.vue'),
    children: [
      {
        name: 'app_home',
        path: '',
        component: () => import('../views/Home/Home.vue')
      },
      {
        name: 'app_discover',
        path: 'decouvrir',
        component: () => import('../views/Discover/Index.vue')
      },
      ...publication,
      ...course,
      ...search,
      ...forum,
      ...user
    ]
  },
  {
    path: '/band',
    name: 'app_band_layout',
    component: () => import('../components/AppBandLayout.vue'),
    meta: { isAuthRequired: true },
    children: [
      {
        path: '',
        name: 'app_band_index',
        component: () => import('../views/BandSpace/NoSpace.vue')
      },
      {
        path: ':id',
        name: 'app_band_dashboard',
        component: () => import('../views/BandSpace/Index.vue')
      },
      {
        path: ':id/agenda',
        name: 'app_band_agenda',
        component: () => import('../views/BandSpace/Empty.vue')
      },
      {
        path: ':id/notes',
        name: 'app_band_notes',
        component: () => import('../views/BandSpace/Empty.vue')
      },
      {
        path: ':id/social',
        name: 'app_band_social',
        component: () => import('../views/BandSpace/Empty.vue')
      },
      {
        path: ':id/files',
        name: 'app_band_files',
        component: () => import('../views/BandSpace/Empty.vue')
      },
      {
        path: ':id/parametres',
        name: 'app_band_parameters',
        component: () => import('../views/BandSpace/Empty.vue')
      }
    ]
  }
]

export default createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    // always scroll to top
    return { top: 0 }
  }
})
