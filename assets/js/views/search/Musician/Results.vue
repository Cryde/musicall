<template>
  <div v-if="!isSuccess" class="columns pt-5 pb-4">
    <div class="mt-5 column is-6 is-offset-3">
      <b-message type="is-info" v-if="!isSearching" has-icon icon="filter">
        Cherchez parmis + de 2000 annonces des musiciens ou groupes.
        Sélectionnez vos filtres ci-dessus pour effectuer la recherche parmi les musiciens ou groupes.
      </b-message>
      <b-loading v-else active/>
    </div>
  </div>
  <div v-else class="pt-5 pb-4">
    <b-loading v-if="isSearching" active/>
    <div v-else>
      <h2 class="subtitle is-4">Résultats</h2>
      <div class="columns is-multiline" v-if="results.length">
        <result-item v-for="announce in results" :key="announce.id"
                     :announce="announce"
                     @open-message-modal="openSendMessageModal"
                     class="column is-4 is-flex is-flex-direction-column"/>
      </div>
      <div v-else class="columns">
        <div class="column mt-5 has-text-centered">
          <i class="far fa-sad-tear fa-3x mb-2"></i><br/>
          Il n'y a malheureusement pas de résultat pour votre recherche.<br/>
          Essayez d'enlever ou de modifier un des filtres pour avoir d'autre résultats.
        </div>
      </div>

      <hr class="mt-5 w-25"/>

      <div class="is-12  columns mt-5">
        <div class="column is-8 is-offset-2">
          <b-message type="is-info" has-icon icon="search">
            Vous ne trouvez pas ce que vous chercher ?<br/>
            N'hésitez pas à
            <span @click="openAddMusicianAnnounce">poster une annonce gratuitement.</span>
          </b-message>
        </div>
      </div>
    </div>
    <send-message-modal
        ref="modal-send-message"
        v-if="selectedRecipient && isAuthenticated"
        :selected-recipient="selectedRecipient"
        :show-is-sent-confirmation="true"
    />
  </div>
</template>

<script>
import {mapGetters} from "vuex";
import SendMessageModal from "../../message/modal/SendMessageModal.vue";
import Avatar from "../../../components/user/Avatar.vue";
import ResultItem from "./ResultItem.vue";
import AddMusicianAnnounceForm from "../../user/Announce/modal/AddMusicianAnnounceForm.vue";

export default {
  components: {ResultItem, Avatar, SendMessageModal},
  props: {
    isSearching: {
      type: Boolean,
      default: false,
    },
    isSuccess: {
      type: Boolean,
      default: false,
    },
    results: {
      type: Array,
      default: []
    }
  },
  data() {
    return {
      selectedRecipient: null,
    }
  },
  computed: {
    ...mapGetters('security', ['isAuthenticated']),
  },
  methods: {
    openSendMessageModal(recipient) {
      this.selectedRecipient = recipient;
      this.$nextTick(() => {
        this.$refs['modal-send-message'].open();
      })
    },
    openAddMusicianAnnounce() {
      this.$buefy.modal.open({
        parent: this,
        component: AddMusicianAnnounceForm,
        hasModalCard: true,
        trapFocus: true
      })
    }
  }
}
</script>