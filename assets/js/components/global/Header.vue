<template>
  <b-navbar fixed-top shadow centered spaced wrapper-class="container">
    <template #brand class="mr-3">
      <b-navbar-item tag="router-link" :to="{name: 'home'}">
        <span class="nav-logo"><img :src="logoPath" alt="Logo MusicAll"/></span>
      </b-navbar-item>
    </template>

    <template #start>

      <b-navbar-item :to="{ name: 'home' }" tag="router-link" exact-active-class="is-active">
        Home
      </b-navbar-item>
      <b-navbar-dropdown :to="{ name: 'publication' }" exact-active-class="is-active" tag="router-link" label="Publications" hoverable>

        <b-navbar-item v-if="isLoading"><spinner size="sm"/></b-navbar-item>
        <b-navbar-item v-else
                       v-for="category in publicationCategories"
                       :to="{name: 'publications_by_category', params: { slug: category.slug}}"
                       :key="category.id" tag="router-link" exact-active-class="is-active"
        >
          {{ category.title }}
        </b-navbar-item>
      </b-navbar-dropdown>

      <b-navbar-dropdown :to="{ name: 'course_index' }" tag="router-link" label="Cours" hoverable>

        <b-navbar-item v-if="isLoading"><spinner size="sm"/></b-navbar-item>
        <b-navbar-item v-else
                       v-for="category in courseCategories"
                       :to="{name: 'course_by_category', params: { slug: category.slug}}"
                       :key="category.id" tag="router-link"
        >
          {{ category.title }}
        </b-navbar-item>
      </b-navbar-dropdown>

      <b-navbar-item :to="{ name: 'search_index' }" tag="router-link">
        Recherche
      </b-navbar-item>

      <b-navbar-item :to="{ name: 'forum_index' }" tag="router-link">
        Forum
      </b-navbar-item>
    </template>

    <template slot="end">
        <b-autocomplete
            :data="results"
            rounded
            class=""
            clearable
            clear-on-select
            placeholder="Rechercher..."
            field="title"
            :loading="isLoadingSearch"
            icon="search"
            @typing="search"
            @select="go"
        >
          <template slot="empty"><span v-if="!isLoadingSearch">Il n'y a pas de résultats</span></template>
          <template slot-scope="props">
            <div class="media">
              <div class="media-content">
                {{ props.option.title }}
                <br>
                <small>
                  {{ props.option.publication_datetime | relativeDate }}
                  <publication-type :type="props.option.type" class="ml-3 is-inline-block"/>
                </small>
              </div>
            </div>
          </template>
        </b-autocomplete>



      <b-navbar-item tag="div" v-if="isAuthenticated">
        <div class="buttons">
          <b-button size="is-light" :to="{ name: 'admin_dashboard' }" tag="router-link"
                    v-if="isRoleAdmin"
                    icon-left="bolt"><span class="badge is-warning" v-if="adminCount">{{ adminCount }}</span></b-button>

          <b-button size="is-light" :to="{ name: 'message_list' }" tag="router-link"
                    icon-left="envelope">
            message
            <span class="badge is-warning" v-if="messageCount">{{ messageCount }}</span>
          </b-button>
        </div>
      </b-navbar-item>

      <Dropdown v-if="isAuthenticated"/>
      <b-navbar-item tag="div" v-else>
        <div class="buttons">
          <router-link class="button is-info" :to="{ name: 'user_registration' }">
            <strong>s'inscrire</strong>
          </router-link>
          <router-link class="button is-info is-light" :to="{ name: 'login' }">
            se connecter
          </router-link>
        </div>
      </b-navbar-item>
    </template>
  </b-navbar>
</template>

<script>
import {mapGetters} from 'vuex';
import Dropdown from './Dropdown';
import PublicationType from "../publication/PublicationType";
import searchApi from "../../api/search";
import {EVENT_TOGGLE_MENU} from '../../constants/events';
import {debounce} from 'lodash';
import logoPath from '../../../images/logo.png';

export default {
  components: {Dropdown, PublicationType},
  data() {
    return {
      term: '',
      results: [],
      searched: false,
      isLoadingSearch: false,
      logoPath
    }
  },
  computed: {
    ...mapGetters('publicationCategory', [
      'isLoading',
      'publicationCategories',
      'courseCategories'
    ]),
    ...mapGetters('security', [
      'isAuthenticated',
      'isLoading',
      'isRoleAdmin'
    ]),
    ...mapGetters('notifications', ['messageCount', 'pendingGalleriesCount', 'pendingPublicationsCount']),
    adminCount() {
      return this.pendingGalleriesCount + this.pendingPublicationsCount;
    },
  },
  methods: {
    hide() {
      this.searched = false;
    },
    search: debounce(async function (value) {
      this.isLoadingSearch = true;
      this.results = [];
      if (!value.trim().length) {
        this.searched = false;
      } else {
        this.searched = false;
        this.results = await searchApi.searchByTerm(value);
        this.searched = true;
      }
      this.isLoadingSearch = false;
    }, 500),
    go(result) {
      if (!result) {
        return;
      }
      this.searched = false;
      const routeName = result.category_type === 'publication' ? 'publication_show' : 'course_show';
      this.$router.replace({name: routeName, params: {slug: result.slug}});
      this.results = [];
    },
    toggleMenu() {
      this.$root.$emit(EVENT_TOGGLE_MENU);
    }
  }
}
</script>