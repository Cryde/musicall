import publicationsApi from "../../../api/admin/publications";
import featuredApi from "../../../api/admin/featured";

const UPDATE_RESULTS_SEARCH = 'UPDATE_RESULTS_SEARCH';
const ADD_FEATURED_ITEM = 'ADD_FEATURED_ITEM';
const UPDATE_FEATURED = 'UPDATE_FEATURED';
const IS_LOADING = 'IS_LOADING';
const EDIT_FEATURED_ITEM = 'EDIT_FEATURED_ITEM';
const REMOVE_FEATURED_ITEM = 'REMOVE_FEATURED_ITEM';

const state = {
  results: [],
  featured: [],
  isLoading: true,
};

const getters = {
  results(state) {
    return state.results;
  },
  isLoading(state) {
    return state.isLoading;
  },
  featured1(state) {
    return state.featured.find(featured => featured.level === 1);
  }
};

const mutations = {
  [UPDATE_RESULTS_SEARCH](state, results) {
    state.results = results;
  },
  [ADD_FEATURED_ITEM](state, featured) {
    state.featured.push(featured);
  },
  [UPDATE_FEATURED](state, featuredList) {
    state.featured = featuredList;
  },
  [IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  },
  [EDIT_FEATURED_ITEM](state, featured) {
    state.featured = state.featured.map((item) => {
      if (item.id === featured.id) {
        return featured;
      }

      return item;
    });
  },
  [REMOVE_FEATURED_ITEM](state, id) {
    state.featured = state.featured.filter(item => item.id !== id);
  }
};

const actions = {
  async searchPublication({commit}, query) {
    const results = await publicationsApi.searchPublication(query);
    commit(UPDATE_RESULTS_SEARCH, results);
  },
  async loadFeatured({commit}) {
    commit(IS_LOADING, true);
    const featuredList = await featuredApi.getFeaturedList();
    commit(UPDATE_FEATURED, featuredList['hydra:member']);
    commit(IS_LOADING, false);
  },
  async refreshFeatured({commit}) {
    const featuredList = await featuredApi.getFeaturedList();
    commit(UPDATE_FEATURED, featuredList['hydra:member']);
  },
  async save({commit}, {level, publicationId, title, description}) {
    const featured = await featuredApi.saveFeatured({level, publicationId, title, description});
    commit(ADD_FEATURED_ITEM, featured);
  },
  async edit({commit}, {featuredId, title, description}) {
    const featured = await featuredApi.editFeatured({featuredId, title, description});
    commit(EDIT_FEATURED_ITEM, featured);
  },
  async remove({commit}, featuredId) {
    commit(REMOVE_FEATURED_ITEM, featuredId);
    await featuredApi.removeFeatured(featuredId);
  },
  async changeOption({commit}, {featuredId, option, value}) {
    const featured = await featuredApi.changeOption({featuredId, option, value});
    commit(EDIT_FEATURED_ITEM, featured);
  },
  async publish({commit}, featuredId) {
    const featured = await featuredApi.publish(featuredId);
    commit(EDIT_FEATURED_ITEM, featured);
  },
  async unpublish({commit}, featuredId) {
    const featured = await featuredApi.unpublish(featuredId);
    commit(EDIT_FEATURED_ITEM, featured);
  },
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}