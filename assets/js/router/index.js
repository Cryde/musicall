import { createRouter, createWebHistory } from 'vue-router'
import course from './course.js'
import forum from './forum.js'
import publication from './publication'
import search from './search.js'
import user from './user.js'

const routes = [
  {
    name: 'app_home',
    path: '/',
    component: () => import('../views/Home/Home.vue')
  },
  ...publication,
  ...course,
  ...search,
  ...forum,
  ...user
]

export default createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    // always scroll to top
    return { top: 0 }
  }
})
