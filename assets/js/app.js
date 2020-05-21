import 'babel-polyfill';
import Vue from "vue";
import App from "./App.vue";
import router from "./router";
import store from "./store";
import BootstrapVue from 'bootstrap-vue';
import VueMeta from 'vue-meta';
import VueLazyload from 'vue-lazyload'
import relativeDateFilter from "./filters/relative-date-filter";
import prettyDateFilter from "./filters/pretty-date-filter";
import VueGtag from "vue-gtag";
import './directives/click-outside';

Vue.use(VueMeta, {
  // optional pluginOptions
  refreshOnceOnNavigation: true
});

Vue.use(BootstrapVue);
Vue.use(VueLazyload);

Vue.use(VueGtag, {
  config: { id: "UA-4980079-1" }
}, router);


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
      {vmid: 'description', name: 'description', 'content': 'Site communautaire au service de la musique. Articles et cours sur la musique, annuaire de musiciens, forums, ...'}
    ]
  })
}).$mount("#app");