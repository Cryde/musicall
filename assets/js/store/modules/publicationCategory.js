import apiCategories from '../../api/publicationCategory';

const UPDATE_CATEGORIES = 'UPDATE_CATEGORIES';
const UPDATE_IS_LOADING = 'UPDATE_IS_LOADING';

const TYPE_PUBLICATION = 1;
const TYPE_COURSE = 2;

const state = {
  isLoading: false,
  categories: [],
};

const getters = {
  isLoading(state) {
    return state.isLoading;
  },
  publicationCategories(state) {
    return state.categories.filter(item => item.type === TYPE_PUBLICATION);
  },
  courseCategories(state) {
    return state.categories.filter(item => item.type === TYPE_COURSE);
  }
};

const mutations = {
  [UPDATE_CATEGORIES](state, categories) {
    state.categories = categories;
  },
  [UPDATE_IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  }
};

const actions = {
  async getCategories({commit}) {
    commit(UPDATE_IS_LOADING, true);
    const categories = await apiCategories.getCategories();
    commit(UPDATE_CATEGORIES, categories);
    commit(UPDATE_IS_LOADING, false);
  }
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}