import styleApi from '../../api/attribute/style';

const IS_LOADING = 'IS_LOADING';
const UPDATE_STYLES = 'UPDATE_STYLES';

const state = {
  isLoading: true,
  styles: [],
};

const getters = {
  isLoadingStyles(state) {
    return state.isLoading;
  },
  styles(state) {
    return state.styles;
  }
};

const mutations = {
  [IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  },
  [UPDATE_STYLES](state, styles) {
    state.styles = styles;
  }
};

const actions = {
  async loadStyles({commit}) {
    commit(IS_LOADING, true);
    const styles = await styleApi.listStyle();
    commit(UPDATE_STYLES, styles)
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