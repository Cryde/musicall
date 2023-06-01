<template>
  <div v-if="isLoading">
    <item-last-announce-skel v-for="i in 10" :key="i"/>
  </div>
  <div v-else>
    <item-last-announce
        v-for="(announce,i) in lastAnnounces"
        :key="i"
        :location-name="announce.location_name"
        :styles="announce.styles"
        :instrument-name="announce.instrument.musician_name"
        :author-username="announce.author.username"
        :type="announce.type"
        class="mb-2"
    />
  </div>
</template>

<script>
import {mapGetters} from "vuex";
import ItemLastAnnounce from "./ItemLastAnnounce.vue";
import ItemLastAnnounceSkel from "./ItemLastAnnounceSkel.vue";
import {EVENT_ANNOUNCE_MUSICIAN_CREATED} from "../../constants/events";

export default {
  components: {ItemLastAnnounceSkel, ItemLastAnnounce},
  computed: {
    ...mapGetters('security', ['isAuthenticated']),
    ...mapGetters('lastAnnounceMusician', ['isLoading', 'lastAnnounces']),
  },
  created() {
    this.$store.dispatch('lastAnnounceMusician/loadLastAnnounces');

    this.$root.$on(EVENT_ANNOUNCE_MUSICIAN_CREATED, () => {
      this.$store.dispatch('lastAnnounceMusician/loadLastAnnounces');
    });
  }
}
</script>