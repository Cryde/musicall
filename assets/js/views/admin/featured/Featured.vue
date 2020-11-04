<template>
    <div>
        <h1>
            <b-link :to="{name: 'admin_dashboard'}">Admin</b-link>
            / Publications mise en avant
        </h1>

        <div v-if="isLoading" class="has-text-centered pt-5">
            <b-spinner variant="primary" label="Loading"></b-spinner>
        </div>
        <div v-else>

            <b-alert v-show="errors.length" variant="danger" class="mt-2 mb-2" show>
                <span v-for="error in errors" class="d-block">{{ error }}</span>
            </b-alert>

            <featured-card :featured="featured1" @edit="onEdit" @add="onAdd" @cover="onCover" @error="onError"/>

            <!--
            <b-card-group deck class="mt-4">
                <b-card
                        img-src="https://picsum.photos/900/350/?image=6"
                        img-alt="Card Image"
                        title="Image Overlay"
                        class="text-uppercase"
                        style="margin-bottom: 0"
                >
                </b-card>

                <b-card
                        img-src="https://picsum.photos/900/350/?image=8"
                        img-alt="Card Image"
                        title="Image Overlay"
                        class="text-uppercase"
                >
                </b-card>
            </b-card-group>-->

            <add-featured-modal :level="currentLevel"/>
            <edit-featured-modal v-if="currentFeatured" :featured="currentFeatured"/>
            <edit-cover-modal v-if="currentFeatured" :featured="currentFeatured"/>
        </div>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';
  import AddFeaturedModal from "./AddFeaturedModal";
  import EditFeaturedModal from "./EditFeaturedModal";
  import EditCoverModal from "./EditCoverModal";
  import FeaturedCard from "./FeaturedCard";

  export default {
    components: {FeaturedCard, EditCoverModal, EditFeaturedModal, AddFeaturedModal},
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
          this.$bvModal.show('modal-featured-edit');
        })
      },
      onAdd(level) {
        this.currentLevel = level;
        this.$nextTick(() => {
          this.$bvModal.show('modal-featured-add');
        });
      },
      onCover(featured) {
        this.currentFeatured = featured;
        this.$nextTick(() => {
          this.$bvModal.show('modal-upload-featured-image');
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

    .btn-actions {
        bottom: 20px;
        right: 20px;
    }
</style>