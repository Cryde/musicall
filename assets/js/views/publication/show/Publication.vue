<template>
  <div>
    <b-loading v-if="isLoading" active/>
    <div v-else-if="publication.type_label === 'text'">

      <breadcrumb
          :root="{to: {name: 'home'}, label: 'Home'}"
          :level1="{to: {name: 'publication'}, label: 'Publications'}"
          :level2="{to: {name: 'publications_by_category', params:{slug: publication.sub_category.slug}}, label: publication.sub_category.title}"
          :current="{label: publication.title}"
      />

      <h1 class="subtitle is-3 is-uppercase">{{ publication.title }}</h1>

      <div class="author">Rédigé par <strong>{{ publication.author.username }}</strong> <span
          v-if="publication.publication_datetime">le {{
          publication.publication_datetime | dateFormat
        }}</span>
      </div>
      <div class="box content is-shadowless p-3 p-lg-3 mt-lg-4 mt-3 publication-container"
           v-html="publication.content"></div>
    </div>
    <div v-else-if="publication.type_label === 'video'">

      <breadcrumb
          :root="{to: {name: 'home'}, label: 'Home'}"
          :level1="{to: {name: 'publication'}, label: 'Publications'}"
          :level2="{to: {name: 'publications_by_category', params:{slug: publication.sub_category.slug}}, label: publication.sub_category.title}"
          :current="{label: publication.title}"
      />


      <h1 class="subtitle is-3 is-uppercase">{{ publication.title }}</h1>
      <div class="author">Publié par <strong>{{ publication.author.username }}</strong> le {{
          publication.publication_datetime | dateFormat
        }}
      </div>
      <figure class="image is-16by9 mt-4">
        <iframe class="has-ratio" width="640" height="360"
                :src="`https://www.youtube.com/embed/${publication.content}?showinfo=0`" frameborder="0"
                allowfullscreen></iframe>
      </figure>
    </div>
    <comment v-if="!isLoading && !hasError && publication.thread && publication.thread.id" :thread-id="publication.thread.id"/>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import {format, parseISO} from 'date-fns';
import Comment from "../../comment/Thread.vue";
import Breadcrumb from "../../../components/global/Breadcrumb.vue";

export default {
  components: {Breadcrumb, Comment},
  metaInfo() {
    return {
      title: this.publication.title,
      meta: [
        {vmid: 'description', name: 'description', content: this.publication.description}
      ]
    }
  },
  watch: {
    '$route.params.slug': function (slug) {
      this.load(slug);
    }
  },
  async created() {
    await this.load(this.$route.params.slug);
  },
  computed: {
    ...mapGetters('publication', [
      'isLoading',
      'publication',
      'hasError',
      'error'
    ])
  },
  methods: {
    async load(slug) {
      await this.$store.dispatch('publication/getPublication', {slug});
      if (this.hasError) {
        this.$buefy.toast.open({
          message: this.error,
          type: 'is-danger',
          position: 'is-bottom-left',
          duration: 5000
        });
        this.$router.replace({name: 'home'});
      }
    }
  },
  beforeDestroy() {
    this.$store.dispatch('publication/reset');
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