import apiSecurity from '../../api/security';
import jwt from 'jwt-decode';

const UPDATE_LOCALE_STORAGE = 'UPDATE_LOCALE_STORAGE';
const UPDATE_IS_LOADING = 'UPDATE_IS_LOADING';
const UPDATE_ERRORS = 'UPDATE_ERRORS';
const AUTHENTICATING = 'AUTHENTICATING';
const UPDATE_AUTH_STATE = 'UPDATE_AUTH_STATE';
const UPDATE_AUTH_REQUESTED = 'UPDATE_AUTH_REQUESTED';
const AUTHENTICATING_ERROR = 'AUTHENTICATING_ERROR';
const REMOVE_DATA_LOCAL_STORAGE = 'REMOVE_DATA_LOCAL_STORAGE';

const state = {
  isLoading: false,
  error: null,
  token: localStorage.getItem('token') || null,
  refreshToken: localStorage.getItem('refresh_token') || null,
  authRequested: false,
  isAuthenticated: false,
  user: {}
};

const getters = {
  isLoading(state) {
    return state.isLoading;
  },
  hasError(state) {
    return state.error !== null;
  },
  error(state) {
    return state.error;
  },
  isAuthenticated(state) {
    return state.isAuthenticated;
  },
  user(state) {
    return state.user;
  },
  isRoleAdmin(state) {
    return state.user.roles.includes('ROLE_ADMIN');
  }
};

const mutations = {
  [AUTHENTICATING](state) {
    state.isAuthenticated = false;
    state.user = {};
  },
  [UPDATE_AUTH_STATE](state, payload) {
    state.isAuthenticated = true;

    state.token = payload.token;
    state.refreshToken = payload.refresh_token;

    const jwtDecoded = jwt(state.token);
    state.user = {
      username: jwtDecoded.username,
      roles: jwtDecoded.roles,
    };
  },
  [UPDATE_LOCALE_STORAGE](state, payload) {
    localStorage.setItem('token', payload.token);
    localStorage.setItem('refresh_token', payload.refresh_token);
  },
  [REMOVE_DATA_LOCAL_STORAGE]() {
    localStorage.removeItem('token',);
    localStorage.removeItem('refresh_token');
  },
  [UPDATE_IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  },
  [UPDATE_ERRORS](state, errors) {
    state.error = errors;
  },
  [AUTHENTICATING_ERROR](state) {
    state.isAuthenticated = false;
    state.user = {};
  },
  [UPDATE_AUTH_REQUESTED](state, authRequested) {
    state.authRequested = authRequested;
  }
};

const actions = {
  async login({commit}, payload) {
    commit(UPDATE_IS_LOADING, true);
    commit(UPDATE_ERRORS, null);
    commit(AUTHENTICATING);
    try {
      const resp = await apiSecurity.login(payload.username, payload.password);
      commit(UPDATE_AUTH_STATE, {token: resp.token, refresh_token: resp.refresh_token});
      commit(UPDATE_LOCALE_STORAGE, {token: resp.token, refresh_token: resp.refresh_token});
      commit(UPDATE_IS_LOADING, false);
    } catch (err) {
      commit(AUTHENTICATING_ERROR);
      commit(UPDATE_ERRORS, err);
      commit(UPDATE_IS_LOADING, false);
    }
  },
  async logout({commit}) {
    commit(REMOVE_DATA_LOCAL_STORAGE);
  },
  async getAuthToken({commit, state}, {displayLoading = false}) {

    if (!state.token) {
      return null;
    }

    displayLoading && commit(UPDATE_IS_LOADING, true);
    // if the current store token expires soon
    if (isTokenExpired(state.token)) {
      // if not already requesting a token
      if (!state.authRequested) {
        commit(UPDATE_AUTH_REQUESTED, true);
        const resp = await apiSecurity.refreshToken(state.refreshToken);
        commit(UPDATE_AUTH_REQUESTED, false);
        displayLoading && commit(UPDATE_IS_LOADING, false);
        commit(UPDATE_AUTH_STATE, {token: resp.token, refresh_token: resp.refresh_token});
        commit(UPDATE_LOCALE_STORAGE, {token: resp.token, refresh_token: resp.refresh_token});

        return resp.token;
      }
    }

    commit(UPDATE_AUTH_STATE, {token: state.token, refresh_token: state.refreshToken});
    commit(UPDATE_LOCALE_STORAGE, {token: state.token, refresh_token: state.refreshToken});
    displayLoading && commit(UPDATE_IS_LOADING, false);

    return state.token;
  }
};

function isTokenExpired(token) {
  return jwt(state.token).exp - 240 <= (Date.now() / 1000).toFixed(0);
}

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}
