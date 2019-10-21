import registerApi from '../../api/registration';

const UPDATE_IS_LOADING = 'UPDATE_IS_LOADING';
const UPDATE_IS_SUCCESS = 'UPDATE_IS_SUCCESS';

const state = {
  isLoading: false,
  isSuccess: false,
  errors: ''
};

const getters = {
  isLoading(state) {
    return state.isLoading;
  },
  hasError(state) {
    return state.errors.length > 0;
  },
  isSuccess(state) {
    return state.isSuccess;
  }
};

const mutations = {
  [UPDATE_IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  },
  [UPDATE_IS_SUCCESS](state, isSuccess) {
    state.isSuccess = isSuccess;
  }
};

const actions = {
  async register({commit}, {username, email, password}) {
    commit(UPDATE_IS_SUCCESS, false);
    commit(UPDATE_IS_LOADING, true);
    try {
      const resp = await registerApi.register({username, password, email});
      commit(UPDATE_IS_SUCCESS, true);

      console.log(resp);
    } catch (e) {

      console.log('ERROR');
      console.log(e);
    }

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