import instrumentApi from '../../api/attribute/instrument';

const IS_LOADING = 'IS_LOADING';
const UPDATE_INSTRUMENTS = 'UPDATE_INSTRUMENTS';

const state = {
  isLoading: true,
  instruments: []
};

const getters = {
  isLoadingInstruments(state) {
    return state.isLoading;
  },
  instruments(state) {
    return state.instruments;
  }
};

const mutations = {
  [IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  },
  [UPDATE_INSTRUMENTS](state, instruments) {
    state.instruments = instruments;
  }
};

const actions = {
  async loadInstruments({commit}) {
    commit(IS_LOADING, true);
    const instruments = await instrumentApi.listInstrument();
    commit(UPDATE_INSTRUMENTS, instruments);
    commit(IS_LOADING, false);
  }
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}