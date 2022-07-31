import commentApi from '../../api/comment/comment';

const IS_LOADING = 'IS_LOADING';
const UPDATE_THREAD_ID = 'UPDATE_THREAD_ID';
const UPDATE_COMMENTS = 'UPDATE_COMMENTS';
const UPDATE_TOTAL_COMMENTS = 'UPDATE_TOTAL_COMMENTS';
const UPDATE_THREAD = 'UPDATE_THREAD';
const ADD_COMMENT = 'ADD_COMMENT';

const state = {
  id: null,
  isLoading: true,
  thread: null,
  comments: [],
  totalComments: 0,
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
  },
  totalComments(state) {
    return state.totalComments;
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
    state.totalComments += 1;
  },
  [UPDATE_TOTAL_COMMENTS](state, totalComments) {
    state.totalComments = totalComments;
  }
};

const actions = {
  async loadThread({commit}, threadId) {
    commit(IS_LOADING, true);
    commit(UPDATE_THREAD_ID, threadId);
    const thread = await commentApi.getThread({threadId});
    const commentsResponse = await commentApi.getComments({thread: threadId});

    commit(UPDATE_COMMENTS, commentsResponse['hydra:member']);
    commit(UPDATE_TOTAL_COMMENTS, commentsResponse['hydra:totalItems']);

    commit(UPDATE_THREAD, thread);

    commit(IS_LOADING, false);
  },
  async postComment({commit, state}, {content}) {
    const comment = await commentApi.postComment({
      thread: `/api/comment_threads/${state.id}`,
      content
    });

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