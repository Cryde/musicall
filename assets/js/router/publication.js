export default [
  {
    name: "publication",
    path: "/publications",
    component: () => import("../components/publication/list/List"),
    meta: {isAuthRequired: false}
  },
  {
    name: "publications_by_category",
    path: "/publications/category/:slug",
    component: () => import("../components/publication/list/List"),
    meta: {isAuthRequired: false}
  },
  {
    name: "publication_show",
    path: "/publications/:slug",
    component: () => import("../components/publication/show/Publication"),
    meta: {isAuthRequired: false}
  },
];