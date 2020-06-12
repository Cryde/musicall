import Vue from 'vue';
import Vuex from 'vuex';
import security from './modules/security';
import registration from './modules/registration';
import video from './modules/video';
import adminPendingPublications from './modules/admin/pendingPublications';
import adminPendingGalleries from './modules/admin/pendingGalleries';
import adminFeatured from './modules/admin/adminFeatured';
import publication from './modules/publication';
import publications from './modules/publications';
import publicationCategory from './modules/publicationCategory';
import userGallery from './modules/userGallery';
import userGalleries from './modules/userGalleries';
import gallery from './modules/gallery';
import galleries from './modules/galleries';
import featured from './modules/featured';
import thread from './modules/thread';
import messages from './modules/messages';
import notifications from './modules/notifications';
import artist from './modules/artist';

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
    adminPendingGalleries,
    userGallery,
    userGalleries,
    gallery,
    galleries,
    adminFeatured,
    featured,
    thread,
    messages,
    notifications,
    artist,
  },
  strict: debug,
})