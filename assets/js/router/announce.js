export default [
  {
    path: "/annonce/musicien",
    name: "announce_musician_add",
    component: () => import("../views/announce/musician/Add"),
    meta: {isAuthRequired: false}
  },
];