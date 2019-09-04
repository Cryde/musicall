import apiSecurity from '../../api/security';

const state = {
  isLoading: false,
  error: null,
  isAuthenticated: false,
  user: {}
};

const getters = {
  isLoading(state) {
    return state.isLoading;
  },
  hasError(state) {
    return state.error !== null;
  },
  error(state) {
    return state.error;
  },
  isAuthenticated(state) {
    return state.isAuthenticated;
  },
  user(state) {
    return state.user;
  }
};

const mutations = {
  ['AUTHENTICATING'](state) {
    state.isLoading = true;
    state.error = null;
    state.isAuthenticated = false;
    state.user = {};
  },
  ['AUTHENTICATING_SUCCESS'](state, payload) {
    state.isLoading = false;
    state.error = null;
    state.isAuthenticated = true;
    state.user = payload.user;
  },
  ['AUTHENTICATING_ERROR'](state, error) {
    state.isLoading = false;
    state.error = error;
    state.isAuthenticated = false;
    state.user = {};
  },
  ['PROVIDING_DATA_REFRESH'](state, payload) {
    state.isLoading = false;
    state.error = null;
    state.isAuthenticated = payload.isAuthenticated;
    state.user = payload.user
  }
};

const actions = {
  async login({commit}, payload) {
    commit('AUTHENTICATING');
    try {
      const resp = await apiSecurity.login(payload.username, payload.password);
      commit('AUTHENTICATING_SUCCESS', {user: resp.data});
    } catch (err) {
      commit('AUTHENTICATING_ERROR', err);
    }
  },
  refresh({commit}, payload) {
    commit('PROVIDING_DATA_REFRESH', payload);
  }
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}