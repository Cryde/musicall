<template>
    <b-card
            overlay
            class="featured-bg featured-level-1 position-relative"
            img-alt="Card Image"
            :text-variant="featured ? getColor(featured): 'dark'"
            :body-text-variant="featured ? getColor(featured): 'dark'"
            :img-src="featured ? featured.cover : ''"
            :title="featured ? featured.title : ''"
            @mouseenter="showActions = true"
            @mouseleave="showActions = false"
    >
        <b-card-text>{{ featured ? featured.description : '' }}</b-card-text>

        <div class="btn-actions position-absolute" v-show="showActions">
            <b-button v-if="featured" variant="danger"
                      class="mr-3"
                      v-b-tooltip.noninteractive.hover title="Supprimer la mise en mise en avant"
                      @click="remove(featured)"
            ><i class="far fa-trash-alt"></i></b-button>

            <b-button v-if="displayAction" variant="primary"
                      v-b-tooltip.noninteractive.hover title="Editer la mise en mise en avant"
                      @click="edit(featured)">
                <i class="far fa-edit"></i>
            </b-button>
            <b-button v-if="!featured" variant="primary" @click="add(1)"
                      v-b-tooltip.noninteractive.hover title="Ajouter une publication mise en avant">
                <i class="fas fa-plus"></i>
            </b-button>

            <b-button v-if="displayAction && featured.options.color === 'light'" variant="dark"
                      v-b-tooltip.noninteractive.hover title="Changer la couleur de la police (dark)"
                      @click="colorDark(featured)">
                <i class="fas fa-moon"></i>
            </b-button>

            <b-button v-if="displayAction && featured.options.color === 'dark'" variant="light"
                      v-b-tooltip.noninteractive.hover title="Changer la couleur de la police (light)"
                      @click="colorLight(featured)">
                <i class="fas fa-sun"></i>
            </b-button>

            <b-button v-if="displayAction" variant="primary"
                      v-b-tooltip.noninteractive.hover title="Editer l'image de la mise en avant"
                      @click="cover(featured)">
                <i class="far fa-image"></i>
            </b-button>

            <b-button v-if="showPublish" variant="success"
                      v-b-tooltip.noninteractive.hover title="Mettre en ligne"
                      @click="publish(featured)">
                <i class="far fa-eye"></i>
            </b-button>

            <b-button v-if="showUnPublish" variant="info"
                      v-b-tooltip.noninteractive.hover title="Cacher de la mise en ligne"
                      @click="unpublish(featured)">
                <i class="far fa-eye-slash"></i>
            </b-button>
        </div>
    </b-card>
</template>

<script>
  export default {
    props: ['featured', 'level'],
    data() {
      return {
        showActions: false
      }
    },
    computed: {
      showPublish() {
        if (!this.featured) {
          return false;
        }
        if (this.featured.status === 1) {
          return false; // already online
        }

        return !!this.featured.cover;
      },
      showUnPublish() {
        if (!this.featured) {
          return false;
        }

        return this.featured.status === 1;
      },
      displayAction() {
        if (!this.featured) {
          return false;
        }

        return this.featured.status !== 1;
      }
    },
    methods: {
      getColor(featured) {
        return featured.options.color === 'dark' ? 'dark' : 'white';
      },
      colorDark(featured) {
        this.$store.dispatch('adminFeatured/changeOption', {featuredId: featured.id, option: 'color', 'value': 'dark'})
      },
      colorLight(featured) {
        this.$store.dispatch('adminFeatured/changeOption', {featuredId: featured.id, option: 'color', 'value': 'light'})
      },
      add(level) {
        this.$emit('add', level);
      },
      edit(featured) {
        this.$emit('edit', featured);
      },
      cover(featured) {
        this.$emit('error', []);
        this.$emit('cover', featured);
      },
      remove(featured) {
        this.$store.dispatch('adminFeatured/remove', featured.id);
      },
      async publish(featured) {
        this.$emit('error', []);
        try {
          await this.$store.dispatch('adminFeatured/publish', featured.id);
        } catch (e) {
          this.$emit('error', e.response.data.violations.map(violation => violation.title));
        }
      },
      async unpublish(featured) {
        await this.$store.dispatch('adminFeatured/unpublish', featured.id);
      }
    }
  }
</script>