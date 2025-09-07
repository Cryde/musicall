import {createWebHistory, createRouter} from 'vue-router'

import publication from "./publication";
import course from './course.js';
import search from './search.js';
import forum from './forum.js';

const routes = [
  {
    name: 'app_home',
    path: '/',
    component: () => import("../views/Home/Home.vue")
  },
  ...publication,
  ...course,
  ...search,
  ...forum,
]

export default createRouter({
  history: createWebHistory(),
  routes,
})
