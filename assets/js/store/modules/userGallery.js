import galleryApi from '../../api/publication/gallery';

const IS_LOADING = 'IS_LOADING';
const IS_LOADING_IMAGES = 'IS_LOADING_IMAGES';
const UPDATE_GALLERY = 'UPDATE_GALLERY';
const UPDATE_IMAGES = 'UPDATE_IMAGES';
const UPDATE_IMAGES_PREPEND = 'UPDATE_IMAGES_PREPEND';
const UPDATE_IMAGES_REMOVE = 'UPDATE_IMAGES_REMOVE';

const state = {
  isLoading: false,
  isLoadingImages: false,
  gallery: {},
  images: [],
};

const getters = {
  isLoading(state) {
    return state.isLoading;
  },
  gallery(state) {
    return state.gallery;
  },
  coverImage(state) {
    return state.gallery.coverImage;
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
  [UPDATE_IMAGES_PREPEND](state, image) {
    state.images = [image, ...state.images];
  },
  [UPDATE_IMAGES_REMOVE](state, image) {
    state.images = state.images.filter((item) => item.id !== image.id);
  }
};

const actions = {
  async loadGallery({commit}, id) {
    commit(IS_LOADING, true);
    const gallery = await galleryApi.getUserGallery(id);
    commit(UPDATE_GALLERY, gallery);
    commit(IS_LOADING, false);
  },
  async loadImages({commit}, galleryId) {
    commit(IS_LOADING_IMAGES, true);
    const images = await galleryApi.getUserImages(galleryId);
    commit(UPDATE_IMAGES, images);
    commit(IS_LOADING_IMAGES, false);
  },
  async edit({commit}, {id, title, description}) {
    const gallery = await galleryApi.editGallery({id, title, description});
    commit(UPDATE_GALLERY, gallery);
  },
  async editCover({commit}, {image}) {
    const gallery = await galleryApi.patchCoverGallery({imageId: image.id});
    commit(UPDATE_GALLERY, gallery);
  },
  async addImage({commit}, image) {
    commit(UPDATE_IMAGES_PREPEND, image);
  },
  async removeImage({commit}, image) {
    await galleryApi.removeImage(image.id);
    commit(UPDATE_IMAGES_REMOVE, image);
  }
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}