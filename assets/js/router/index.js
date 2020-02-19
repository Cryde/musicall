import Vue from "vue";
import Router from "vue-router";

Vue.use(Router);

export default new Router({
  routes: [
    {
      path: "/",
      name: 'home',
      component: () => import("../views/Home"),
      meta: {isAuthRequired: false}
    },
    {
      path: "/todo",
      name: 'todo',
      component: () => import("../views/Todo"),
      meta: {isAuthRequired: false}
    },
    {
      name: "publication",
      path: "/publications",
      component: () => import("../components/publication/list/PublicationList"),
      meta: {isAuthRequired: false}
    },
    {
      name: "publications_by_category",
      path: "/publications/category/:slug",
      component: () => import("../components/publication/list/PublicationList"),
      meta: {isAuthRequired: false}
    },
    {
      name: "publication_show",
      path: "/publications/:slug",
      component: () => import("../components/publication/show/Publication"),
      meta: {isAuthRequired: false}
    },
    {
      name: "login",
      path: "/login",
      component: () => import("../views/Login"),
      meta: {isAuthRequired: false}
    },
    {
      name: "user_registration",
      path: "/registration",
      component: () => import("../views/user/Registration"),
      meta: {isAuthRequired: false}
    },
    {
      name: "user_publications",
      path: "/user/publications",
      component: () => import("../components/publication/user/list/List"),
      meta: {isAuthRequired: true}
    },
    {
      name: "user_settings",
      path: "/user/settings",
      component: () => import("../views/user/Settings"),
      meta: {isAuthRequired: true}
    },
    {
      path: "/user/publications/edit/:id",
      name: "user_publications_edit",
      component: () => import("../components/publication/user/edit/Edit"),
      meta: {isAuthRequired: true}
    },
    {
      path: "/admin/publications/pending/",
      name: "admin_publications_pending",
      component: () => import("../views/admin/PublicationPending"),
      meta: {isAuthRequired: true}
    }
  ]
});