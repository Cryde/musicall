<template>
    <nav id="menu">
        <ul>

            <li>
                <router-link :to="{ name: 'home' }">Home</router-link>
            </li>
            <li>
                <router-link :to="{ name: 'publication' }">Publications</router-link>
                <ul>
                    <li v-if="isLoading">
                        <b-spinner small type="grow"></b-spinner>
                    </li>
                    <li v-else v-for="category in categories">
                        <a href="">{{ category.title }}</a>
                    </li>
                </ul>
            </li>

            <li>
                <a href="">Cours</a>
                <ul>
                    <li><a href="/cours/guitare">Guitare</a></li>
                    <li><a href="/cours/basse">Basse</a></li>
                    <li><a href="/cours/mao">MAO</a></li>
                    <li><a href="/cours/batterie">Batterie</a></li>
                    <li><a href="/cours/inclassable">Inclassable</a></li>
                </ul>
            </li>
            <li>
                <a href="">Annuaire</a>
            </li>
            <li>
                <a href="">Forum</a>
            </li>

            <li>
                <router-link :to="{ name: 'todo' }">Todo</router-link>
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