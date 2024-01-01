import Vue from "vue";
import App from "./App.vue";
import router from "./router";
import store from "./store";
import VueMeta from 'vue-meta';
import relativeDateFilter from "./filters/relative-date-filter";
import prettyDateFilter from "./filters/pretty-date-filter";
import VueGtag from "vue-gtag";
import * as Sentry from "@sentry/vue";
import GmapVue from 'gmap-vue';
import {
  ConfigProgrammatic,
  Input,
  Navbar,
  Loading,
  Autocomplete,
  Dropdown,
  Icon,
  Button,
  Tag,
  Skeleton,
  Tooltip,
  Pagination,
  Message,
  Image,
  Modal,
  Field,
  Steps,
  Table
} from 'buefy'
import PerfectScrollbar from 'vue2-perfect-scrollbar'
import VueProgressBar from 'vue-progressbar'

Vue.use(Input);
Vue.use(Navbar);
Vue.use(Loading);
Vue.use(Autocomplete);
Vue.use(Dropdown);
Vue.use(Icon);
Vue.use(Button);
Vue.use(Tag);
Vue.use(Skeleton);
Vue.use(Tooltip);
Vue.use(Pagination);
Vue.use(Message);
Vue.use(Image);
Vue.use(Modal);
Vue.use(Field);
Vue.use(Steps);
Vue.use(Table);

ConfigProgrammatic.setOptions({
  defaultIconPack: 'fas',
  defaultProgrammaticPromise: true
})

if (import.meta.env.VITE_SENTRY_DSN) {
  Sentry.init({
    Vue,
    dsn: import.meta.env.VITE_SENTRY_DSN,
    integrations: [
      new Sentry.BrowserTracing({
        routingInstrumentation: Sentry.vueRouterInstrumentation(router),
      }),
      new Sentry.Replay(),
    ],

    // Set tracesSampleRate to 1.0 to capture 100%
    // of transactions for performance monitoring.
    // We recommend adjusting this value in production
    tracesSampleRate: 1.0,

    // Set `tracePropagationTargets` to control for which URLs distributed tracing should be enabled
    tracePropagationTargets: ["127.0.0.1", "www.musicall.com", /^\//],

    // Capture Replay for 10% of all sessions,
    // plus for 100% of sessions with an error
    replaysSessionSampleRate: 0.1,
    replaysOnErrorSampleRate: 1.0,
  });
}

Vue.use(VueProgressBar, {});

Vue.use(PerfectScrollbar);

Vue.use(VueMeta, {
  // optional pluginOptions
  refreshOnceOnNavigation: true
});

Vue.use(VueGtag, {
  config: { id: "G-1CK1G9W6FX" }
}, router);

Vue.use(GmapVue, {
  load: {
    key: import.meta.env.VITE_GOOGLE_API_KEY_FRONT,
    libraries: 'places',
  },
  installComponents: true
})


Vue.filter('relativeDate', relativeDateFilter);
Vue.filter('prettyDate', prettyDateFilter);

Vue.config.productionTip = false;

new Vue({
  router,
  store,
  render: h => h(App),
  metaInfo: () => ({
    title: 'MusicAll, le site de référence au service de la musique',
    meta: [
      {vmid: 'description', name: 'description', 'content': 'Site communautaire au service de la musique. Articles et cours sur la musique, annuaire de musiciens, forums, ...'},
      { property: "og:type", content: 'website' },
      { property: "og:locale", content: 'fr_FR' },
      { property: "og:image", content: window.location.origin + '/build/images/facebook-logo.jpg' },
      { property: "og:site_name", content: 'MusicAll' },
    ]
  })
}).$mount("#app");

// this  is a special export for images we reference in twig
import.meta.glob([
  '../images/**',
]);