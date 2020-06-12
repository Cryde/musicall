import artistApi from "../../api/artist/artist";

const IS_LOADING = 'IS_LOADING';
const UPDATE_ARTIST = 'UPDATE_ARTIST';

const state = {
  isLoading: true,
  artist: null,
};

const getters = {
  isLoading(state) {
    return state.isLoading;
  },
  artist(state) {
    return state.artist;
  }
};

const mutations = {
  [IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  },
  [UPDATE_ARTIST](state, artist) {
    state.artist = artist;
  }
};

const actions = {
  async loadArtist({commit}, {slug}) {
    commit(IS_LOADING, true);
    const artist = await artistApi.getArtist({slug});
    // todo : load related content
    commit(UPDATE_ARTIST, artist);
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