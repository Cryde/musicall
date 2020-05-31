export default [
  {
    name: "message_list",
    path: "/messages",
    component: () => import("../views/message/Index"),
    meta: {isAuthRequired: true}
  },
];