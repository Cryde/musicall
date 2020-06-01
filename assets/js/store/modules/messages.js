import messageApi from "../../api/message/message";

const IS_LOADING = 'IS_LOADING';
const IS_LOADING_MESSAGES = 'IS_LOADING_MESSAGES';
const UPDATE_THREADS = 'UPDATE_THREADS';
const UPDATE_THREAD_IN_THREADS = 'UPDATE_THREAD_IN_THREADS';
const ADD_THREAD_IN_THREADS = 'ADD_THREAD_IN_THREADS';
const ADD_MESSAGE_TO_MESSAGES = 'ADD_MESSAGE_TO_MESSAGES';
const UPDATE_CURRENT_THREAD_ID = 'UPDATE_CURRENT_THREAD_ID';
const UPDATE_MESSAGES = 'UPDATE_MESSAGES';
const UPDATE_THREAD_IS_READ = 'UPDATE_THREAD_IS_READ';

const state = {
  id: null,
  isLoading: true,
  isLoadingMessages: false,
  currentThreadId: null,
  threads: [],
  messages: [], // related to the current thread
};

const getters = {
  isLoading(state) {
    return state.isLoading;
  },
  isLoadingMessages(state) {
    return state.isLoadingMessages;
  },
  threads(state) {
    return state.threads;
  },
  currentThreadId(state) {
    return state.currentThreadId;
  },
  messages(state) {
    return state.messages;
  }
};

const mutations = {
  [IS_LOADING](state, isLoading) {
    state.isLoading = isLoading;
  },
  [IS_LOADING_MESSAGES](state, isLoading) {
    state.isLoadingMessages = isLoading;
  },
  [UPDATE_THREADS](state, threads) {
    state.threads = threads;
  },
  [UPDATE_MESSAGES](state, messages) {
    state.messages = messages;
  },
  [UPDATE_THREAD_IN_THREADS](state, {meta, thread}) {
    state.threads = state.threads.map((item) => {
      if (item.thread.id === thread.id) {
        item.thread = thread;
        item.meta = meta;
      }
      return item;
    })
  },
  [ADD_THREAD_IN_THREADS](state, {thread, meta, participants}) {
    state.threads = [{thread, meta, participants}, ...state.threads];
  },
  [ADD_MESSAGE_TO_MESSAGES](state, message) {
    state.messages.push(message);
  },
  [UPDATE_CURRENT_THREAD_ID](state, threadId) {
    state.currentThreadId = threadId;
  },
  [UPDATE_THREAD_IS_READ](state, {threadId, isRead}) {
    state.threads = state.threads.map((item) => {
      if (item.thread.id === threadId) {
        item.meta.is_read = isRead;
      }
      return item;
    })
  }
};

const actions = {
  async loadThreads({commit}) {
    commit(IS_LOADING, true);
    const threads = await messageApi.getThreads();
    commit(UPDATE_THREADS, threads);
    commit(IS_LOADING, false);
  },
  async loadThread({commit, dispatch}, {threadId}) {
    commit(IS_LOADING_MESSAGES, true);
    commit(UPDATE_CURRENT_THREAD_ID, threadId);
    const messages = await messageApi.getMessages({threadId});
    commit(UPDATE_MESSAGES, messages);

    commit(UPDATE_THREAD_IS_READ, {threadId, isRead: true});
    dispatch('notifications/decrementMessageCount', {}, {root: true});
    commit(IS_LOADING_MESSAGES, false);
    await messageApi.markThreadAsRead({threadId});
  },
  async postMessage({commit, state}, {recipientId, content}) {
    const {meta, thread, message, participants} = await messageApi.postMessage({recipientId, content});
    if (state.threads.find(item => item.thread.id === thread.id)) {
      commit(UPDATE_THREAD_IN_THREADS, {thread, meta});
    } else {
      commit(ADD_THREAD_IN_THREADS, {thread, meta, participants});
    }

    if (state.currentThreadId && state.currentThreadId === thread.id) {
      // we only add message if the thread is open
      commit(ADD_MESSAGE_TO_MESSAGES, message);
    }
  },
  async postMessageInThread({commit}, {threadId, content}) {
    const {meta, thread, message, participants} = await messageApi.postMessageInThread({threadId, content});
    commit(UPDATE_THREAD_IN_THREADS, {thread, meta});
    commit(ADD_MESSAGE_TO_MESSAGES, message);
  }
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}