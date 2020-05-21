import commentApi from '../../api/comment/comment';

const IS_LOADING = 'IS_LOADING';
const UPDATE_THREAD_ID = 'UPDATE_THREAD_ID';
const UPDATE_COMMENTS = 'UPDATE_COMMENTS';
const UPDATE_THREAD = 'UPDATE_THREAD';
const ADD_COMMENT = 'ADD_COMMENT';

const state = {
  id: null,
  isLoading: true,
  thread: null,
  comments: [],
};

const getters = {
  isLoading(state) {
    return state.isLoading;
  },
  comments(state) {
    return state.comments;
  },
  thread(state) {
    return state.thread;
  }
};

const mutations = {
  [IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  },
  [UPDATE_THREAD_ID](state, threadId) {
    state.id = threadId;
  },
  [UPDATE_COMMENTS](state, comments) {
    state.comments = comments;
  },
  [UPDATE_THREAD](state, thread) {
    state.thread = thread;
  },
  [ADD_COMMENT](state, comment) {
    state.comments = [comment, ...state.comments];
    state.thread.comment_number += 1;
  }
};

const actions = {
  async loadThread({commit}, threadId) {
    commit(IS_LOADING, true);
    commit(UPDATE_THREAD_ID, threadId);
    const thread = await commentApi.getThread({threadId});

    commit(UPDATE_COMMENTS, thread.comments);
    delete thread.comments; // maybe it is not good ...
    // but the reason is that later we will only mutate the comments array
    commit(UPDATE_THREAD, thread);

    commit(IS_LOADING, false);
  },
  async postComment({commit, state}, {content}) {
    const comment = await commentApi.postComment({threadId: state.id, content});

    commit(ADD_COMMENT, comment);
  }
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}