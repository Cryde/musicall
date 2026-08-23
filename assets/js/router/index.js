import { createRouter, createWebHistory } from 'vue-router'
import { RIDER_SUPER_ADMIN_ONLY } from '../constants/bandSpace.js'
import admin from './admin.js'
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
      {
        name: 'app_public_share',
        path: 'shares/:token',
        component: () => import('../views/PublicShare.vue')
      },
      // Public on purpose, and deliberately outside app_band_layout: that layout renders the band
      // sidebar and loads the visitor's spaces, neither of which a visitor without an account has.
      {
        name: 'app_band_space_presentation',
        path: 'band-space',
        component: () => import('../views/BandSpace/Presentation.vue')
      },
      ...publication,
      ...course,
      ...search,
      ...forum,
      ...user,
      admin,
      {
        path: ':pathMatch(.*)*',
        name: 'not_found',
        component: () => import('../views/NotFound.vue')
      }
    ]
  },
  {
    path: '/band',
    name: 'app_band_layout',
    component: () => import('../components/AppBandLayout.vue'),
    meta: { isAuthRequired: true, unauthenticatedRedirect: 'app_band_space_presentation' },
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
        component: () => import('../views/BandSpace/Agenda.vue')
      },
      {
        path: ':id/notes',
        name: 'app_band_notes',
        component: () => import('../views/BandSpace/Notes.vue')
      },
      {
        path: ':id/files',
        name: 'app_band_files',
        component: () => import('../views/BandSpace/Files.vue')
      },
      {
        path: ':id/taches',
        name: 'app_band_tasks',
        component: () => import('../views/BandSpace/Tasks.vue')
      },
      {
        path: ':id/finances',
        name: 'app_band_finance',
        component: () => import('../views/BandSpace/Finance.vue')
      },
      {
        path: ':id/setlists',
        name: 'app_band_setlist',
        component: () => import('../views/BandSpace/Setlist.vue')
      },
      {
        // One route for the whole module: the rider on screen is a query param, like
        // Setlist's ?setlist=. Hiding the sidebar entry alone would leave the URL walkable,
        // hence the guard flag, whose value lives in constants/bandSpace.js so releasing
        // the module stays a single flip.
        path: ':id/tech-riders',
        name: 'app_band_rider',
        component: () => import('../views/BandSpace/TechRider.vue'),
        meta: { superAdminOnly: RIDER_SUPER_ADMIN_ONLY }
      },
      {
        path: ':id/parametres',
        name: 'app_band_parameters',
        component: () => import('../views/BandSpace/Settings.vue')
      },
      {
        path: 'invitation/:token',
        name: 'app_band_invitation',
        component: () => import('../views/BandSpace/InvitationResponse.vue'),
        // Overrides the layout: somebody following an invitation wants to sign in, not to be sold
        // the module. Selling it to them would also drop the token from the URL.
        meta: { unauthenticatedRedirect: 'app_login' }
      }
    ]
  },
  {
    // Live mode runs outside AppBandLayout so the full viewport is usable
    // for stage display — no MenuBand chrome, no footer, no padding.
    path: '/band/:bandSpaceId/setlists/:setlistId/live',
    name: 'app_band_setlist_live',
    component: () => import('../views/BandSpace/SetlistLive.vue'),
    meta: { isAuthRequired: true }
  }
]

export default createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(_to, _from, savedPosition) {
    // Restore scroll position on back/forward navigation
    if (savedPosition) {
      return savedPosition
    }
    // Scroll to top for new navigations
    return { top: 0 }
  }
})
