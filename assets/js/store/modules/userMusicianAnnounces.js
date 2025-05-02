import announceApi from "../../api/musician/announce";

const UPDATE_IS_LOADING = 'UPDATE_IS_LOADING';
const UPDATE_ANNOUNCES = 'UPDATE_ANNOUNCES';

const state = {
  isLoading: true,
  announces: [],
};

const getters = {
  isLoading(state) {
    return state.isLoading;
  },
  announces(state) {
    return state.announces;
  }
};

const mutations = {
  [UPDATE_IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  },
  [UPDATE_ANNOUNCES](state, announces) {
    state.announces = announces;
  }
};

const actions = {
  async load({commit}) {
    commit(UPDATE_IS_LOADING, true);
    const announces = await announceApi.getByCurrentUser();
    commit(UPDATE_ANNOUNCES, announces['member']);
    commit(UPDATE_IS_LOADING, false);
  },
  async refresh({commit}) {
    const announces = await announceApi.getByCurrentUser();
    commit(UPDATE_ANNOUNCES, announces['member']);
  },
  async delete({commit}, id) {
    commit(UPDATE_IS_LOADING, true);
    await announceApi.delete(id);
    const announces = await announceApi.getByCurrentUser();
    commit(UPDATE_ANNOUNCES, announces['member']);
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