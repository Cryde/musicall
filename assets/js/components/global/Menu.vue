<template>
    <nav id="menu">
        <ul>

            <li>
                <router-link :to="{ name: 'home' }">
                    <i class="fas fa-home fa-fw"></i> Home
                </router-link>
            </li>
            <li>
                <router-link :to="{ name: 'publication' }">
                    <i class="fas fa-newspaper fa-fw"></i> Publications
                </router-link>
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
            </li>

            <li>
                <router-link :to="{ name: 'course_index' }">
                    <i class="fas fa-graduation-cap fa-fw"></i> Cours
                </router-link>
                <ul>
                    <li v-if="isLoading">
                        <b-spinner small type="grow"></b-spinner>
                    </li>
                    <li v-else v-for="category in courseCategories">
                        <router-link :to="{name: 'course_by_category', params: { slug: category.slug}}">
                            {{ category.title }}
                        </router-link>
                    </li>
                </ul>
            </li>

            <li>
                <router-link :to="{ name: 'search_index' }">
                    <i class="fas fa-stream fa-fw"></i> Recherche
                </router-link>
            </li>

            <li>
                <router-link :to="{ name: 'forum_index' }">
                    <i class="far fa-comments fa-fw"></i> Forum
                </router-link>
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
        'publicationCategories',
        'courseCategories'
      ])
    }
  }
</script>