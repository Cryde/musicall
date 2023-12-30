export default [
  {
    name: "message_list",
    path: "/messages",
    component: () => import("../views/message/Index.vue"),
    meta: {isAuthRequired: true}
  },
];