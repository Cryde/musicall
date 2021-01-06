import announceApi from "../../api/musician/announce";
import {TYPES_ANNOUNCE_BAND, TYPES_ANNOUNCE_BAND_LABEL, TYPES_ANNOUNCE_MUSICIAN} from "../../constants/types";

const UPDATE_SELECTED_INSTRUMENTS = 'UPDATE_SELECTED_INSTRUMENTS';
const UPDATE_SELECTED_STYLES = 'UPDATE_SELECTED_STYLES';
const UPDATE_SELECTED_LOCATION = 'UPDATE_SELECTED_LOCATION';
const UPDATE_SELECTED_ANNOUNCE_TYPE = 'UPDATE_SELECTED_ANNOUNCE_TYPE';
const UPDATE_IS_SENDING = 'UPDATE_IS_SENDING';
const UPDATE_IS_SUCCESS = 'UPDATE_IS_SUCCESS';
const UPDATE_NOTE = 'UPDATE_NOTE';
const RESET = 'RESET';

const state = {
  isSending: false,
  isSuccess: false,
  type: '',
  note: '',
  instrument: '',
  styles: [],
  location: {
    name: '',
    longitude: null,
    latitude: null,
  }
};

const getters = {
  isSuccess(state) {
    return state.isSuccess;
  },
  isSending(state) {
    return state.isSending;
  },
  selectedType(state) {
    return state.type;
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
  note(state) {
    return state.note;
  },
  selectedAnnounceTypeName(state) {
    return state.type === 'band' ? 'groupe' : 'musicien';
  },
  isValid(state) {
    return state.instrument !== '' && state.styles.length > 0 && state.type !== '' && state.location.name !== '';
  },
  isStepStylesValid(state) {
    return state.styles.length > 0;
  },
  isStepLocationValid(state) {
    return state.location.name !== '';
  }
};

const mutations = {
  [UPDATE_IS_SUCCESS](state, isSuccess) {
    state.isSuccess = isSuccess;
  },
  [UPDATE_IS_SENDING](state, isSending) {
    state.isSending = isSending;
  },
  [UPDATE_NOTE](state, note) {
    state.note = note;
  },
  [UPDATE_SELECTED_ANNOUNCE_TYPE](state, type) {
    state.type = type;
  },
  [UPDATE_SELECTED_INSTRUMENTS](state, instrument) {
    state.instrument = instrument;
  },
  [UPDATE_SELECTED_STYLES](state, style) {
    if (state.styles.includes(style)) {
      state.styles = state.styles.filter(_style => _style.id !== style.id);
    } else {
      state.styles.push(style);
    }
  },
  [UPDATE_SELECTED_LOCATION](state, {long, lat, name}) {
    state.location.name = name;
    state.location.longitude = long;
    state.location.latitude = lat;
  },
  [RESET](state) {
    state.isSending = false;
    state.isSuccess = false;
    state.type = '';
    state.note = '';
    state.instrument = '';
    state.styles = [];
    state.location.name = '';
    state.location.longitude = null;
    state.location.latitude = null;
  }
};

const actions = {
  async updateAnnounceType({commit}, type) {
    commit(UPDATE_SELECTED_ANNOUNCE_TYPE, type);
  },
  async updateSelectedInstruments({commit}, {instrument}) {
    commit(UPDATE_SELECTED_INSTRUMENTS, instrument);
  },
  async updateSelectedStyles({commit}, {style}) {
    commit(UPDATE_SELECTED_STYLES, style);
  },
  async updateLocation({commit}, {long, lat, name}) {
    commit(UPDATE_SELECTED_LOCATION, {long, lat, name});
  },
  updateNote({commit}, note) {
    commit(UPDATE_NOTE, note);
  },
  async send({commit, state}) {
    commit(UPDATE_IS_SENDING, true);
    try {
      await announceApi.add({
        type: state.type === TYPES_ANNOUNCE_BAND_LABEL ? TYPES_ANNOUNCE_BAND : TYPES_ANNOUNCE_MUSICIAN,
        note: state.note,
        styles: state.styles.map(style => style.id),
        instrument: state.instrument.id,
        locationName: state.location.name,
        longitude: state.location.longitude,
        latitude: state.location.latitude,
      });
      commit(UPDATE_IS_SUCCESS, true);
    } catch (e) {
      console.log(e);
    }
    commit(UPDATE_IS_SENDING, false);
  },
  reset({commit}) {
    commit(RESET);
  }
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}