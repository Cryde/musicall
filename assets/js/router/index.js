import Vue from "vue";
import Router from "vue-router";

Vue.use(Router);

export default new Router({
  routes: [
    {
      path: "/",
      name: 'home',
      component: () => import("../views/Home"),
    },
    {
      name: "publication",
      path: "/publications",
      component: () => import("../components/publication/list/PublicationList")
    },
    {
      name: "publication_show",
      path: "/publications/:slug",
      component: () => import("../components/publication/show/Publication")
    },
    {
      name: "login",
      path: "/login",
      component: () => import("../views/Login")
    },
    {
      name: "user_publications",
      path: "/user/publications",
      component: () => import("../components/publication/user/list/List"),
    },
    {
      path: "/user/publications/edit/:id",
      name: "user_publications_edit",
      component: () => import("../components/publication/user/edit/Edit")
    }
  ]
});