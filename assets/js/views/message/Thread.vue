<template>
  <div class="thread" v-if="currentThreadId">

    <perfect-scrollbar ref="scroll">
      <div class="column is-12" id="message-container">
        <b-loading :active="isLoadingMessages"/>
        <div class="columns mb-0" v-if="!isLoadingMessages" v-for="message in orderedMessages" :key="message.id">
          <div class="column is-8 is-message-body" :class="{'is-offset-4 is-sender has-text-right': isSender(message)}">
            <b-tooltip :label="message.creation_datetime | relativeDate" type="is-dark" :position="isSender(message) ? 'is-left' : 'is-right'">
              <p v-html="autoLink(message.content)" class="has-background-light" :class="{'has-background-info' : isSender(message)}"></p>
            </b-tooltip>
          </div>
        </div>
      </div>
    </perfect-scrollbar>

    <div class="columns form-container-textarea is-flex-wrap-wrap">
      <div class="column is-12">
        <b-input type="textarea" v-model="content" :disabled="isAddingMessage"></b-input>
        <b-button icon-left="paper-plane" type="is-info"
                  :loading="isAddingMessage"
                  class="mt-2 is-pulled-right" :disabled="!content.length || isAddingMessage" @click="send">
          Envoyer
        </b-button>
      </div>
    </div>
  </div>
  <div v-else class="has-text-centered pt-5">
    Sélectionnez un thread sur la gauche ou créez un nouvelle conversation.
  </div>
</template>

<script>
import {mapGetters} from "vuex";
import Autolinker from 'autolinker';

export default {
  data() {
    return {
      content: ''
    }
  },
  computed: {
    ...mapGetters('messages', ['messages', 'isLoadingMessages', 'currentThreadId', 'isAddingMessage']),
    ...mapGetters('user', ['user']),
    orderedMessages() {
      return this.messages.reverse()
    }
  },
  mounted() {
    this.$store.subscribe((mutation, state) => {
      if (mutation.type === 'messages/IS_LOADING_MESSAGES' && mutation.payload === false) {
        // when the loading of the message for a thread is done
        this.scrollBottom();
      }

      if (mutation.type === 'messages/ADD_MESSAGE_TO_MESSAGES') {
        // when we added a message
        this.scrollBottom();
      }
    })
  },
  methods: {
    isSender(message) {
      return message.author.id === this.user.id;
    },
    scrollBottom() {
      this.$nextTick(() => {
        this.$refs.scroll.$el.scrollTop = this.$refs.scroll.$el.scrollHeight;
      });
    },
    autoLink(str) {
      return Autolinker.link(str);
    },
    async send() {
      try {
        await this.$store.dispatch('messages/postMessageInThread', {
          threadId: this.currentThreadId,
          content: this.content
        });
        this.content = '';
      } catch (e) {
        console.error(e);
      }
    }
  }
}
</script>

<style>

#message-container .is-message-body p {
  word-wrap: break-word;
  display: inline-block;
  padding: 7px 10px;
  border-radius: 10px;
}

#message-container .is-message-body.is-sender p {
  color: white;
}

#message-container .is-message-body a {
  color: #5B87AE;
  text-decoration: underline;
}

#message-container .is-sender.is-message-body a {
  color: white;
  text-decoration: underline;
}

.thread {
  height: 100%;
}

.thread .ps {
  height: 70%
}
</style>