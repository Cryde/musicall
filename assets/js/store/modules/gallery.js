import galleryApi from '../../api/publication/gallery';

const IS_LOADING = 'IS_LOADING';
const IS_LOADING_IMAGES = 'IS_LOADING_IMAGES';
const UPDATE_GALLERY = 'UPDATE_GALLERY';
const UPDATE_IMAGES = 'UPDATE_IMAGES';
const RESET_STATE = 'RESET_STATE';

const state = {
  isLoading: true,
  isLoadingImages: false,
  gallery: {},
  images: [],
};

const getters = {
  isLoading(state) {
    return state.isLoading;
  },
  isLoadingImages(state) {
    return state.isLoadingImages;
  },
  gallery(state) {
    return state.gallery;
  },
  images(state) {
    return state.images;
  }
};

const mutations = {
  [IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  },
  [IS_LOADING_IMAGES](state, isLoading) {
    state.isLoadingImages = isLoading;
  },
  [UPDATE_GALLERY](state, gallery) {
    state.gallery = gallery;
  },
  [UPDATE_IMAGES](state, images) {
    state.images = images;
  },
  [RESET_STATE](state) {
    state = {
      isLoading: true,
      isLoadingImages: false,
      gallery: {},
      images: [],
    };
  }
};

const actions = {
  async loadGallery({commit}, slug) {
    commit(IS_LOADING, true);
    const gallery = await galleryApi.getGallery(slug);
    commit(UPDATE_GALLERY, gallery);
    commit(IS_LOADING, false);
  },
  async loadImages({commit}, slug) {
    commit(IS_LOADING_IMAGES, true);
    const images = await galleryApi.getGalleryImages(slug);
    commit(UPDATE_IMAGES, images);
    commit(IS_LOADING_IMAGES, false);
  },
  resetState({commit}) {
    commit(RESET_STATE);
  }
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}