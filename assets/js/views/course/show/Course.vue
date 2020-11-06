<template>
  <div>
    <div v-if="isLoading" class="has-text-centered pt-5">
      <spinner/>
    </div>
    <div v-else>
      <h1 class="subtitle is-3 is-uppercase">{{ publication.title }}</h1>

      <div class="author">Rédigé par <strong>{{ publication.author.username }}</strong> le {{
          publication.publication_datetime | dateFormat
        }}
      </div>
      <div class="box content is-shadowless p-3 p-lg-3 mt-lg-4 mt-3 publication-container" v-html="publication.content"></div>
    </div>
    <comment v-if="!isLoading && publication.thread.id" :thread-id="publication.thread.id"/>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import {format, parseISO} from 'date-fns';
import Comment from "../../comment/Thread";
import Spinner from "../../../components/global/misc/Spinner";

export default {
  components: {Spinner, Comment},
  metaInfo() {
    return {
      title: this.publication.title,
      meta: [
        {vmid: 'description', name: 'description', content: this.publication.description},
        {property: "og:title", content: this.publication.title},
        {property: "og:description", content: this.publication.description},
      ]
    }
  },
  async created() {
    await this.$store.dispatch('publication/getPublication', {slug: this.$route.params.slug});
  },
  computed: {
    ...mapGetters('publication', [
      'isLoading',
      'publication',
      'hasError'
    ])
  },
  filters: {
    dateFormat(date) {
      return format(parseISO(date), 'dd/MM/yyyy HH:mm');
    }
  }
}
</script>

<style scoped>
.author {
  font-size: 0.8em;
}
</style>