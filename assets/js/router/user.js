export default [
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
    name: 'app_user_courses',
    path: '/user/courses',
    component: () => import('../views/User/Course/Index.vue'),
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
