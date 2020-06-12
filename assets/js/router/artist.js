export default [
  {
    path: "/artist/:slug",
    name: "artist_show",
    component: () => import("../views/artist/Show"),
    meta: {isAuthRequired: false}
  },
];