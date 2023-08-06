import musicianApi from "../../api/search/musician";

const UPDATE_SEARCH = 'UPDATE_SEARCH';
const UPDATE_IS_SEARCHING = 'UPDATE_IS_SEARCHING';
const UPDATE_IS_SUCCESS = 'UPDATE_IS_SUCCESS';
const RESET = 'RESET';
const UPDATE_RESULTS = 'UPDATE_RESULTS';
const UPDATE_ERRORS = 'UPDATE_ERRORS';

const state = {
  isSearching: false,
  isSuccess: false,
  search: '',
  results: [],
  errors: [],
};

const getters = {
  isSuccess(state) {
    return state.isSuccess;
  },
  isSearching(state) {
    return state.isSearching;
  },
  search(state) {
    return state.search;
  },
  results(state) {
    return state.results;
  },
  errors(state) {
    return state.errors;
  },
};

const mutations = {
  [UPDATE_IS_SUCCESS](state, isSuccess) {
    state.isSuccess = isSuccess;
  },
  [UPDATE_IS_SEARCHING](state, isSearching) {
    state.isSearching = isSearching;
  },
  [UPDATE_SEARCH](state, search) {
    state.search = search;
  },
  [UPDATE_ERRORS](state, errors) {
    state.errors = errors;
  },
  [UPDATE_RESULTS](state, results) {
    state.results = results;
  },
  [RESET](state) {
    state.isSending = false;
    state.isSuccess = false;
    state.search = '';
    state.results = [];
    state.errors = [];
  }
};

const actions = {
  async updateSearch({commit}, search) {
    commit(UPDATE_SEARCH, search);
  },
  reset({commit}) {
    commit(RESET);
  },
  async search({commit, state}) {
    commit(UPDATE_IS_SEARCHING, true);
    commit(UPDATE_IS_SUCCESS, false);
    commit(UPDATE_ERRORS, []);
    try {
      const results = await musicianApi.getResultsFromText({
        search: state.search
      });

      commit(UPDATE_RESULTS, results['hydra:member']);
      commit(UPDATE_IS_SUCCESS, true);
    } catch (e) {
      if (e.response.data.hasOwnProperty('violations')) {
        commit(UPDATE_ERRORS, e.response.data.violations.map(violation => violation.title));
      } else {
        commit(UPDATE_ERRORS, ['Il n\'y a pas de résulat répondant à votre recherche. Pourriez vous reformuler votre recherche ?'])
      }
      commit(UPDATE_IS_SUCCESS, false);
      commit(UPDATE_RESULTS, []);
    }

    commit(UPDATE_IS_SEARCHING, false);
  }
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}