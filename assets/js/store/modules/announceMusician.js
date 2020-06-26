const UPDATE_SELECTED_INSTRUMENTS = 'UPDATE_SELECTED_INSTRUMENTS';
const UPDATE_SELECTED_STYLES = 'UPDATE_SELECTED_STYLES';
const UPDATE_SELECTED_LOCATION = 'UPDATE_SELECTED_LOCATION';
const UPDATE_SELECTED_ANNOUNCE_TYPE = 'UPDATE_SELECTED_ANNOUNCE_TYPE';
const UPDATE_IS_SENDING = 'UPDATE_IS_SENDING';
const UPDATE_NOTE = 'UPDATE_NOTE';

const state = {
  isSending: false,
  type: '',
  note: '',
  instrument: [],
  styles: [],
  location: {
    name: '',
    longitude: null,
    latitude: null,
  }
};

const getters = {
  isSending(state) {
    return state.isSending;
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
  selectedAnnounceTypeName(state) {
    return state.type === 'band' ? 'groupe' : 'musicien';
  }
};

const mutations = {
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
  async send({commit}) {
    commit(UPDATE_IS_SENDING, true);
  }
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}