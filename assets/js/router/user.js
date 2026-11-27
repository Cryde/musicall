import { localizedPage } from '../i18n/localizedPage.js'

export default [
  {
    name: 'app_user_public_profile',
    path: '/u/:username',
    component: () => import('../views/User/Profile/PublicProfile.vue'),
    meta: { isAuthRequired: false }
  },
  // Folded into the profile (#1317): its « Médias » view, in the same locale. The server answers old links with
  // a 301; this covers a link already in the app.
  {
    name: 'app_user_musician_profile',
    path: '/u/:username/musician',
    redirect: (to) => ({
      name: 'app_user_public_profile',
      params: to.params,
      query: { ...to.query, voir: 'medias' },
      hash: to.hash
    })
  },
  {
    name: 'app_user_teacher_profile',
    path: '/u/:username/teacher',
    component: () => import('../views/User/Profile/TeacherProfile.vue'),
    meta: { isAuthRequired: false }
  },
  {
    name: 'app_user_sound_engineer',
    path: '/u/:username/sound-engineer',
    component: () => import('../views/User/Profile/SoundEngineerProfile.vue'),
    meta: { isAuthRequired: false }
  },
  {
    name: 'app_user_photographer',
    path: '/u/:username/photographer',
    component: () => import('../views/User/Profile/VisualServiceProfile.vue'),
    props: { kind: 'photography' },
    meta: { isAuthRequired: false }
  },
  {
    name: 'app_user_videographer',
    path: '/u/:username/videographer',
    component: () => import('../views/User/Profile/VisualServiceProfile.vue'),
    props: { kind: 'video' },
    meta: { isAuthRequired: false }
  },
  {
    name: 'app_user_graphic_designer',
    path: '/u/:username/graphic-designer',
    component: () => import('../views/User/Profile/VisualServiceProfile.vue'),
    props: { kind: 'graphic_design' },
    meta: { isAuthRequired: false }
  },
  {
    name: 'app_user_webmaster',
    path: '/u/:username/webmaster',
    component: () => import('../views/User/Profile/VisualServiceProfile.vue'),
    props: { kind: 'web' },
    meta: { isAuthRequired: false }
  },
  {
    name: 'app_contact',
    path: '/contact',
    component: () => import('../views/Contact/Index.vue'),
    meta: { isAuthRequired: false }
  },
  {
    name: 'app_privacy',
    path: '/privacy',
    component: localizedPage({
      fr: () => import('../views/Legal/Privacy.vue'),
      en: () => import('../views/Legal/en/Privacy.vue')
    })
  },
  {
    name: 'app_terms',
    path: '/terms',
    component: localizedPage({
      fr: () => import('../views/Legal/Terms.vue'),
      en: () => import('../views/Legal/en/Terms.vue')
    })
  },
  {
    // Public on purpose: Google Play's Data safety form links here as the account deletion URL (#1130).
    name: 'app_account_deletion',
    path: '/supprimer-mon-compte',
    component: localizedPage({
      fr: () => import('../views/Legal/AccountDeletion.vue'),
      en: () => import('../views/Legal/en/AccountDeletion.vue')
    })
  },
  {
    name: 'app_mentions_legales',
    path: '/mentions-legales',
    component: localizedPage({
      fr: () => import('../views/Legal/MentionsLegales.vue'),
      en: () => import('../views/Legal/en/MentionsLegales.vue')
    })
  },
  {
    name: 'app_login',
    path: '/login',
    component: () => import('../views/User/Login.vue'),
    meta: { isGuestOnly: true }
  },
  {
    name: 'app_register',
    path: '/register',
    component: () => import('../views/User/Register.vue'),
    meta: { isGuestOnly: true }
  },
  {
    name: 'app_verify_email',
    path: '/verify-email',
    component: () => import('../views/User/VerifyEmail.vue'),
    meta: { isGuestOnly: true }
  },
  {
    name: 'app_forgot_password',
    path: '/forgot-password',
    component: () => import('../views/User/ForgotPassword.vue'),
    meta: { isAuthRequired: false }
  },
  {
    name: 'app_reset_password',
    path: '/lost-password/:token',
    component: () => import('../views/User/ResetPassword.vue'),
    meta: { isAuthRequired: false }
  },
  {
    name: 'app_messages',
    path: '/messages/:threadId?',
    component: () => import('../views/Message/Index.vue'),
    meta: { isAuthRequired: true }
  },
  {
    name: 'app_user_services',
    path: '/user/services',
    component: () => import('../views/User/Service/Index.vue'),
    meta: { isAuthRequired: true }
  },
  {
    name: 'app_user_announces',
    path: '/user/announces',
    component: () => import('../views/User/Announce/Index.vue'),
    meta: { isAuthRequired: true }
  },
  {
    name: 'app_user_publications',
    path: '/user/publications',
    component: () => import('../views/User/Publication/Index.vue'),
    meta: { isAuthRequired: true }
  },
  {
    name: 'app_user_publication_edit',
    path: '/user/publications/:id/edit',
    component: () => import('../views/User/Publication/Edit.vue'),
    meta: { isAuthRequired: true }
  },
  {
    name: 'app_user_publication_preview',
    path: '/user/publications/:id/preview',
    component: () => import('../views/User/Publication/Preview.vue'),
    meta: { isAuthRequired: true }
  },
  {
    name: 'app_user_courses',
    path: '/user/courses',
    component: () => import('../views/User/Course/Index.vue'),
    meta: { isAuthRequired: true }
  },
  {
    name: 'app_user_galleries',
    path: '/user/galleries',
    component: () => import('../views/User/Gallery/Index.vue'),
    meta: { isAuthRequired: true }
  },
  {
    name: 'app_user_gallery_edit',
    path: '/user/galleries/:id/edit',
    component: () => import('../views/User/Gallery/Edit.vue'),
    meta: { isAuthRequired: true }
  },
  {
    name: 'app_user_gallery_preview',
    path: '/user/galleries/:id/preview',
    component: () => import('../views/User/Gallery/Preview.vue'),
    meta: { isAuthRequired: true }
  },
  {
    name: 'app_notifications_index',
    path: '/notifications',
    component: () => import('../views/Notification/Index.vue'),
    meta: { isAuthRequired: true }
  },
  {
    path: '/user/settings',
    component: () => import('../views/User/Settings/SettingsLayout.vue'),
    meta: { isAuthRequired: true },
    children: [
      {
        name: 'app_user_settings',
        path: '',
        component: () => import('../views/User/Settings/SettingsGeneral.vue')
      },
      {
        name: 'app_user_settings_password',
        path: 'password',
        component: () => import('../views/User/Settings/SettingsPassword.vue')
      },
      {
        name: 'app_user_settings_notifications',
        path: 'notifications',
        component: () => import('../views/User/Settings/SettingsNotifications.vue')
      },
      {
        name: 'app_user_settings_privacy',
        path: 'privacy',
        component: () => import('../views/User/Settings/SettingsPrivacy.vue')
      },
      {
        name: 'app_user_settings_profile',
        path: 'profile',
        component: () => import('../views/User/Settings/SettingsProfile.vue')
      },
      // The former profile sub-tabs (#1317): their content is edited on the profile now, teaching in the services (#1406).
      {
        path: 'profile/:tab(privacy|musician)',
        redirect: { name: 'app_user_settings_profile' }
      },
      {
        path: ':tab(services|profile/teacher)',
        redirect: { name: 'app_user_services' }
      }
    ]
  }
]
