export default [
  {
    path: "/admin/forum/category",
    name: "admin_forum_category_list",
    component: () => import("../../views/admin/forum/List"),
    meta: {isAuthRequired: true}
  },
  {
    path: "/admin/forum/category/:id/forum",
    name: "admin_forum_list_by_category",
    component: () => import("../../views/admin/forum/ForumListByCategory"),
    meta: {isAuthRequired: true}
  },
];