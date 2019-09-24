import apiPublication from '../../api/publication';

const state = {
  isLoading: false,
  error: null,
  video: {},
  isLoadingAdd: false,
  newVideo: {}
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
  video(state) {
    return state.video;
  },
  isLoadingAdd(state) {
    return state.isLoadingAdd;
  },
  isExistingVideo(state) {
    return state.video.existing_video;
  },
  newVideo(state) {
    return state.newVideo;
  }
};

const mutations = {
  ['FETCHING'](state) {
    state.isLoading = true;
    state.error = null;
    state.video = {};
  },
  ['FETCHING_SUCCESS'](state, payload) {
    state.isLoading = false;
    state.error = null;
    state.video = payload.video;
  },
  ['FETCHING_ERROR'](state, error) {
    state.isLoading = false;
    state.error = error;
    state.video = {};
  },
  ['ADDING'](state) {
    state.isLoadingAdd = true;
    state.error = null;
    state.newVideo = {};
  },
  ['ADDING_SUCCESS'](state, payload) {
    state.isLoadingAdd = false;
    state.error = null;
    state.newVideo = payload;
  },
  ['RESET_ALL'](state) {
    state = {
      isLoading: false,
      error: null,
      video: {},
      isLoadingAdd: false,
      newVideo: {}
    };
  }
};

const actions = {
  resetState({commit}) {
    commit('RESET_ALL');
  },
  async getPreviewVideo({commit}, payload) {
    commit('FETCHING');
    try {
      const resp = await apiPublication.getPreviewVideo(payload.videoUrl);
      commit('FETCHING_SUCCESS', {video: resp.data});
    } catch (err) {
      commit('FETCHING_ERROR', err);
    }
  },
  async addVideo({commit}, {title, description, videoUrl, imageUrl}) {
    commit('ADDING');
    try {
      const resp = await apiPublication.addVideo({title, description, videoUrl, imageUrl});
      commit('ADDING_SUCCESS', {video: resp.data});
    } catch (err) {
      commit('FETCHING_ERROR', err);
    }
  }
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}