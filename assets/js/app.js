import 'babel-polyfill';
import 'whatwg-fetch';
import Vue from "vue";
import App from "./App.vue";
import router from "./router";
import store from "./store";
import BootstrapVue from 'bootstrap-vue';
import VueMeta from 'vue-meta';

Vue.use(VueMeta, {
  // optional pluginOptions
  refreshOnceOnNavigation: true
});

Vue.use(BootstrapVue);

Vue.config.productionTip = false;

new Vue({
  router,
  store,
  render: h => h(App),
  metaInfo: () => ({
    title: 'MusicAll, le site de référence au service de la musique'
  })
}).$mount("#app");