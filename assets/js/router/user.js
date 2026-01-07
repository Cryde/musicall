export default [
  {
    name: 'app_contact',
    path: '/contact',
    component: () => import('../views/Contact/Index.vue'),
    meta: { isAuthRequired: false }
  },
  {
    name: 'app_privacy',
    path: '/privacy',
    component: () => import('../views/Legal/Privacy.vue')
  },
  {
    name: 'app_terms',
    path: '/terms',
    component: () => import('../views/Legal/Terms.vue')
  },
  {
    name: 'app_mentions_legales',
    path: '/mentions-legales',
    component: () => import('../views/Legal/MentionsLegales.vue')
  },
  {
    name: 'app_login',
    path: '/login',
    component: () => import('../views/User/Login.vue'),
    meta: { isAuthRequired: false }
  },
  {
    name: 'app_register',
    path: '/register',
    component: () => import('../views/User/Register.vue'),
    meta: { isAuthRequired: false }
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
      }
    ]
  }
]
