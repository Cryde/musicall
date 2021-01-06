<template>
  <b-navbar fixed-top shadow centered spaced wrapper-class="container">
    <template slot="brand" class="mr-3">
      <b-navbar-item tag="router-link" :to="{name: 'home'}">
        <span class="nav-logo"><img src="/build/images/logo.png" alt="Logo MusicAll"/></span>
      </b-navbar-item>
    </template>

    <template slot="start">

      <div class="search-bar">
        <b-autocomplete
            :data="results"
            rounded
            class="is-block ml-4 is-align-items-self-end  "
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
      </div>

    </template>

    <template slot="end">

      <b-navbar-item tag="div" v-if="isAuthenticated">
        <div class="buttons">
          <b-button size="is-light" :to="{ name: 'admin_dashboard' }" tag="router-link"
                    v-if="isRoleAdmin"
                    icon-left="bolt">
            admin
            <span class="badge is-warning" v-if="adminCount">{{ adminCount }}</span>
          </b-button>

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