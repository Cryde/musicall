export default [
  {
    path: "/admin/",
    name: "admin_dashboard",
    component: () => import("../views/admin/Dashboard"),
    meta: {isAuthRequired: true}
  },
  {
    path: "/admin/publications/pending/",
    name: "admin_publications_pending",
    component: () => import("../views/admin/publications/PublicationPending"),
    meta: {isAuthRequired: true}
  }
];