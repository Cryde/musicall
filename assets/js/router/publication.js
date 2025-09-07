export default [
  {
    name: "app_publications",
    path: "/publications",
    component: () => import("../views/Publication/Index.vue"),
    meta: {isAuthRequired: false}
  },
  {
    name: "app_publications_by_category",
    path: "/publications/category/:slug",
    component: () => import("../views/Publication/Index.vue"),
    meta: {isAuthRequired: false}
  },
  {
    name: "app_publication_show",
    path: "/publications/:slug",
    component: () => import("../views/Publication/Index.vue"),
    meta: {isAuthRequired: false}
  },
];