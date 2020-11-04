<template>
    <div v-if="isLoading" class="has-text-centered pt-5">
        <b-spinner variant="primary" label="Spinning"></b-spinner>
    </div>
    <b-row v-else>
        <b-col class="pb-5">
            <b-row>
                <b-col><h1>{{ artist.name }}</h1></b-col>
            </b-row>
            <b-row v-if="artist.cover">
                <b-col><b-img fluid :src="artist.cover"/></b-col>
            </b-row>

            <b-row>
                <b-col :cols="8" v-if="artist.biography" class="mt-3">
                    <h2>Biography</h2>
                    <p v-html="artist.biography"></p>
                </b-col>
                <b-col :cols="4" v-if="artist.members" class="mt-3">
                    <h2>Membres</h2>
                    <p v-html="artist.members"></p>
                </b-col>
            </b-row>

            <b-row>
                <b-col :cols="6" v-if="artist.label_name" class="mt-3">
                    <h2>Label</h2>
                    <p v-html="artist.label_name"></p>
                </b-col>
                <b-col :cols="6" v-if="artist.country_name" class="mt-3">
                    <h2>Pays d'orgine</h2>
                    <p v-html="artist.country_name"></p>
                </b-col>
            </b-row>
        </b-col>
    </b-row>
</template>

<script>
  import {mapGetters} from 'vuex';

  export default {
    metaInfo() {
      return {
        title: this.artist.name,
        meta: [{vmid: 'description', name: 'description', content: this.artist.biography}]
      }
    },
    computed: {
      ...mapGetters('artist', ['isLoading', 'artist']),
    },
    async mounted() {
      await this.$store.dispatch('artist/loadArtist', {slug: this.$route.params.slug});
    }
  }
</script>