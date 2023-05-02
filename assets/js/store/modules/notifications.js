import notificationsApi from "../../api/user/notifications";

const UPDATE_MESSAGE_COUNT = 'UPDATE_MESSAGE_COUNT';
const UPDATE_ADMIN_PENDING_GALLERIES_COUNT = 'UPDATE_ADMIN_PENDING_GALLERIES_COUNT';
const UPDATE_ADMIN_PENDING_PUBLICATIONS_COUNT = 'UPDATE_ADMIN_PENDING_PUBLICATIONS_COUNT';

const state = {
  count: {
    message: 0,
    admin: {
      galleries: 0,
      publications: 0,
    }
  }
};

const getters = {
  messageCount(state) {
    return state.count.message;
  },
  pendingGalleriesCount(state) {
    return state.count.admin.galleries;
  },
  pendingPublicationsCount(state) {
    return state.count.admin.publications;
  }
};

const mutations = {
  [UPDATE_MESSAGE_COUNT](state, count) {
    state.count.message = count;
  },
  [UPDATE_ADMIN_PENDING_GALLERIES_COUNT](state, count) {
    state.count.admin.galleries = count;
  },
  [UPDATE_ADMIN_PENDING_PUBLICATIONS_COUNT](state, count) {
    state.count.admin.publications = count;
  }
};

const actions = {
  async loadNotifications({commit}) {
    const notifications = await notificationsApi.getNotifications();
    commit(UPDATE_MESSAGE_COUNT, notifications.unread_messages);

    if (notifications.hasOwnProperty('pending_galleries') && notifications.hasOwnProperty('pending_publications')) {
      commit(UPDATE_ADMIN_PENDING_GALLERIES_COUNT, notifications.pending_galleries);
      commit(UPDATE_ADMIN_PENDING_PUBLICATIONS_COUNT, notifications.pending_publications);
    }
  },
  decrementMessageCount({commit, state}) {
    if (state.count.message) {
      commit(UPDATE_MESSAGE_COUNT, state.count.message - 1);
    }
  }
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}