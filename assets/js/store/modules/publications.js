import apiPublications from '../../api/publications';

const state = {
  isLoading: false,
  error: null,
  publications: [],
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
  publications(state) {
    return state.publications;
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
    state.publications = payload.publications;
  },
  ['FETCHING_ERROR'](state, error) {
    state.isLoading = false;
    state.error = error;
    state.publication = {};
  },
};

const actions = {
  async getPublications({commit}) {
    commit('FETCHING');
    try {
      const resp = await apiPublications.getPublications();
      commit('FETCHING_SUCCESS', {publications: resp.data});
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