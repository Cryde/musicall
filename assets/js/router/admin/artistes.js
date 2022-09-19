export default [
  {
    path: "/admin/artists",
    name: "admin_artists_list",
    component: () => import("../../views/admin/artist/List"),
    meta: {isAuthRequired: true}
  },
  {
    path: "/admin/artists/edit/:id",
    name: "admin_artists_edit",
    component: () => import("../../views/admin/artist/Edit"),
    meta: {isAuthRequired: true}
  },
];