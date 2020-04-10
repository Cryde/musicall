export default [
  {
    name: "user_publications",
    path: "/user/publications",
    component: () => import("../components/publication/user/list/List"),
    meta: {isAuthRequired: true}
  },
  {
    path: "/user/publications/edit/:id",
    name: "user_publications_edit",
    component: () => import("../components/publication/user/edit/Edit"),
    meta: {isAuthRequired: true}
  },
  {
    path: "/user/galleries/edit/:id",
    name: "user_gallery_edit",
    component: () => import("../components/publication/user/edit/EditGallery"),
    meta: {isAuthRequired: true}
  },
];