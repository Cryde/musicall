import featuredApi from "../../api/publication/featured";

const UPDATE_FEATURED = 'UPDATE_FEATURED';
const IS_LOADING = 'IS_LOADING';

const state = {
  featured: [],
  isLoading: true,
};

const getters = {
  isLoading(state) {
    return state.isLoading;
  },
  featured1(state) {
    return state.featured.find(featured => featured.level === 1);
  },
  hasFeatured(state) {
    return state.featured.length > 0;
  }
};

const mutations = {
  [IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  },
  [UPDATE_FEATURED](state, list) {
    state.featured = list;
  }
};

const actions = {
  async loadFeatured({commit}) {
    commit(IS_LOADING, true);
    const featuredList = await featuredApi.getFeaturedList();
    commit(UPDATE_FEATURED, featuredList['member']);
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