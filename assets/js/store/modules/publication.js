import apiPublication from '../../api/publication';

const state = {
  isLoading: false,
  error: null,
  publication: {},
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
  publication(state) {
    return state.publication;
  }
};

const mutations = {
  ['FETCHING'](state) {
    state.isLoading = true;
    state.error = null;
    state.publication = {};
  },
  ['FETCHING_SUCCESS'](state, payload) {
    state.isLoading = false;
    state.error = null;
    state.publication = payload.publication;
  },
  ['FETCHING_ERROR'](state, error) {
    state.isLoading = false;
    state.error = error;
    state.publication = {};
  },
};

const actions = {
  async getPublication({commit}, payload) {
    commit('FETCHING');
    try {
      const resp = await apiPublication.getPublication(payload.slug);
      commit('FETCHING_SUCCESS', {publication: resp});
    } catch (err) {
      commit('FETCHING_ERROR', err);
    }
  },
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}