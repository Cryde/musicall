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
  }
]
