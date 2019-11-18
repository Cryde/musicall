import apiAdminPublication from '../../../api/admin/publications';

const IS_LOADING = 'IS_LOADING';
const UPDATE_PUBLICATIONS = 'UPDATE_PUBLICATIONS';
const UPDATE_ERRORS = 'UPDATE_ERRORS';

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
    return state.errors !== null;
  },
  error(state) {
    return state.errors;
  },
  publications(state) {
    return state.publications;
  }
};

const mutations = {
  [IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  },
  [UPDATE_PUBLICATIONS](state, publications) {
    state.publications = publications;
  },
  [UPDATE_ERRORS](state, errors) {
    state.errors = errors;
  }
};

const actions = {
  async getPublications({commit}) {
    commit(UPDATE_ERRORS, null);
    commit(IS_LOADING, true);
    try {
      const publications = await apiAdminPublication.getPendingPublications();
      commit(UPDATE_PUBLICATIONS, publications);
      commit(IS_LOADING, false);
    } catch (err) {
      commit(UPDATE_ERRORS, err);
    }
  },
  async approvePublication({commit}, {id}) {
      commit(IS_LOADING, true);
      try {
        await apiAdminPublication.approvePublication(id);
        commit(IS_LOADING, false);
      } catch(e) {
        commit(UPDATE_ERRORS, e);
      }
  },
  async rejectPublication({commit}, {id}) {
    commit(IS_LOADING, true);
    try {
      await apiAdminPublication.rejectPublication(id);
      commit(IS_LOADING, false);
    } catch(e) {
      commit(UPDATE_ERRORS, e);
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