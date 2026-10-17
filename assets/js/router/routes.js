import { CHAT_TESTER_ONLY, RIDER_TESTER_ONLY } from '../constants/bandSpace.js'
import admin from './admin.js'
import course from './course.js'
import forum from './forum.js'
import { localizedRoutes } from './localizedRoutes.js'
import publication from './publication.js'
import search from './search.js'
import user from './user.js'

// The pages of forum.js and user.js with a version per locale. Their other pages, a member's own and the
// sign-in flow, have no prefix and keep the active locale.
const LOCALIZED_FORUM_AND_USER_PAGES = new Set([
  'app_forum_index',
  'forum_topic_list',
  'forum_topic_item',
  'app_user_public_profile',
  'app_user_musician_profile',
  'app_user_teacher_profile',
  'app_contact',
  'app_privacy',
  'app_terms',
  'app_account_deletion',
  'app_mentions_legales'
])

const isLocalizedPage = (record) => LOCALIZED_FORUM_AND_USER_PAGES.has(record.name)
const isUnprefixedPage = (record) => !isLocalizedPage(record)

export default [
  {
    path: '/',
    name: 'app_layout',
    component: () => import('../components/AppBaseLayout.vue'),
    // The layout matches `/` ahead of the home page, which sits one level down under the locale param, so
    // it hands over. The empty locale is explicit, or Back from an English page would inherit `en`.
    redirect: { name: 'app_home', params: { locale: '' } },
    children: [
      localizedRoutes([
        {
          name: 'app_home',
          path: '/',
          component: () => import('../views/Home/Home.vue')
        },
        {
          name: 'app_discover',
          path: '/decouvrir',
          component: () => import('../views/Discover/Index.vue')
        },
        // Public on purpose, and deliberately outside app_band_layout: that layout renders the band
        // sidebar and loads the visitor's spaces, neither of which a visitor without an account has.
        {
          name: 'app_band_space_presentation',
          path: '/band-space',
          component: () => import('../views/BandSpace/Presentation.vue')
        },
        ...publication,
        ...course,
        ...search,
        ...forum.filter(isLocalizedPage),
        // Or `/en/forums/my-topics` would be the forum named my-topics. That member page has no English
        // version, so it goes to its URL without prefix, like the other ones.
        { path: '/forums/my-topics', redirect: { name: 'app_forum_my_topics', params: {} } },
        ...user.filter(isLocalizedPage),
        {
          path: '/:pathMatch(.*)*',
          name: 'not_found',
          component: () => import('../views/NotFound.vue')
        }
      ]),
      {
        name: 'app_public_share',
        path: 'shares/:token',
        component: () => import('../views/PublicShare.vue')
      },
      ...forum.filter(isUnprefixedPage),
      ...user.filter(isUnprefixedPage),
      admin
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
        // The URL is French because that is what a member reads; the route name says chat, matching
        // the API it calls. Gated like the rider module, so releasing is one flip in constants.
        path: ':id/discussion',
        name: 'app_band_chat',
        component: () => import('../views/BandSpace/Chat.vue'),
        meta: { testerOnly: CHAT_TESTER_ONLY }
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
        meta: { testerOnly: RIDER_TESTER_ONLY }
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
    // for stage display: no MenuBand chrome, no footer, no padding.
    path: '/band/:bandSpaceId/setlists/:setlistId/live',
    name: 'app_band_setlist_live',
    component: () => import('../views/BandSpace/SetlistLive.vue'),
    meta: { isAuthRequired: true }
  }
]
