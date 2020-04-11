
import apiAdminGallery from '../../../api/admin/gallery';

const IS_LOADING = 'IS_LOADING';
const UPDATE_GALLERIES = 'UPDATE_GALLERIES';
const UPDATE_ERRORS = 'UPDATE_ERRORS';

const state = {
  isLoading: false,
  error: null,
  galleries: [],
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
  galleries(state) {
    return state.galleries;
  }
};

const mutations = {
  [IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  },
  [UPDATE_GALLERIES](state, galleries) {
    state.galleries = galleries;
  },
  [UPDATE_ERRORS](state, errors) {
    state.errors = errors;
  }
};

const actions = {
  async loadPendingGalleries({commit}) {
    commit(UPDATE_ERRORS, null);
    commit(IS_LOADING, true);
    try {
      const galleries = await apiAdminGallery.getPendingGalleries();
      console.log(galleries);
      commit(UPDATE_GALLERIES, galleries);
      commit(IS_LOADING, false);
    } catch (err) {
      commit(UPDATE_ERRORS, err);
    }
  },
  async approveGallery({commit}, {id}) {
    commit(IS_LOADING, true);
    try {
      await apiAdminGallery.approveGallery(id);
      commit(IS_LOADING, false);
    } catch(e) {
      commit(UPDATE_ERRORS, e);
    }
  },
  async rejectGallery({commit}, {id}) {
    commit(IS_LOADING, true);
    try {
      await apiAdminGallery.rejectGallery(id);
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