import Vue from 'vue';
import Vuex from 'vuex';
import security from './modules/security';
import publication from './modules/publication';
import publications from './modules/publications';

Vue.use(Vuex);

const debug = process.env.NODE_ENV !== 'production';

export default new Vuex.Store({
  modules: {
    security,
    publication,
    publications,
  },
  strict: debug,
})