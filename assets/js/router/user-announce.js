export default [
  {
    name: "user_musician_announce",
    path: "/user/announces",
    component: () => import("../views/user/Announce/List.vue"),
    meta: {isAuthRequired: true}
  },
];