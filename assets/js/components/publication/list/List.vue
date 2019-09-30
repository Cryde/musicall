<template>
    <div v-if="isLoading" class="text-center">
        <b-spinner variant="primary"></b-spinner>
    </div>
    <div v-else id="publication-list">
        <b-card-group columns>
            <div v-for="publication in publications" :key="publication.id">
                <b-card v-if="publication.category !== 'news'" tag="b-link"
                        :to="{ name: 'publication_show', params: { slug: publication.slug }}" :title="publication.title"
                        :img-src="publication.cover_image" img-alt="Image" img-top>
                    <b-card-text v-if="publication.type === 'text'">
                        {{ publication.description }}
                    </b-card-text>
                    <div v-if="publication.type === 'video'" class="text-center video-tag"><i
                            class="fab fa-youtube"></i></div>
                </b-card>
                <b-card v-else>
                    <b-card-title>{{ publication.title }}</b-card-title>
                    <b-card-text>
                        {{ publication.description }}
                    </b-card-text>
                </b-card>
            </div>
        </b-card-group>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';

  export default {
    metaInfo: {
      title: 'Publications',
    },
    computed: {
      ...mapGetters('publications', [
        'publications',
        'isLoading'
      ])
    },
    async created() {
      await this.$store.dispatch('publications/getPublications');
    }
  }
</script>