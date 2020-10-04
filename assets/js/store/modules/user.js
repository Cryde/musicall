import userApi from "../../api/user";

const IS_LOADING = 'IS_LOADING';
const UPDATE_USER = 'UPDATE_USER';

const state = {
  isLoading: true,
  user: null,
};

const getters = {
  isLoading(state) {
    return state.isLoading;
  },
  user(state) {
    return state.user;
  }
};

const mutations = {
  [IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  },
  [UPDATE_USER](state, user) {
    state.user = user;
  }
};

const actions = {
  async load({commit}) {
    commit(IS_LOADING, true);
    const user = await userApi.me();
    commit(UPDATE_USER, user);
    commit(IS_LOADING, false);
  },
  async refresh({commit}) {
    const user = await userApi.me();
    commit(UPDATE_USER, user);
    commit(IS_LOADING, false);
  }
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}