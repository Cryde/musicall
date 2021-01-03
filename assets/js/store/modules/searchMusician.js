import musicianApi from "../../api/search/musician";
import {TYPES_ANNOUNCE_BAND, TYPES_ANNOUNCE_BAND_LABEL, TYPES_ANNOUNCE_MUSICIAN} from '../../constants/types'

const UPDATE_SELECTED_INSTRUMENTS = 'UPDATE_SELECTED_INSTRUMENTS';
const UPDATE_SELECTED_STYLES = 'UPDATE_SELECTED_STYLES';
const UPDATE_SELECTED_LOCATION = 'UPDATE_SELECTED_LOCATION';
const UPDATE_SELECTED_ANNOUNCE_TYPE = 'UPDATE_SELECTED_ANNOUNCE_TYPE';
const UPDATE_IS_SEARCHING = 'UPDATE_IS_SEARCHING';
const UPDATE_IS_SUCCESS = 'UPDATE_IS_SUCCESS';
const RESET = 'RESET';
const UPDATE_RESULTS = 'UPDATE_RESULTS';

const state = {
  isSearching: false,
  isSuccess: false,
  type: null,
  instrument: null,
  styles: [],
  location: {
    longitude: null,
    latitude: null,
  },
  results: [],
};

const getters = {
  isSuccess(state) {
    return state.isSuccess;
  },
  isSearching(state) {
    return state.isSearching;
  },
  selectedInstrument(state) {
    return state.instrument;
  },
  selectedStyles(state) {
    return state.styles;
  },
  selectedLocationName(state) {
    return state.location.name;
  },
  results(state) {
    return state.results;
  },
  selectedTypeName(state) {
    if (!state.type) {
      return null;
    }

    return state.type === 'band' ? 'band' : 'musician';
  }
};

const mutations = {
  [UPDATE_IS_SUCCESS](state, isSuccess) {
    state.isSuccess = isSuccess;
  },
  [UPDATE_IS_SEARCHING](state, isSearching) {
    state.isSearching = isSearching;
  },
  [UPDATE_SELECTED_ANNOUNCE_TYPE](state, type) {
    state.type = type;
  },
  [UPDATE_SELECTED_INSTRUMENTS](state, instrument) {
    state.instrument = instrument;
  },
  [UPDATE_SELECTED_STYLES](state, styles) {
    state.styles = styles;
  },
  [UPDATE_SELECTED_LOCATION](state, {long, lat}) {
    state.location.longitude = long;
    state.location.latitude = lat;
  },
  [UPDATE_RESULTS](state, results) {
    state.results = results;
  },
  [RESET](state) {
    state.isSending = false;
    state.isSuccess = false;
    state.type = '';
    state.instrument = '';
    state.styles = [];
    state.results = [];
    state.location.longitude = null;
    state.location.latitude = null;
  }
};

const actions = {
  async updateSearchType({commit}, type) {
    commit(UPDATE_SELECTED_ANNOUNCE_TYPE, type);
  },
  async updateSelectedInstruments({commit}, instrument) {
    commit(UPDATE_SELECTED_INSTRUMENTS, instrument);
  },
  async updateSelectedStyles({commit}, styles) {
    commit(UPDATE_SELECTED_STYLES, styles);
  },
  async updateLocation({commit}, {long, lat}) {
    commit(UPDATE_SELECTED_LOCATION, {long, lat});
  },
  reset({commit}) {
    commit(RESET);
  },
  async search({commit, state}) {
    commit(UPDATE_IS_SEARCHING, true);
    commit(UPDATE_IS_SUCCESS, false);
    try {
      const results = await musicianApi.getResults({
        // we need to reverse the search because
        // when the announce search a band it mean he/she is a musician
        // so we search a musician
        type: state.type === TYPES_ANNOUNCE_BAND_LABEL ? TYPES_ANNOUNCE_MUSICIAN : TYPES_ANNOUNCE_BAND,
        instrument: state.instrument,
        styles: state.styles ? state.styles.map(item => item.id) : [],
        latitude: state.location.latitude,
        longitude: state.location.longitude,
      });

      commit(UPDATE_RESULTS, results.data);
      commit(UPDATE_IS_SUCCESS, true);
    } catch (e) {
      // todo do something
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