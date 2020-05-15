<template>
    <b-navbar toggleable="sm" fixed="top" type="light" variant="light">
        <b-container>
            <b-navbar-brand :to="{name: 'home'}"><img src="/build/images/logo.png" alt="Logo MusicAll"/>
            </b-navbar-brand>

            <b-navbar-nav class="ml-auto mr-3 position-relative" v-click-outside="hide">
                <b-nav-form class="d-none d-md-block">

                    <i class="fas fa-search mr-3" v-if="!isLoadingSearch"></i>
                    <b-spinner v-else small class="mr-3"></b-spinner>
                    <b-form-input class="mr-sm-2" placeholder="Recherche" debounce="1000" @update="search"
                                  @focus="search"
                                  v-model="term"></b-form-input>

                </b-nav-form>
                <div class="results p-3 shadow" v-if="searched">
                    <i class="fas fa-times float-right" @click="searched = false"></i>
                    <h4 v-if="results.length">Il y a {{ results.length }} résultat(s) :</h4>
                    <span v-else>Il n'y a pas de résultat</span>
                    <div v-for="result in results"
                                 @click="go(result)"
                    >
                        {{ result.title }}<br/>
                        <em class="mr-2">{{ result.publication_datetime | relativeDate }}</em> <publication-type :type="result.type"/>
                    </div>
                </div>
            </b-navbar-nav>

            <div v-if="isLoading">
                <b-spinner small type="grow"></b-spinner>
            </div>
            <div v-else>
                <Dropdown v-if="isAuthenticated"/>
                <div v-else>
                    <router-link :to="{ name: 'user_registration' }" class="ml-auto btn btn-registration">
                        s'inscrire
                    </router-link>
                    <router-link :to="{ name: 'login' }" class="ml-2 btn btn-login">
                        se connecter
                    </router-link>
                </div>
            </div>

            <b-navbar-toggle target="nav-text-collapse"></b-navbar-toggle>
        </b-container>

    </b-navbar>
</template>

<script>
  import {mapGetters} from 'vuex';
  import Dropdown from './Dropdown';
  import PublicationType from "../publication/PublicationType";
  import searchApi from "../../api/search";

  export default {
    components: {Dropdown, PublicationType},
    data() {
      return {
        term: '',
        results: [],
        searched: false,
        isLoadingSearch: false
      }
    },
    computed: {
      ...mapGetters('security', [
        'isAuthenticated',
        'isLoading'
      ])
    },
    methods: {
      hide() {
        this.searched = false;
      },
      async search() {
        if (!this.term.trim().length) {
          this.searched = false;
        } else {
          this.isLoadingSearch = true;
          this.searched = false;
          this.results = await searchApi.searchByTerm(this.term);
          this.searched = true;
          this.isLoadingSearch = false;
        }
      },
      go(result) {
        this.searched = false;
        const routeName = result.category_type === 'publication' ? 'publication_show' : 'course_show';
        this.$router.replace({name: routeName, params: {slug: result.slug}});
      }
    }
  }
</script>