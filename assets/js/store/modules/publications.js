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
  total(state) {
    return state.total;
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
  [UPDATE_META](state, total) {
    state.total = total;
  },
};

const actions = {
  async getPublications({commit}, {page}) {
    commit(IS_LOADING, true);
    commit(UPDATE_ERROR, null);
    try {
      const data = await apiPublications.getPublications({page});
      commit(UPDATE_PUBLICATIONS, data['member']);
      commit(UPDATE_META, data['totalItems']);
    } catch (err) {
      commit(UPDATE_ERROR, err);
    }
    commit(IS_LOADING, false);
  },
  async getPublicationsByCategory({commit}, {slug, page}) {
    commit(IS_LOADING, true);
    commit(UPDATE_ERROR, null);
    try {
      const data = await apiPublications.getPublicationsByCategory({slug, page});
      commit(UPDATE_PUBLICATIONS, data['member']);
      commit(UPDATE_META, data['totalItems']);
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