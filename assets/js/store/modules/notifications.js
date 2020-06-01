import notificationsApi from "../../api/user/notifications";

const UPDATE_MESSAGE_COUNT = 'UPDATE_MESSAGE_COUNT';

const state = {
  count: {
    message: 0,
  }
};

const getters = {
  messageCount(state) {
    return state.count.message;
  }
};

const mutations = {
  [UPDATE_MESSAGE_COUNT](state, count) {
    state.count.message = count;
  }
};

const actions = {
  async loadNotifications({commit}) {
    const {messages} = await notificationsApi.getNotifications();
    commit(UPDATE_MESSAGE_COUNT, messages);
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