export default [
  {
    name: "gallery_show",
    path: "/gallery/:slug",
    component: () => import("../views/gallery/show/Gallery.vue"),
    meta: {isAuthRequired: false}
  },
  {
    name: "gallery_list",
    path: "/photos/",
    component: () => import("../views/gallery/list/Galeries.vue"),
    meta: {isAuthRequired: false}
  },
];
