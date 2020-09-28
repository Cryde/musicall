export default [
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
    path: "/user/settings",
    component: () => import("../views/user/Settings"),
    meta: {isAuthRequired: true},
    children: [
      {
        path: '',
        name: "user_settings",
        component: () => import("../views/user/Settings/General"),
        meta: {isAuthRequired: true},
      },
      {
        path: 'password',
        name: "user_settings_password",
        component: () => import("../views/user/Settings/SettingsPasswordChange"),
        meta: {isAuthRequired: true},
      }
    ]
  },
];