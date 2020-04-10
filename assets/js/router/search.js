export default [
  {
    path: "/search/",
    name: "search_index",
    component: () => import("../views/search/Search"),
    meta: {isAuthRequired: false}
  },
];