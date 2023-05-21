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
import publicationEdit from './modules/publicationEdit';
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
import styles from './modules/styles';
import instruments from './modules/instruments';
import announceMusician from './modules/announceMusician';
import userMusicianAnnounces from './modules/userMusicianAnnounces';
import searchMusician from './modules/searchMusician';
import searchMusicianText from './modules/searchMusicianText';
import user from './modules/user';

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
    publicationEdit,
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
    styles,
    instruments,
    announceMusician,
    userMusicianAnnounces,
    searchMusician,
    searchMusicianText,
    user,
  },
  strict: debug,
})