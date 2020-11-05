import userPublication from '../../api/userPublication';
import userPublicationApi from '../../api/userPublication';

const STATUS_DRAFT = 0;
const UPDATE_PUBLICATION = 'UPDATE_PUBLICATION';
const UPDATE_ERRORS = 'UPDATE_ERRORS';
const UPDATE_ERRORS_PUBLISHING = 'UPDATE_ERRORS_PUBLISHING';
const UPDATE_CONTENT = 'UPDATE_CONTENT';
const UPDATE_IS_LOADING = 'UPDATE_IS_LOADING';
const UPDATE_IS_SAVING = 'UPDATE_IS_SAVING';
const UPDATE_IS_PUBLISHING = 'UPDATE_IS_PUBLISHING';

const state = {
  isLoading: true,
  isSaving: false,
  isPublishing: false,
  errors: [],
  errorsPublish: [],
  id: '',
  content: '',
  title: '',
  description: '',
  cover: '',
  slug: '',
  statusId: '',
};

const getters = {
  isLoading(state) {
    return state.isLoading;
  },
  isSaving(state) {
    return state.isSaving;
  },
  isPublishing(state) {
    return state.isPublishing;
  },
  errors(state) {
    return state.errors;
  },
  errorsPublish(state) {
    return state.errorsPublish;
  },
  id(state) {
    return state.id;
  },
  content(state) {
    return state.content;
  },
  title(state) {
    return state.title;
  },
  description(state) {
    return state.description;
  },
  cover(state) {
    return state.cover;
  },
  slug(state) {
    return state.slug;
  },
  statusId(state) {
    return state.statusId;
  },
  isDraft(state) {
    return state.statusId === STATUS_DRAFT;
  }
};

const mutations = {
  [UPDATE_PUBLICATION](state, publication) {
    state.id = publication.id;
    state.content = publication.content;
    state.title = publication.title;
    state.description = publication.short_description;
    state.cover = publication.cover;
    state.slug = publication.slug;
    state.statusId = publication.status_id;
  },
  [UPDATE_CONTENT](state, content) {
    state.content = content;
  },
  [UPDATE_ERRORS](state, errors) {
    state.errors = errors;
  },
  [UPDATE_ERRORS_PUBLISHING](state, errors) {
    state.errorsPublish = errors;
  },
  [UPDATE_IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  },
  [UPDATE_IS_PUBLISHING](state, isPublishing) {
    state.isPublishing = isPublishing;
  },
  [UPDATE_IS_SAVING](state, isSaving) {
    state.isSaving = isSaving;
  }
};

const actions = {
  async loadPublication({commit}, id) {
    commit(UPDATE_IS_LOADING, true);
    const publication = await userPublication.getPublication(id);
    commit(UPDATE_PUBLICATION, publication);
    commit(UPDATE_IS_LOADING, false);
  },
  updateContent({commit}, content) {
    commit(UPDATE_CONTENT, content);
  },
  async save({commit, state}, {title, description, content}) {
    commit(UPDATE_IS_SAVING, true);
    commit(UPDATE_ERRORS, []);
    try {
      const publication = await userPublication.savePublication({
        id: state.id,
        data: {title, short_description: description, content}
      });

      commit(UPDATE_PUBLICATION, {content, ...publication});
    } catch (e) {
      commit(UPDATE_ERRORS, e.response.data.violations.map(violation => violation.title));
    }

    commit(UPDATE_IS_SAVING, false);
  },
  async publish({commit, state}, id = null) {
    commit(UPDATE_ERRORS_PUBLISHING, []);
    commit(UPDATE_IS_PUBLISHING, true);
    try {
      await userPublicationApi.publishPublicationApi(id || state.id);
    } catch (e) {
      commit(UPDATE_ERRORS_PUBLISHING, e.response.data.violations.map(violation => violation.title))
    }
    commit(UPDATE_IS_PUBLISHING, false);
  }
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}