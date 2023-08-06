export default [
  {
    path: "/rechercher-un-musicien/",
    name: "search_index",
    component: () => import("../views/search/Musician/SearchForm.vue"),
    meta: {isAuthRequired: false}
  },
];