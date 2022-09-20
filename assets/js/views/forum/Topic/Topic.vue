<template>
  <b-loading v-if="isLoading" active/>
  <div v-else>

    <breadcrumb
        :root="{to: {name: 'home'}, label: 'Home'}"
        :level1="{to: {name: 'forum_index'}, label: 'Forum'}"
        :level2="{to: {name: 'forum_topic_list', params: {slug: topic.forum.slug}}, label: topic.forum.title}"
        :current="{label: topic.title}"
    />

    <h1 class="subtitle is-3">{{ topic.title }}</h1>

    <topic-post v-for="post in posts"
                :post="post"
                :key="post.id"
    />

    <b-pagination
        v-if="displayPagination"
        :total="totalItems"
        v-model="current"
        range-before="3"
        range-after="1"
        :per-page="maxItemsPerPage"
        aria-next-label="Page suivante"
        aria-previous-label="Page précédente"
        aria-page-label="Page"
        aria-current-label="Page courante"
    >
      <template #default="props">
        <b-pagination-button
            :page="props.page"
            :id="`page${props.page.number}`"
            tag="router-link"
            :to="props.page.number === 1 ? '?' : `?page=${props.page.number}`">
          {{ props.page.number }}
        </b-pagination-button>
      </template>
      <template #previous="props">
        <b-pagination-button
            :page="props.page"
            tag="router-link"
            :to="props.page.number <= 1 ? '?' : `?page=${props.page.number}`">
          <b-icon icon="angle-left"/>
        </b-pagination-button>
      </template>

      <template #next="props">
        <b-pagination-button
            :page="props.page"
            tag="router-link"
            :to="`?page=${props.page.number}`">
          <b-icon icon="angle-right"/>
        </b-pagination-button>
      </template>
    </b-pagination>
  </div>
</template>

<script>
import forum from "../../../api/forum/forum";
import Breadcrumb from "../../../components/global/Breadcrumb";
import TopicPost from "./TopicPost";
import {FORUM_MAX_TOPIC_POST_PER_PAGE} from "../../../constants/forum";

export default {
  components: {TopicPost, Breadcrumb},
  data() {
    return {
      isLoading: true,
      topic: null,
      posts: [],
      current: 1,
      totalItems: 0,
    }
  },
  computed: {
    displayPagination() {
      return this.totalItems > FORUM_MAX_TOPIC_POST_PER_PAGE;
    },
    maxItemsPerPage() {
      return FORUM_MAX_TOPIC_POST_PER_PAGE;
    }
  },
  metaInfo() {
    return {
      title: this.pageTitle(),
    }
  },
  watch: {
    $route() {
      this.current = this.$route.query.page ? +this.$route.query.page : 1;
      this.fetchData();
    },
  },
  async created() {
    this.isLoading = true;
    this.current = this.$route.query.page ? +this.$route.query.page : 1;
    const slug = this.$route.params.slug;
    this.topic = await forum.getTopic(slug);
    await this.fetchData();
    this.isLoading = false;
  },
  methods: {
    pageTitle() {
      if (this.topic) {
        return this.topic.title;
      }

      return 'Toutes les publications relative à la musique | MusicAll'
    },
    async fetchData() {
      const metaPosts = await forum.getPostsByTopic({
        topic: this.topic.id,
        order: {'creationDatetime': 'asc'},
        page: this.current
      });
      this.posts = metaPosts['hydra:member']
      this.totalItems = metaPosts['hydra:totalItems']
    },
  }
}
</script>