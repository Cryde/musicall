import messageApi from "../../api/message/message";

const IS_LOADING = 'IS_LOADING';
const IS_LOADING_MESSAGES = 'IS_LOADING_MESSAGES';
const IS_ADDING_MESSAGE = 'IS_ADDING_MESSAGE';
const UPDATE_THREADS = 'UPDATE_THREADS';
const UPDATE_THREAD_IN_THREADS = 'UPDATE_THREAD_IN_THREADS';
const ADD_MESSAGE_TO_MESSAGES = 'ADD_MESSAGE_TO_MESSAGES';
const UPDATE_CURRENT_THREAD_ID = 'UPDATE_CURRENT_THREAD_ID';
const UPDATE_MESSAGES = 'UPDATE_MESSAGES';
const UPDATE_THREAD_IS_READ = 'UPDATE_THREAD_IS_READ';

const state = {
  id: null,
  isLoading: true,
  isLoadingMessages: false,
  isAddingMessage: false,
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
  },
  isAddingMessage(state) {
    return state.isAddingMessage;
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
  [ADD_MESSAGE_TO_MESSAGES](state, message) {
    state.messages.push(message);
  },
  [UPDATE_CURRENT_THREAD_ID](state, threadId) {
    state.currentThreadId = threadId;
  },
  [UPDATE_THREAD_IS_READ](state, {metaThreadId, isRead}) {
    state.threads = state.threads.map((item) => {
      if (item.id === metaThreadId) {
        item.is_read = isRead;
      }
      return item;
    })
  },
  [IS_ADDING_MESSAGE](state, isAdding) {
    state.isAddingMessage = isAdding;
  }
};

const actions = {
  async loadThreads({commit}) {
    commit(IS_LOADING, true);
    const threads = await messageApi.getThreads();
    commit(UPDATE_THREADS, threads['hydra:member']);
    commit(IS_LOADING, false);
  },
  async loadThread({commit, dispatch}, {meta}) {
    commit(IS_LOADING_MESSAGES, true);
    commit(UPDATE_CURRENT_THREAD_ID, meta.thread.id);
    const messages = await messageApi.getMessages({threadId: meta.thread.id});
    commit(UPDATE_MESSAGES, messages['hydra:member'].reverse());

    commit(UPDATE_THREAD_IS_READ, {metaThreadId: meta.id, isRead: true});
    dispatch('notifications/decrementMessageCount', {}, {root: true});
    commit(IS_LOADING_MESSAGES, false);
    await messageApi.markThreadAsRead({threadMetaId: meta.id});
  },
  async postMessage({commit, state}, {recipientId, content}) {
    const message = await messageApi.postMessage({recipientId, content});
    const threads = await messageApi.getThreads(); // todo : improve this (only load thread meta related to this thead/user)
    commit(UPDATE_THREADS, threads['hydra:member']);

    if (state.currentThreadId && state.currentThreadId === message.thread.id) {
      // we only add message if the thread is open
      commit(ADD_MESSAGE_TO_MESSAGES, message);
    }
  },
  async postMessageInThread({commit}, {threadId, content}) {
    commit(IS_ADDING_MESSAGE, true);
    const message = await messageApi.postMessageInThread({threadId, content});
    const threads = await messageApi.getThreads(); // todo : improve this (only load thread meta related to this thead/user)
    commit(ADD_MESSAGE_TO_MESSAGES, message);
    commit(UPDATE_THREADS, threads['hydra:member']);
    commit(IS_ADDING_MESSAGE, false);
  }
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions
}