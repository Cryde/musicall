export default [
  {
    path: '/cours',
    name: 'app_course',
    component: () => import('../views/Course/Index.vue'),
    meta: { isAuthRequired: false }
  },
  {
    path: '/cours/category/:slug',
    name: 'app_course_by_category',
    component: () => import('../views/Course/Index.vue'),
    meta: { isAuthRequired: false }
  },
  {
    name: 'app_course_show',
    path: '/cours/:slug',
    component: () => import('../views/Course/Index.vue'),
    meta: { isAuthRequired: false }
  }
]
