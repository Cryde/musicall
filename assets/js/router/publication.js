export default [
  {
    name: "publication",
    path: "/publications",
    component: () => import("../views/publication/list/Grid.vue"),
    meta: {isAuthRequired: false}
  },
  {
    name: "publications_by_category",
    path: "/publications/category/:slug",
    component: () => import("../views/publication/list/Grid.vue"),
    meta: {isAuthRequired: false}
  },
  {
    name: "publication_show",
    path: "/publications/:slug",
    component: () => import("../views/publication/show/Publication.vue"),
    meta: {isAuthRequired: false}
  },
];