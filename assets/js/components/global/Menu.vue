<template>
    <nav id="menu">
        <ul>

            <li>
                <router-link :to="{ name: 'home' }"><i class="fas fa-home fa-fw"></i> Home</router-link>
            </li>
            <li>
                <router-link :to="{ name: 'publication' }"><i class="fas fa-newspaper fa-fw"></i> Publications</router-link>
                <ul>
                    <li v-if="isLoading">
                        <b-spinner small type="grow"></b-spinner>
                    </li>
                    <li v-else v-for="category in categories">
                        <router-link :to="{name: 'publications_by_category', params: { slug: category.slug}}">
                            {{ category.title }}
                        </router-link>
                    </li>
                    <li v-if="!isLoading">
                        <router-link :to="{name: 'gallery_list'}">Galeries</router-link>
                    </li>
                </ul>
            </li>

            <li>
                <a href=""><i class="fas fa-graduation-cap fa-fw"></i> Cours</a>
                <ul>
                    <li><a href="/cours/guitare">Guitare</a></li>
                    <li><a href="/cours/basse">Basse</a></li>
                    <li><a href="/cours/mao">MAO</a></li>
                    <li><a href="/cours/batterie">Batterie</a></li>
                    <li><a href="/cours/inclassable">Inclassable</a></li>
                </ul>
            </li>
            <li>
                <a href=""><i class="fas fa-stream fa-fw"></i> Annuaire</a>
            </li>
            <li>
                <a href=""><i class="far fa-comments fa-fw"></i> Forum</a>
            </li>

            <li>
                <router-link :to="{ name: 'todo' }"><i class="fas fa-clipboard-check fa-fw"></i> Todo</router-link>
            </li>
        </ul>
    </nav>
</template>

<script>
  import {mapGetters} from 'vuex';

  export default {
    computed: {
      ...mapGetters('publicationCategory', [
        'isLoading',
        'categories'
      ])
    }
  }
</script>