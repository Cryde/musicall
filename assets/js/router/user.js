export default [
    {
        name: "app_login",
        path: "/login",
        component: () => import("../views/User/Login.vue"),
        meta: {isAuthRequired: false}
    },
]
