export default [
  {
    path: "/cours/",
    name: "course_index",
    component: () => import("../views/course/Course"),
    meta: {isAuthRequired: false}
  },
];