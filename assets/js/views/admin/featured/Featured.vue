<template>
  <div>
    <breadcrumb
        :root="{to: {name: 'admin_dashboard'}, label: 'Admin'}"
        :current="{label: 'Publications mise en avant'}"
    />

    <h1 class="subtitle is-3">Publications mise en avant</h1>

    <b-loading v-if="isLoading" active/>
    <div v-else>
      <b-message v-if="errors.length" type="is-danger" class="mt-2 mb-2">
        <span v-for="error in errors" class="is-block">{{ error }}</span>
      </b-message>

      <featured-card :featured="featured1" @edit="onEdit" @add="onAdd" @cover="onCover" @error="onError"/>

      <add-featured-modal :level="currentLevel" ref="modal-featured-add"/>
      <edit-featured-modal v-if="currentFeatured" :featured="currentFeatured" ref="modal-featured-edit"/>
      <edit-cover-modal v-if="currentFeatured" :featured="currentFeatured" ref="modal-upload-featured-image"/>
    </div>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import AddFeaturedModal from "./modal/AddFeaturedModal";
import EditFeaturedModal from "./modal/EditFeaturedModal";
import EditCoverModal from "./modal/EditCoverModal";
import FeaturedCard from "./FeaturedCard";
import Breadcrumb from "../Breadcrumb";

export default {
  components: {Breadcrumb, FeaturedCard, EditCoverModal, EditFeaturedModal, AddFeaturedModal},
  data() {
    return {
      currentLevel: null,
      currentFeatured: null,
      errors: [],
    }
  },
  computed: {
    ...mapGetters('adminFeatured', ['isLoading', 'featured1'])
  },
  mounted() {
    this.$store.dispatch('adminFeatured/loadFeatured');
  },
  methods: {
    onEdit(featured) {
      this.currentFeatured = featured;
      this.$nextTick(() => {
        this.$refs['modal-featured-edit'].open();
      })
    },
    onAdd(level) {
      this.currentLevel = level;
      this.$nextTick(() => {
        this.$refs['modal-featured-add'].open();
      });
    },
    onCover(featured) {
      this.currentFeatured = featured;
      this.$nextTick(() => {
        this.$refs['modal-upload-featured-image'].open();
      });
    },
    onError(errors) {
      this.errors = errors;
    }
  }
}
</script>

<style>
.featured-bg {
  background-color: #d8d8d8 !important;
}


</style>