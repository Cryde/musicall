export default [
  {
    path: "/cours/",
    name: "course_index",
    component: () => import("../views/course/Course"),
    meta: {isAuthRequired: false}
  },
  {
    name: "course_show",
    path: "/cours/:slug",
    component: () => import("../views/course/show/Course"),
    meta: {isAuthRequired: false}
  },
];