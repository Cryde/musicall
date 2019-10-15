import apiSecurity from '../../api/security';
import jwt from 'jwt-decode';

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
  }
};

const mutations = {
  ['AUTHENTICATING'](state) {
    state.isLoading = true;
    state.error = null;
    state.isAuthenticated = false;
    state.user = {};
  },
  ['UPDATE_IS_LOADING'](state, isLoading) {
    state.isLoading = isLoading;
  },
  ['AUTHENTICATING_SUCCESS'](state, payload) {
    state.isLoading = false;
    state.error = null;
    state.isAuthenticated = true;

    state.token = payload.token;
    state.refreshToken = payload.refresh_token;

    localStorage.setItem('token', payload.token);
    localStorage.setItem('refresh_token', payload.refresh_token);
  },
  ['UPDATE_AUTH_STATE'](state, payload) {
    state.token = payload.token;
    state.refreshToken = payload.refresh_token;

    state.isAuthenticated = true;

    const jwtDecoded = jwt(state.token);
    state.user = {
      username: jwtDecoded.username,
      roles: jwtDecoded.roles,
    };

    localStorage.setItem('token', payload.token);
    localStorage.setItem('refresh_token', payload.refresh_token);
  },
  ['AUTHENTICATING_ERROR'](state, error) {
    state.isLoading = false;
    state.error = error;
    state.isAuthenticated = false;
    state.user = {};
  },
  ['PROVIDING_DATA_REFRESH'](state, payload) {
    state.isLoading = false;
    state.error = null;
    state.isAuthenticated = payload.isAuthenticated;
    state.user = payload.user
  },
  ['UPDATE_AUTH_REQUESTED'](state, authRequested) {
    state.authRequested = authRequested;
  }
};

const actions = {
  async login({commit}, payload) {
    commit('AUTHENTICATING');
    try {
      const resp = await apiSecurity.login(payload.username, payload.password);
      commit('AUTHENTICATING_SUCCESS', {token: resp.token, refresh_token: resp.refresh_token});
      console.log(jwt(resp.token));
    } catch (err) {
      commit('AUTHENTICATING_ERROR', err);
    }
  },
  refresh({commit}) {
    commit('PROVIDING_DATA_REFRESH', payload);
  },
  async getAuthToken({commit, state}, displayLoading) {

    if (!state.token) {
      return null;
    }

    displayLoading && commit('UPDATE_IS_LOADING', true);
    // if the current store token expires soon
    if (isTokenExpired(state.token)) {
      // if not already requesting a token
      if (!state.authRequested) {
        commit('UPDATE_AUTH_REQUESTED', true);
        const resp = await apiSecurity.refreshToken(state.refreshToken);
        commit('UPDATE_AUTH_REQUESTED', false);
        displayLoading && commit('UPDATE_IS_LOADING', false);
        commit('UPDATE_AUTH_STATE', {token: resp.token, refresh_token: resp.refresh_token});

        return resp.token;
      }
    }

    commit('UPDATE_AUTH_STATE', {token: state.token, refresh_token: state.refreshToken});
    displayLoading && commit('UPDATE_IS_LOADING', false);

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
