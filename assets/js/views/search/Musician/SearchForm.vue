<template>
    <div>

        <breadcrumb
                :root="{to: {name: 'home'}, label: 'Home'}"
                :level1="{to: {name: 'search_index'}, label: 'Recherche'}"
                :current="{label: 'Rechercher un musicien'}"
        />

        <b-button
                @click="openAddMusicianAnnounce()"
                icon-left="bullhorn"
                type="is-info"
                v-if="isAuthenticated"
                class="is-pulled-right">
            Poster une annonce
        </b-button>
        <b-tooltip v-else class="is-pulled-right" type="is-black"
                   label="Vous devez être connecté pour poster une annonce">
            <b-button type="is-info">
                <i class="fas fa-bullhorn"></i> Poster une annonce
            </b-button>
        </b-tooltip>

        <h1 class="subtitle is-3">Rechercher un musicien ou un groupe</h1>

        <div class="columns mt-lg-5 pt-4">
            <div class="column is-7 is-12-mobile">

                <b-message v-if="errors.length" type="is-danger">
                    <span v-for="error in errors">{{ error }}</span>
                </b-message>

                <b-input
                        @input="changeSearch"
                        :value="search"
                        maxlength="200"
                        type="textarea"
                        placeholder="Taper votre recherche ici, exemple: Je recherche un guitariste qui joue du rock"
                        size="is-large"
                />
                <b-button type="is-info" class="mt-5 is-pulled-right" :disabled="!canSearch" icon-left="search"
                          :loading="isSearching" label="Rechercher"
                          @click="send"/>
            </div>
            <div class="column is-5 is-12-mobile">
                Voici une liste d'exemple que vous pouvez utiliser.
                <article class="message mb-1 mt-4 is-clickable" @click="applyExampleToForm($event)">
                    <div class="message-body p-3">Je cherche un groupe de pop et rock qui a besoin d'un batteur</div>
                </article>
                <article class="message mb-1 is-clickable" @click="applyExampleToForm($event)">
                    <div class="message-body p-3">Je recherche un guitariste pour mon groupe de funk</div>
                </article>
                <article class="message mb-1 is-clickable" @click="applyExampleToForm($event)">
                    <div class="message-body p-3">Je recherche un chanteur pour mon groupe de stoner et métal</div>
                </article>
            </div>
        </div>

        <results :is-searching="isSearching" :is-success="isSuccess" :results="results"/>
    </div>
</template>
<script>
import {mapGetters} from 'vuex';
import Results from './Results.vue';
import Spinner from "../../../components/global/misc/Spinner.vue";
import Breadcrumb from "../../../components/global/Breadcrumb.vue";
import AddMusicianAnnounceForm from "../../user/Announce/modal/AddMusicianAnnounceForm.vue";

export default {
  components: {Breadcrumb, Spinner, Results},
  metaInfo() {
    return {
      title: 'Rechercher un musicien ou un groupe - MusicAll',
    }
  },
  computed: {
    ...mapGetters('security', ['isAuthenticated']),
    ...mapGetters('searchMusicianText', ['search', 'errors', 'isSearching', 'isSuccess', 'results']),
    canSearch() {
      return this.search.trim().length > 10;
    }
  },
  methods: {
    async send() {
      await this.$store.dispatch('searchMusicianText/search');
    },
    openAddMusicianAnnounce() {
      this.$buefy.modal.open({
        parent: this,
        component: AddMusicianAnnounceForm,
        hasModalCard: true,
        trapFocus: true
      })
    },
    applyExampleToForm(event) {
      this.$store.dispatch('searchMusicianText/updateSearch', event.target.innerText);
    },
    changeSearch(value) {
      this.$store.dispatch('searchMusicianText/updateSearch', value);
    }
  }
}
</script>