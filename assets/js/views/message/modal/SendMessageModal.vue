<template>
    <b-modal title="Envoyer un message" id="modal-send-message" ref="modal-send-message">

        <v-select
                v-model="recipient"
                @search="fetchOptions"
                :options="recipientsOptions"
                label="username"
                :disabled="isSending"
        ></v-select>

        <b-textarea v-model="content" class="mt-3" :disabled="isSending"></b-textarea>

        <template v-slot:modal-footer="{ ok, cancel, hide }">
            <b-button @click="cancel()" class="mr-auto">
                Cancel
            </b-button>
            <b-button variant="primary" @click="sendMessage" :disabled="!canSendMessage || isSending">
                <b-spinner v-if="isSending" small/>
                <i v-else class="far fa-paper-plane"></i>
                Envoyer
            </b-button>
        </template>
    </b-modal>
</template>

<script>
  import vSelect from 'vue-select';
  import searchApi from "../../../api/search";
  import {debounce} from 'lodash';

  export default {
    components: {vSelect},
    data() {
      return {
        isSending: false,
        content: '',
        recipient: null,
        recipientsOptions: [],
      }
    },
    computed: {
      canSendMessage() {
        return this.content.trim().length > 0 && this.recipient;
      }
    },
    mounted() {
      this.$refs['modal-send-message'].$on('hidden', () => {
        this.reset();
      });
    },
    methods: {
      sendMessage() {
        this.isSending = true;
        try {
          this.$store.dispatch('messages/postMessage', {recipientId: this.recipient.id, content: this.content});
          this.$bvModal.hide('modal-send-message');
        } catch (e) {
          // todo handle error
        }
        this.isSending = false;
      },
      async fetchOptions(search, loading) {
        if (search.length >= 4) {
          loading(true);
          this.search(search, loading);
        }
      },
      search: debounce(async function (search, loading) {
        this.recipientsOptions = await searchApi.searchUsers(search);
        loading(false);
      }, 350),
      reset() {
        this.isSending = false;
        this.content = '';
        this.recipient = null;
        this.recipientsOptions = [];
      }
    }
  }
</script>