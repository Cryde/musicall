<template>
  <div>
    <template v-if="isLoading">
      <b-skeleton height="320px" class="mb-4"></b-skeleton>
    </template>
    <featured-list v-else/>
    <div class="columns" :class="{'mt-5': hasFeatured || isLoading}">
      <div class="column is-8">
        <div class="columns mb-0">
          <div class="column is-12 pb-0">
            <h2 class="subtitle is-4">
              Dernières publications
            </h2>
          </div>
        </div>

        <div class="has-text-right mt-2 mb-2">
          <b-tooltip label="Ajouter une vidéo YouTube 'découverte'" type="is-dark">
            <b-button size="is-small" rounded
                      icon-left="youtube" icon-pack="fab"
                      @click="openAddVideoModal()">
              Ajouter un découverte
            </b-button>
          </b-tooltip>

          <b-tooltip label="Ajouter une publication" type="is-dark">
            <b-button size="is-small" rounded icon-left="pen" icon-pack="fas"
                      @click="openAddPublicationModal()">
              Ajouter un publication
            </b-button>
          </b-tooltip>
        </div>

        <publication-list/>
      </div>
      <div class="column is-4">
        <div class="columns mb-0">
          <div class="column is-12 pb-0">
            <h2 class="subtitle is-4">
              Dernières annonces
              <b-tooltip label="Dernières announces de musiciens ou groupes" class="is-size-7" type="is-info">
                <b-icon icon="info-circle" size="is-small"/>
              </b-tooltip>
            </h2>
          </div>
        </div>

        <div class="columns mb-0">
          <div class="column is-12 pb-0">
            <div class="has-text-right mt-2 mb-2">
              <b-tooltip label="Chercher un musicien ou un groupe pour votre projet" type="is-dark">
                <b-button size="is-small" rounded
                    icon-left="search" icon-pack="fas"
                    tag="router-link" :to="{name: 'search_index'}">
                  Chercher
                </b-button>
              </b-tooltip>

              <b-tooltip label="Ajouter une annonce de recherche de musicien ou groupe" type="is-dark">
                <b-button size="is-small" rounded
                    icon-left="bullhorn" icon-pack="fas"
                    @click="openAddAnnounceModal()">
                  Poster une annonce
                </b-button>
              </b-tooltip>
            </div>
            <last-announce-list/>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import {mapGetters} from 'vuex';
import FeaturedList from "./home/FeaturedList";
import PublicationList from "./publication/list/List.vue";
import LastAnnounceList from "./announce/LastAnnounceList.vue";
import AddMusicianAnnounceForm from "./user/Announce/modal/AddMusicianAnnounceForm.vue";
import AddVideoForm from "./user/Publication/add/video/AddVideoForm.vue";
import RegisterOrLoginModal from "../components/global/content/RegisterOrLoginModal.vue";

export default {
  components: {LastAnnounceList, PublicationList, FeaturedList},
  computed: {
    ...mapGetters('security', ['isAuthenticated']),
    ...mapGetters('featured', ['hasFeatured', 'isLoading'])
  },
  metaInfo() {
    return {
      title: 'MusicAll, le site de référence au service de la musique'
    }
  },
  created() {
    this.$store.dispatch('featured/loadFeatured');
  },
  methods: {
    openRegisterOrLoginModal(message) {
      this.$buefy.modal.open({
        parent: this,
        component: RegisterOrLoginModal,
        props: {message},
        hasModalCard: true,
        trapFocus: true
      })
    },
    openAddPublicationModal() {
      if (!this.isAuthenticated) {
        this.openRegisterOrLoginModal(`
        Si vous souhaitez ajouter une publication, vous devez vous connecter.<br/>
        Si vous ne disposez pas de compte, vous pouvez vous inscrire gratuitement sur le site.`);
        return;
      }

      this.$router.push({name: 'user_publications'})
    },
    openAddVideoModal() {
      if (!this.isAuthenticated) {
        this.openRegisterOrLoginModal(`
        Si vous souhaitez partager une vidéo avec la communauté, vous devez vous connecter.<br/>
        Si vous ne disposez pas de compte, vous pouvez vous inscrire gratuitement sur le site.`);
        return;
      }
      this.$buefy.modal.open({
        parent: this,
        component: AddVideoForm,
        hasModalCard: true,
        trapFocus: true
      })
    },
    openAddAnnounceModal() {
      if (!this.isAuthenticated) {
        this.openRegisterOrLoginModal(`
        Si vous souhaitez ajouter une annonce pour trouver un musicien ou un groupe, vous devez vous connecter.<br/>
        Si vous ne disposez pas de compte, vous pouvez vous inscrire gratuitement sur le site.`);
        return;
      }
      this.$buefy.modal.open({
        parent: this,
        component: AddMusicianAnnounceForm,
        props: {isFromAnnounce: false},
        hasModalCard: true,
        trapFocus: true
      })
    }
  }
}
</script>
