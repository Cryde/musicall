import publications from "./publications";
import forum from "./forum";

export default [
  {
    path: "/admin/",
    name: "admin_dashboard",
    component: () => import("../../views/admin/Dashboard.vue"),
    meta: {isAuthRequired: true}
  },
  ...publications,
  ...forum
];