import apiPublications from '../../api/publications';

const state = {
  isLoading: false,
  error: null,
  publications: [],
  numberOfPages: 0,
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
  },
  numberOfPages(state) {
    return state.numberOfPages;
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
    console.log(payload.data.publications);
    state.publications = payload.data.publications;
    state.numberOfPages = payload.data.meta.numberOfPages;
  },
  ['FETCHING_ERROR'](state, error) {
    state.isLoading = false;
    state.error = error;
    state.publication = {};
  },
};

const actions = {
  async getPublications({commit}, {offset}) {
    commit('FETCHING');
    try {
      const resp = await apiPublications.getPublications({offset});
      commit('FETCHING_SUCCESS', {data: resp.data});
    } catch (err) {
      commit('FETCHING_ERROR', err);
    }
  },
  async getPublicationsByCategory({commit}, {slug, offset}) {
    commit('FETCHING');
    try {
      const resp = await apiPublications.getPublicationsByCategory({slug, offset});
      commit('FETCHING_SUCCESS', {data: resp.data});
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