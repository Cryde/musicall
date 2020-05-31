<template>
    <b-row>
        <b-col cols="12">
            <b-row>
                <b-col cols="12">
                    <b-button v-b-modal.modal-send-message variant="primary" class="float-right">
                        <i class="far fa-comment-dots"></i> Envoyer un message
                    </b-button>
                    <h1>Messages</h1>
                </b-col>
            </b-row>

            <b-row id="thread-container" class="content-box p-2 mt-4">
                <b-col cols="4">
                    <b-spinner v-if="isLoading"></b-spinner>
                    <thread-list v-else />
                </b-col>
                <b-col cols="8" class="h-100">
                    <thread/>
                </b-col>
            </b-row>
        </b-col>
        <send-message-modal/>
    </b-row>
</template>

<script>
  import SendMessageModal from './modal/SendMessageModal'
  import {mapGetters} from "vuex";
  import ThreadList from "./ThreadList";
  import Thread from "./Thread";

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