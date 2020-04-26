<template>
    <footer id="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-2 mt-3 mt-lg-0">
                    <h3>
                        <router-link :to="{ name: 'home' }"><i class="fas fa-home fa-fw"></i> MusicAll</router-link>
                    </h3>
                </div>
                <div class="col-lg-2 mt-3 mt-lg-0">
                    <h3>
                        <router-link :to="{ name: 'publication' }"><i class="fas fa-newspaper fa-fw"></i> Publications
                        </router-link>
                    </h3>
                    <ul>
                        <li v-if="isLoading">
                            <b-spinner small type="grow"></b-spinner>
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
                <div class="col-lg-2 mt-3 mt-lg-0">
                    <h3><a href=""><i class="fas fa-graduation-cap fa-fw"></i> Cours</a></h3>
                    <ul>
                        <li><a href="/cours/guitare">Guitare</a></li>
                        <li><a href="/cours/basse">Basse</a></li>
                        <li><a href="/cours/mao">MAO</a></li>
                        <li><a href="/cours/batterie">Batterie</a></li>
                        <li><a href="/cours/divers">Divers</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 mt-3 mt-lg-0">
                    <h3><a href=""><i class="far fa-comments fa-fw"></i> Forum</a></h3>
                </div>
                <div class="col-lg-4 mt-3 mt-lg-0 social">
                    <h3><i class="far fa-thumbs-up"></i> Nous suivre</h3>
                    <a class="facebook" href="https://www.facebook.com/Music.All" title="Suivez nous sur facebook"
                       target="_blank">

                        <i class="fab fa-facebook-f fa-2x"></i></a>
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
            <div class="row">
                <div class="col-12 mt-5 mt-lg-0 text-center text-lg-right">
                    <i class="fa fa-copyright"></i> 2008-{{ new Date().getFullYear() }} MusicAll
                </div>
            </div>
        </div>
    </footer>
</template>

<script>
  import {mapGetters} from 'vuex';

  export default {
    computed: {
      ...mapGetters('publicationCategory', [
        'isLoading',
        'publicationCategories'
      ])
    }
  }
</script>