export default [
  {
    path: "/forums/",
    name: "forum_index",
    component: () => import("../views/forum/Forum"),
    meta: {isAuthRequired: false}
  },
  {
    path: "/forums/:id",
    name: "forum_topic_list",
    component: () => import("../views/forum/Topic/TopicList"),
    meta: {isAuthRequired: false}
  },
  {
    path: "/forums/topic/:id/:page?",
    name: "forum_topic_item",
    component: () => import("../views/forum/Topic/Topic"),
    meta: {isAuthRequired: false}
  },
];