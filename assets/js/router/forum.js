export default [
  {
    path: '/forums',
    name: 'app_forum_index',
    component: () => import('../views/Forum/Index.vue'),
    meta: { isAuthRequired: false }
  },
  {
    path: '/forums/:slug',
    name: 'forum_topic_list',
    component: () => import('../views/Forum/Show.vue'),
    meta: { isAuthRequired: false }
  },
  {
    path: '/forums/topic/:slug/:page?',
    name: 'forum_topic_item',
    component: () => import('../views/Forum/Topic.vue'),
    meta: { isAuthRequired: false }
  }
]
