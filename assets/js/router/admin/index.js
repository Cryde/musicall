import attributes from "./attributes";
import publications from "./publications";
import artistes from "./artistes";
import forum from "./forum";

export default [
  {
    path: "/admin/",
    name: "admin_dashboard",
    component: () => import("../../views/admin/Dashboard"),
    meta: {isAuthRequired: true}
  },
  ...attributes,
  ...artistes,
  ...publications,
  ...forum
];