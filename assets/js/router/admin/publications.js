export default [
  {
    path: "/admin/publications/pending/",
    name: "admin_publications_pending",
    component: () => import("../../views/admin/publications/PublicationPending"),
    meta: {isAuthRequired: true}
  },
  {
    path: "/admin/publication/featured/",
    name: "admin_publication_featured",
    component: () => import("../../views/admin/featured/Featured"),
    meta: {isAuthRequired: true}
  },
];