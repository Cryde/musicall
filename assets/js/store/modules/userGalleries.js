import galleryApi from '../../api/publication/gallery';

const IS_LOADING = 'IS_LOADING';
const UPDATE_GALLERIES = 'UPDATE_GALLERIES';

const state = {
  isLoading: false,
  galleries: [],
};

const getters = {
  isLoading(state) {
    return state.isLoading;
  },
  galleries(state) {
    return state.galleries;
  },
};

const mutations = {
  [IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  },
  [UPDATE_GALLERIES](state, galleries) {
    state.galleries = galleries;
  },
};

const actions = {
  async load({commit}) {
    commit(IS_LOADING, true);
    const galleries = await galleryApi.getGalleries();
    commit(UPDATE_GALLERIES, galleries);
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