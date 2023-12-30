<template>
  <div class="columns">
    <div class="column is-12">
      <div class="columns">
        <div class="column is-12">
          <b-button
              @click="$refs['modal-send-message'].open()"
              type="is-info"
              class="is-pulled-right mr-3"
              icon-left="comment-dots"
          >
            Envoyer un message
          </b-button>
          <h1 class="subtitle is-3">Messages</h1>
        </div>
      </div>

      <div id="thread-container" class="columns card inbox-messages">
        <div class="column is-4 pt-0 pb-0 pl-0">
          <b-loading :active="isLoading"/>
          <thread-list v-if="!isLoading"/>
        </div>
        <div class="column is-8 h-100">
          <thread/>
        </div>
      </div>
    </div>
    <send-message-modal ref="modal-send-message"/>
  </div>
</template>

<script>
import SendMessageModal from './modal/SendMessageModal.vue'
import {mapGetters} from "vuex";
import ThreadList from "./ThreadList.vue";
import Thread from "./Thread.vue";

export default {
  components: {ThreadList, SendMessageModal, Thread},
  computed: {
    ...mapGetters('messages', ['isLoading'])
  },
  async mounted() {
    await this.$store.dispatch('messages/loadThreads');
  }
}
</script>