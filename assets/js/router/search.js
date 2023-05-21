export default [
  {
    path: "/rechercher-un-musicien-legacy/",
    name: "search_index_legacy",
    component: () => import("../views/search/Musician/Search"),
    meta: {isAuthRequired: false}
  },
  {
    path: "/rechercher-un-musicien/",
    name: "search_index",
    component: () => import("../views/search/Musician/SearchForm.vue"),
    meta: {isAuthRequired: false}
  },
];