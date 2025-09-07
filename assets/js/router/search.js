export default [
  {
    path: "/rechercher-un-musicien",
    name: "app_search_musician",
    component: () => import("../views/Search/Index.vue"),
    meta: {isAuthRequired: false}
  },
];