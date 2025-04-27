import announceApi from '../../api/musician/announce';

const IS_LOADING = 'IS_LOADING';
const UPDATE_ANNOUNCES = 'UPDATE_ANNOUNCES';

const state = {
  isLoading: true,
  announces: []
};

const getters = {
  isLoading(state) {
    return state.isLoading;
  },
  lastAnnounces(state) {
    return state.announces;
  }
};

const mutations = {
  [IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  },
  [UPDATE_ANNOUNCES](state, announces) {
    state.announces = announces;
  }
};

const actions = {
  async loadLastAnnounces({commit}) {
    commit(IS_LOADING, true);
    const announces = await announceApi.getLastAnnounces();
    commit(UPDATE_ANNOUNCES, announces['member']);
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