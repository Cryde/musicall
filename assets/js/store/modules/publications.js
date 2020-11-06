import apiPublications from '../../api/publications';

const IS_LOADING = 'IS_LOADING';
const UPDATE_ERROR = 'UPDATE_ERROR';
const UPDATE_PUBLICATIONS = 'UPDATE_PUBLICATIONS';
const UPDATE_META = 'UPDATE_META';

const state = {
  isLoading: false,
  error: null,
  publications: [],
  numberOfPages: 0,
  total: 0,
  limitByPage: 0,
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
  },
  total(state) {
    return state.total;
  },
  limitByPage(state) {
    return state.limitByPage;
  }
};

const mutations = {
  [IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  },
  [UPDATE_ERROR](state, error) {
    state.error = error;
  },
  [UPDATE_PUBLICATIONS](state, publications) {
    state.publications = publications;
  },
  [UPDATE_META](state, meta) {
    state.numberOfPages = meta.numberOfPages;
    state.total = meta.total;
    state.limitByPage = meta.limit_by_page;
  },
};

const actions = {
  async getPublications({commit}, {offset}) {
    commit(IS_LOADING, true);
    commit(UPDATE_ERROR, null);
    try {
      const {publications, meta} = await apiPublications.getPublications({offset});
      commit(UPDATE_PUBLICATIONS, publications);
      commit(UPDATE_META, meta);
    } catch (err) {
      commit(UPDATE_ERROR, err);
    }
    commit(IS_LOADING, false);
  },
  async getPublicationsByCategory({commit}, {slug, offset}) {
    commit(IS_LOADING, true);
    commit(UPDATE_ERROR, null);
    try {
      const {publications, meta} = await apiPublications.getPublicationsByCategory({slug, offset});
      commit(UPDATE_PUBLICATIONS, publications);
      commit(UPDATE_META, meta);
    } catch (err) {
      commit(UPDATE_ERROR, err);
    }
    commit(IS_LOADING, false);
  },
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}