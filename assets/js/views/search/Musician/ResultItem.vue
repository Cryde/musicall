<template>
  <div>
    <article class="media box" style="width: 100%">
      <figure class="media-left">
        <avatar :user="announce.user"/>
      </figure>
      <div class="media-content">
        <div class="content">
          <p>
            <strong>@{{ announce.user.username }}</strong>
            <br>
            <strong>Localisation:</strong> {{ announce.location_name }}<br/>
            <strong v-if="announce.distance">Distance:</strong>
            <span v-if="announce.distance">{{ announce.distance | formatDistance }}<br/></span>
            <strong>{{ announce.type | instrumentLabel }}:</strong> {{ announce.instrument }}<br/>
            <strong>Styles:</strong> {{ announce.styles }}<br/>
          </p>
        </div>
        <nav class="level is-mobile">
          <div class="level-left">
            <b-button v-if="isAuthenticated"
                      size="is-small"
                      class="mt-auto is-pulled-right "
                      @click="openSendMessageModal(announce.user)">
              Contacter
            </b-button>
            <b-button v-else class="mt-auto is-pulled-right" size="is-small"
                      v-b-tooltip.noninteractive.hover
                      title="Vous devez être inscrit ou connecté pour contacter un utilisateur"
            >
              Contacter
            </b-button>
            <b-button v-if="announce.note" class="mt-auto is-pulled-right mr-2" size="is-small">
              Voir la note
            </b-button>
          </div>
        </nav>
      </div>
    </article>
  </div>
</template>

<script>
import Avatar from "../../../components/user/Avatar";
import {TYPES_ANNOUNCE_BAND, TYPES_ANNOUNCE_MUSICIAN} from "../../../constants/types";
import {mapGetters} from "vuex";

export default {
  components: {Avatar},
  props: {
    announce: {
      type: Object,
      required: true
    }
  },
  computed: {
    ...mapGetters('security', ['isAuthenticated']),
  },
  filters: {
    formatDistance(distance) {
      const formattedDistance = (parseFloat(distance) * 100).toFixed(2);
      return `± ${formattedDistance} km`;
    },
    instrumentLabel(type) {
      if (type === TYPES_ANNOUNCE_MUSICIAN) {
        return 'Instrument cherché';
      }

      if (type === TYPES_ANNOUNCE_BAND) {
        return 'Instrument joué';
      }
    }
  },
  methods: {
    openSendMessageModal(user) {
      this.$emit('open-message-modal', user);
    }
  }
}
</script>