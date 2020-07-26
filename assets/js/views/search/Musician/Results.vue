<template>
    <b-col xl="8" offset-xl="2" v-if="!isSuccess" class="pt-5 pb-4">
        <b-alert variant="info" show v-if="!isSearching" class="mt-5">
            <i class="fas fa-filter fa-3x mr-3 mt-3 mb-1 float-left"></i>
            Cherchez parmis + de 2000 annonces des musiciens ou groupes.
            Sélectionnez vos filtres ci-dessus pour effectuer la recherche parmi les musiciens ou groupes.
        </b-alert>
        <div v-else class="text-center mt-5">
            <b-spinner/>
        </div>
    </b-col>
    <b-col v-else class="pt-5 pb-4">
        <div v-if="isSearching" class="text-center">
            <b-spinner/>
        </div>
        <div v-else>
            <h2>Résultats</h2>
            <b-row v-if="results.length">
                <b-col xl="6" cols="12" v-for="announce in results" :key="announce.id" class="d-flex">
                    <div class="bg-white p-3 mt-4 card-result shadow d-flex flex-column w-100">
                        <b-row class="mb-auto">
                            <b-col cols="3" class="text-center">
                                <b-avatar></b-avatar>
                                <br/>
                                <span class="mt-2 d-inline-block text-truncate w-100">{{ announce.user.username }}</span>
                            </b-col>
                            <b-col>
                                <strong>Localisation:</strong> {{ announce.location_name }}<br/>
                                <strong v-if="announce.distance">Distance:</strong>
                                <span v-if="announce.distance">{{ announce.distance | formatDistance }}<br/></span>
                                <strong>{{ announce.type | instrumentLabel }}:</strong> {{ announce.instrument }}<br/>
                                <strong>Styles:</strong> {{ announce.styles }}<br/>
                            </b-col>
                        </b-row>
                        <b-row class="align-content-end mt-2 ">
                            <b-col>
                                <b-button v-if="isAuthenticated"
                                          class="mt-auto float-right" size="sm"
                                          @click="openSendMessageModal(announce.user)">
                                    Contacter
                                </b-button>
                                <b-button v-else class="mt-auto float-right" size="sm"
                                          v-b-tooltip.noninteractive.hover
                                          title="Vous devez être inscrit ou connecté pour contacter un utilisateur"
                                >
                                    Contacter
                                </b-button>
                                <b-button v-if="announce.note" class="mt-auto float-right mr-2" size="sm">
                                    Voir la note
                                </b-button>
                            </b-col>
                        </b-row>
                    </div>
                </b-col>
            </b-row>
            <b-row v-else>
                <b-col class="mt-5 text-center ">
                    <i class="far fa-sad-tear fa-3x mb-2"></i><br/>
                    Il n'y a malheureusement pas de résultat pour votre recherche.<br/>
                    Essayez d'enlever ou de modifier un des filtres pour avoir d'autre résultats.
                </b-col>
            </b-row>

            <hr class="mt-5 w-25"/>

            <b-row class="mt-5">
                <b-col xl="8" offset-xl="2">
                    <b-alert variant="info" show>
                        <i class="fas fa-search fa-3x float-left mr-3"></i>

                        Vous ne trouvez pas ce que vous chercher ?<br/>
                        N'hésitez pas à
                        <b-link :to="{name: 'announce_musician_add'}">poster une annonce gratuitement.</b-link>
                    </b-alert>
                </b-col>
            </b-row>
        </div>
        <send-message-modal
                v-if="selectedRecipient && isAuthenticated"
                :selected-recipient="selectedRecipient"
                :show-is-sent-confirmation="true"
        />
    </b-col>
</template>

<script>
  import {mapGetters} from "vuex";
  import {TYPES_ANNOUNCE_MUSICIAN, TYPES_ANNOUNCE_BAND} from "../../../constants/types";
  import SendMessageModal from "../../message/modal/SendMessageModal";

  export default {
    components: {SendMessageModal},
    data() {
      return {
        selectedRecipient: null,
      }
    },
    computed: {
      ...mapGetters('searchMusician', ['isSearching', 'isSuccess', 'results']),
      ...mapGetters('security', ['isAuthenticated']),
    },
    filters: {
      formatDistance(distance) {
        const formattedDistance = (parseFloat(distance) * 100).toFixed(2);
        return `± ${formattedDistance} km`;
      },
      instrumentLabel(type) {
        if(type === TYPES_ANNOUNCE_MUSICIAN) {
          return 'Instrument cherché';
        }

        if(type === TYPES_ANNOUNCE_BAND) {
          return 'Instrument joué';
        }
      }
    },
    methods: {
      openSendMessageModal(recipient) {
        this.selectedRecipient = recipient;
        this.$nextTick(() => {
          this.$bvModal.show('modal-send-message');
        })
      }
    }
  }
</script>

<style>
    .card-result {
        border-radius: 5px;
    }

</style>