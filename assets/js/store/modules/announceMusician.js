const UPDATE_SELECTED_INSTRUMENTS = 'UPDATE_SELECTED_INSTRUMENTS';
const UPDATE_SELECTED_STYLES = 'UPDATE_SELECTED_STYLES';
const UPDATE_SELECTED_LOCATION = 'UPDATE_SELECTED_LOCATION';
const UPDATE_SELECTED_ANNOUNCE_TYPE = 'UPDATE_SELECTED_ANNOUNCE_TYPE';

const state = {
  isLoading: true,
  type: '',
  instrument: [],
  styles: [],
  location: {
    name: '',
    longitude: null,
    latitude: null,
  }
};

const getters = {
  isLoadingInstruments(state) {
    return state.isLoading;
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
  }
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}