export default [
  {
    name: "user_publications",
    path: "/user/publications",
    component: () => import("../views/user/Publication/list/List"),
    meta: {isAuthRequired: true}
  },
  {
    name: "user_gallery",
    path: "/user/galleries",
    component: () => import("../views/user/Gallery/List"),
    meta: {isAuthRequired: true}
  },
  {
    path: "/user/publications/edit/:id",
    name: "user_publications_edit",
    component: () => import("../views/user/Publication/edit/Edit"),
    meta: {isAuthRequired: true}
  },
  {
    path: "/user/galleries/edit/:id",
    name: "user_gallery_edit",
    component: () => import("../views/user/Gallery/EditGallery"),
    meta: {isAuthRequired: true}
  },
];