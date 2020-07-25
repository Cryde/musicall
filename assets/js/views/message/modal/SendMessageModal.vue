<template>
    <b-modal :title="modalTitle" id="modal-send-message" ref="modal-send-message">

        <div v-if="!isSent">
            <v-select
                    v-if="!selectedRecipient"
                    v-model="recipient"
                    @search="fetchOptions"
                    :options="recipientsOptions"
                    label="username"
                    :disabled="isSending"
            ></v-select>

            <b-textarea v-model="content" class="mt-3" :disabled="isSending"></b-textarea>
        </div>
        <div v-else class="text-center">
            <i class="fas fa-check fa-5x text-success mb-3"></i><br/>
            Votre message a bien été envoyé. <br/>
            Vous pouvez
            <b-link :to="{name: 'message_list'}">voir vos messages ici.</b-link>
        </div>
        <template v-slot:modal-footer="{ ok, cancel, hide }">
            <b-button @click="cancel()" class="mr-auto" v-if="!isSent">
                Annuler
            </b-button>
            <b-button @click="cancel()" class="mr-auto" v-if="isSent">
                Fermer
            </b-button>
            <b-button v-if="!isSent" variant="primary" @click="sendMessage" :disabled="!canSendMessage || isSending">
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
    props: {
      selectedRecipient: {
        default: null,
      },
      showIsSentConfirmation: {
        default: false,
        type: Boolean
      }
    },
    data() {
      return {
        isSent: false,
        isSending: false,
        content: '',
        recipient: null,
        recipientsOptions: [],
      }
    },
    computed: {
      canSendMessage() {
        return this.content.trim().length > 0 && this.recipient;
      },
      modalTitle() {
        return this.selectedRecipient ? `Envoyer un message à ${this.selectedRecipient.username}` : 'Envoyer un message';
      }
    },
    mounted() {
      this.$refs['modal-send-message'].$on('show', () => {
        this.recipient = this.selectedRecipient;
      });

      this.$refs['modal-send-message'].$on('hidden', () => {
        this.reset();
      });
    },
    methods: {
      sendMessage() {
        this.isSending = true;
        try {
          this.$store.dispatch('messages/postMessage', {recipientId: this.recipient.id, content: this.content});
          this.isSent = true;
          if (!this.showIsSentConfirmation) {
            this.$bvModal.hide('modal-send-message');
          }
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
        this.isSent = false;
        this.isSending = false;
        this.content = '';
        this.recipient = null;
        this.recipientsOptions = [];
      }
    }
  }
</script>