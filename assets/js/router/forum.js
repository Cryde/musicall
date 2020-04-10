export default [
  {
    path: "/forums/",
    name: "forum_index",
    component: () => import("../views/forum/Forum"),
    meta: {isAuthRequired: false}
  },
];