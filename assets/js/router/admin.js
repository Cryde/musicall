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
  },
  {
    path: "/admin/gallery/pending/",
    name: "admin_gallery_pending",
    component: () => import("../views/admin/gallery/GalleryPending"),
    meta: {isAuthRequired: true}
  },
  {
    path: "/admin/publication/featured/",
    name: "admin_publication_featured",
    component: () => import("../views/admin/featured/Featured"),
    meta: {isAuthRequired: true}
  }
];