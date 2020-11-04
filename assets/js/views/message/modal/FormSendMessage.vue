<template>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">{{ modalTitle }}</p>
      <button
          type="button"
          class="delete"
          @click="$emit('close')"/>
    </header>

    <section class="modal-card-body">

      <div v-if="!isSent">
        <v-select
            v-if="!selectedRecipient"
            v-model="recipient"
            @search="fetchOptions"
            :options="recipientsOptions"
            label="username"
            :disabled="isSending"
        ></v-select>

        <b-input type="textarea" v-model="content" class="mt-3" :disabled="isSending"></b-input>
      </div>
      <div v-else class="has-text-centered">
        <i class="fas fa-check fa-5x has-text-success mb-3"></i><br/>
        Votre message a bien été envoyé. <br/>
        Vous pouvez
        <router-link :to="{name: 'message_list'}">voir vos messages ici.</router-link>
      </div>
    </section>

    <footer class="modal-card-foot">
      <b-button type="is-light" @click="$emit('close')" v-if="!isSent">Annuler</b-button>
      <b-button type="is-light" @click="$emit('close')" v-if="isSent">Fermer</b-button>

      <b-button type="is-info"
                v-if="!isSent"
                :loading="isSending"
                :disabled="!canSendMessage || isSending"
                icon-left="paper-plane"
                @click="sendMessage">
        Envoyer
      </b-button>
    </footer>
  </div>
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
      isModalOpen: false,
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
    this.recipient = this.selectedRecipient;
    /*    this.$refs['modal-send-message'].$on('show', () => {
          this.recipient = this.selectedRecipient;
        });

        this.$refs['modal-send-message'].$on('hidden', () => {
          this.reset();
        });*/
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
  },
  destroyed() {
    this.reset();
  }
}
</script>