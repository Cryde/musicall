import apiPublication from '../../api/publication';

const IS_LOADING = 'IS_LOADING';
const UPDATE_PUBLICATION = 'UPDATE_PUBLICATION';
const UPDATE_ERROR = 'UPDATE_ERROR';
const RESET = 'RESET';

const state = {
  isLoading: true,
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
  [IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  },
  [UPDATE_PUBLICATION](state, publication) {
    state.publication = publication;
  },
  [UPDATE_ERROR](state, error) {
    state.error = error;
  },
  [RESET](state) {
    state.error = null;
    state.publication = {};
    state.isLoading = true;
  }
};

const actions = {
  async getPublication({commit}, payload) {
    commit(IS_LOADING, true);
    commit(UPDATE_PUBLICATION, {});
    commit(UPDATE_ERROR, null);
    try {
      const publication = await apiPublication.getPublication(payload.slug);
      commit(UPDATE_PUBLICATION, publication);
    } catch (err) {
      if (err.response.status === 404) {
        commit(UPDATE_ERROR, 'La publication n\'existe pas.');
      }
      commit(UPDATE_ERROR, 'Une erreur inconnue est survenue');
    }
    commit(IS_LOADING, false);
  },
  reset({commit}) {
    commit(RESET);
  }
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}