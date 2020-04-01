import Vue from "vue";
import Router from "vue-router";
import admin from './admin';

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
      name: "gallery_show",
      path: "/gallery/:slug",
      component: () => import("../views/gallery/show/Gallery"),
      meta: {isAuthRequired: false}
    },
    {
      name: "gallery_list",
      path: "/photos/",
      component: () => import("../views/gallery/list/Galeries"),
      meta: {isAuthRequired: false}
    },
    {
      name: "login",
      path: "/login",
      component: () => import("../views/Login"),
      meta: {isAuthRequired: false}
    },
    {
      name: "user_request_lost_password",
      path: "/request/lost-password",
      component: () => import("../views/user/RequestResetPassword"),
      meta: {isAuthRequired: false}
    },
    {
      name: "user_lost_password",
      path: "/lost-password/:token",
      component: () => import("../views/user/ResetPassword"),
      meta: {isAuthRequired: false}
    },
    {
      name: "user_registration",
      path: "/registration",
      component: () => import("../views/user/Registration"),
      meta: {isAuthRequired: false}
    },
    {
      name: "user_registration_success",
      path: "/registration/success",
      component: () => import("../views/user/RegistrationConfirmSuccess"),
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
      path: "/user/galleries/edit/:id",
      name: "user_gallery_edit",
      component: () => import("../components/publication/user/edit/EditGallery"),
      meta: {isAuthRequired: true}
    },
    ...admin
  ]
});