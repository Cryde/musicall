import Vue from 'vue';
import Vuex from 'vuex';
import security from './modules/security';
import registration from './modules/registration';
import video from './modules/video';
import adminPendingPublications from './modules/admin/pendingPublications';
import publication from './modules/publication';
import publications from './modules/publications';
import publicationCategory from './modules/publicationCategory';
import userGallery from './modules/userGallery';
import userGalleries from './modules/userGalleries';

Vue.use(Vuex);

const debug = process.env.NODE_ENV !== 'production';

export default new Vuex.Store({
  modules: {
    security,
    video,
    registration,
    publication,
    publications,
    publicationCategory,
    adminPendingPublications,
    userGallery,
    userGalleries
  },
  strict: debug,
})