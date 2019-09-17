<template>
    <div>
        <b-card-group columns>
            <b-card v-for="publication in publications" key="publication.slug" tag="b-link" :to="{ name: 'publication_show', params: { slug: publication.slug }}" :title="publication.title" :img-src="publication.cover_image" img-alt="Image" img-top>
                <b-card-text>
                    {{ publication.description }}
                </b-card-text>
            </b-card>
        </b-card-group>
    </div>
</template>

<script>
    import {mapGetters} from 'vuex';

    export default {
      computed: {
        ...mapGetters('publications', [
            'publications'
        ])
      },
      async created() {
        await this.$store.dispatch('publications/getPublications');
      }
    }
</script>