<template>
  <div class="card is-clickable" style="min-height: 250px"
       @mouseenter="showActions = true"
       @mouseleave="showActions = false">
    <div class="card-image" v-if="featured && featured.cover">
      <figure class="image card-image is-overlay">
        <img :src="featured.cover" :alt="featured.title"/>
      </figure>
    </div>
    <header class="card-header is-overlay" v-if="featured && featured.title">
      <h2 class="subtitle is-3 mt-3 ml-5" :class="getColor(featured)">{{ featured.title }}</h2>
    </header>
    <div class="card-content is-overlay mt-5 ml-0">
      <div class="content" v-if="featured && featured.description" :class="getColor(featured)">
        {{ featured.description }}
      </div>

      <div class="btn-actions" v-show="showActions">
        <b-tooltip v-if="featured" type="is-black" position="is-left" label="Supprimer la mise en mise en avant">
          <b-button type="is-danger" class="mr-3" @click="remove(featured)" icon-left="trash-alt"/>
        </b-tooltip>
        <b-tooltip v-if="displayAction" type="is-black" position="is-left" label="Editer la mise en mise en avant">
          <b-button type="is-info" icon-left="edit" @click="edit(featured)"/>
        </b-tooltip>
        <b-tooltip v-if="!featured" type="is-black" position="is-left" label="Ajouter une publication mise en avant">
          <b-button type="is-info" @click="add(1)" icon-left="plus"/>
        </b-tooltip>
        <b-tooltip
            v-if="displayAction && featured.options.color === 'light'"
            type="is-black" position="is-left" label="Changer la couleur de la police (dark)">
          <b-button type="is-dark" @click="colorDark(featured)" icon-left="moon"/>
        </b-tooltip>
        <b-tooltip v-if="displayAction && featured.options.color === 'dark'"
                   type="is-black" position="is-left" label="Changer la couleur de la police (light)">
          <b-button type="is-light" icon-left="sun" @click="colorLight(featured)"/>
        </b-tooltip>
        <b-tooltip v-if="displayAction" type="is-black" position="is-left" label="Editer l'image de la mise en avant">
          <b-button type="is-info" icon-left="image" @click="cover(featured)"/>
        </b-tooltip>
        <b-tooltip v-if="showPublish" type="is-black" position="is-left" label="Mettre en ligne">
          <b-button type="is-success" icon-left="eye" @click="publish(featured)"/>
        </b-tooltip>
        <b-tooltip v-if="showUnPublish" type="is-black" position="is-left" label="Cacher de la mise en ligne">
          <b-button type="is-info" icon-left="eye-slash" @click="unpublish(featured)"/>
        </b-tooltip>
      </div>
    </div>
  </div>
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
      return featured.options.color === 'dark' ? 'has-text-dark' : 'has-text-white';
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

<style scoped>
.btn-actions {
  bottom: 20px;
  right: 20px;
  position: absolute;
}
</style>