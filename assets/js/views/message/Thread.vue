<template>
    <b-row class="h-100" v-if="currentThreadId">
        <b-col cols="12" id="message-container" ref="message-container">
            <b-row v-if="isLoadingMessages" class="text-center pt-5">
                <b-col cols="12">
                    <b-spinner/>
                </b-col>
            </b-row>
            <b-row v-else v-for="message in messages" :key="message.id">
                <b-col :cols="12">
                    <b-col cols="8" :class="{'offset-4 is-sender text-right': message.is_sender}">
                        <p class="message-body" v-html="autoLink(message.content)"
                           v-b-tooltip.noninteractive.hover :title="message.creation_datetime | relativeDate"
                        ></p>
                    </b-col>
                </b-col>
            </b-row>
        </b-col>
        <b-col :cols="12" class="align-content-start flex-wrap">
            <b-textarea v-model="content"></b-textarea>
            <b-button variant="primary" class="mt-2 float-right" :disabled="!content.length" @click="send">
                Envoyer
            </b-button>
        </b-col>
    </b-row>
    <b-row v-else class="text-center pt-4"></b-row>
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
      ...mapGetters('messages', ['messages', 'isLoadingMessages', 'currentThreadId'])
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
      scrollBottom() {
        this.$nextTick(() => {
          const messageContainer = document.querySelector('#message-container');
          messageContainer.scrollTop = messageContainer.scrollHeight;
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