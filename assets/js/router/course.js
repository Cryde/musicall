export default [
  {
    path: "/cours/",
    name: "course_index",
    component: () => import("../views/course/Course"),
    meta: {isAuthRequired: false}
  },
  {
    path: "/cours/category/:slug",
    name: "course_by_category",
    component: () => import("../views/course/list/List"),
    meta: {isAuthRequired: false}
  },
  {
    name: "course_show",
    path: "/cours/:slug",
    component: () => import("../views/course/show/Course"),
    meta: {isAuthRequired: false}
  },
];