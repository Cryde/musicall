<template>
    <div>
        <b-card
                v-if="featured1"
                overlay
                class="d-lg-block d-md-block d-none"
                :img-src="featured1.cover"
                :img-alt="featured1.title"
                tag="b-link"
                :to="{name:'publication_show', params: {slug: featured1.publication.slug}}"
                :text-variant="featured1 ? getColor(featured1): 'dark'"
                :body-text-variant="featured1 ? getColor(featured1): 'dark'"
                :title="featured1.title"
        >
            <b-card-text v-if="featured1.description">
                {{ featured1.description }}
            </b-card-text>
        </b-card>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';

  export default {
    computed: {
      ...mapGetters('featured', ['featured1'])
    },
    methods: {
      getColor(featured) {
        return featured.options.color === 'dark' ? 'dark' : 'white';
      },
    }
  }
</script>