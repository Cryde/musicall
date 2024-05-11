<template>
  <footer class="footer is-dark">
    <div class="container">
      <div class="columns">
        <div class="column">
          <h3>
            <router-link :to="{ name: 'home' }"><i class="fas fa-home fa-fw"></i> MusicAll
            </router-link>
          </h3>
        </div>
        <div class="column">
          <h3>
            <router-link :to="{ name: 'publication' }"><i class="fas fa-newspaper fa-fw"></i>
              Publications
            </router-link>
          </h3>
          <ul>
            <li v-if="isLoading">
              <spinner/>
            </li>
            <li v-else v-for="category in publicationCategories">
              <router-link :to="{name: 'publications_by_category', params: { slug: category.slug}}">
                {{ category.title }}
              </router-link>
            </li>
            <li v-if="!isLoading">
              <router-link :to="{name: 'gallery_list'}">Photos</router-link>
            </li>
          </ul>
        </div>
        <div class="column">
          <h3>
            <router-link :to="{ name: 'course_index' }"><i
                class="fas fa-graduation-cap fa-fw"></i> Cours
            </router-link>
          </h3>
          <ul>
            <li v-if="isLoading">
              <spinner/>
            </li>
            <li v-else v-for="category in courseCategories">
              <router-link :to="{name: 'course_by_category', params: { slug: category.slug}}">
                {{ category.title }}
              </router-link>
            </li>
          </ul>
        </div>
        <div class="column">
          <h3>
            <router-link :to="{ name: 'search_index' }"><i class="fas fa-stream fa-fw"></i>
              Recherche
            </router-link>
          </h3>
        </div>
        <div class="column">
          <h3>
            <router-link :to="{ name: 'forum_index' }">
              <i class="far fa-comments fa-fw"></i> Forum
            </router-link>
          </h3>
        </div>
        <div class="col-lg-4 mt-3 mt-lg-0">
          <h3><i class="far fa-thumbs-up"></i> Contact</h3>
          <router-link :to="{name: 'contact'}" class="is-block mb-3 mt-3">Nous contacter</router-link>
          <div class="social">
            <a class="facebook" href="https://www.facebook.com/Music.All" title="Suivez nous sur facebook"
               target="_blank">
              <i class="fab fa-facebook-f fa-2x"></i>
            </a>
            <a class="twitter" href="https://twitter.com/musicall" title="Suivez nous sur Twitter"
               target="_blank"><i
                class="fab fa-twitter fa-2x"></i></a>
            <a class="instagram" href="https://www.instagram.com/music.all.official"
               title="Suivez nous sur Instagram" target="_blank"><i class="fab fa-instagram fa-2x"></i></a>
            <a class="youtube" href="https://www.youtube.com/c/MusicAllCommunity"
               title="Suivez nous sur YouTube"
               target="_blank"><i class="fab fa-youtube fa-2x"></i></a>
          </div>
        </div>
      </div>
      <div class="columns">
        <div class="column is-12 mt-5 mt-lg-0 has-text-centered text-lg-right">
          <i class="fa fa-copyright"></i> 2008-{{ new Date().getFullYear() }} MusicAll
        </div>
      </div>
    </div>
  </footer>
</template>

<script>
import {mapGetters} from 'vuex';
import Spinner from "./misc/Spinner.vue";

export default {
  components: {Spinner},
  computed: {
    ...mapGetters('publicationCategory', [
      'isLoading',
      'publicationCategories',
      'courseCategories'
    ])
  }
}
</script>