export default [
  {
    path: "/admin/attribute/style/",
    name: "admin_attribute_style_list",
    component: () => import("../../views/admin/attribute/style/List"),
    meta: {isAuthRequired: true}
  },
  {
    path: "/admin/attribute/instrument/",
    name: "admin_attribute_instrument_list",
    component: () => import("../../views/admin/attribute/instrument/List"),
    meta: {isAuthRequired: true}
  },
];