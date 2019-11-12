import registerApi from '../../api/registration';
import {retrieveErrors} from "../../helper/errors";

const UPDATE_IS_LOADING = 'UPDATE_IS_LOADING';
const UPDATE_IS_SUCCESS = 'UPDATE_IS_SUCCESS';
const UPDATE_ERRORS = 'UPDATE_ERRORS';

const state = {
  isLoading: false,
  isSuccess: false,
  errors: []
};

const getters = {
  errors(state) {
    return state.errors;
  },
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
  },
  [UPDATE_ERRORS](state, errors) {
    state.errors = errors;
  }
};

const actions = {
  async register({commit}, {username, email, password}) {
    commit(UPDATE_IS_SUCCESS, false);
    commit(UPDATE_IS_LOADING, true);

    try {
      const resp = await registerApi.register({username, password, email});

      if (resp.data.hasOwnProperty('errors')) {
        commit(UPDATE_ERRORS, retrieveErrors(resp.data.errors));

      } else {
        commit(UPDATE_IS_SUCCESS, true);
      }
    } catch (e) {
      commit(UPDATE_ERRORS, ['Erreur inconnue']);
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