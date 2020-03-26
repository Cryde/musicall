<template>
    <div>
        <div v-if="isLoading" class="text-center pt-5">
            <b-spinner variant="primary" label="Spinning"></b-spinner>
        </div>
        <div v-else>
            <h1>{{ gallery.title }}</h1>

            <div class="author">
                Photo de <strong>{{ gallery.author.username }}</strong> <span v-if="gallery.publicationDatetime">le {{ gallery.publicationDatetime | dateFormat }}</span>
            </div>

            <div v-if="isLoadingImages" class="text-center pt-5">
                <b-spinner variant="primary" label="Spinning"></b-spinner>
            </div>
            <div v-else>
                <div class="mt-lg-4 mt-3" ref="image-container" v-show="showImages">
                    <img v-for="image in images" v-lazy="{src: image.sizes.medium, loading: image.sizes.small}"/>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
  import {mapGetters} from 'vuex';
  import Macy from 'macy';
  import {format, parseISO} from 'date-fns';

  export default {
    data() {
      return {
        macy: null,
        showImages: false
      }
    },
    computed: {
      ...mapGetters('gallery', [
        'gallery',
        'images',
        'isLoading',
        'isLoadingImages',
      ])
    },
    async mounted() {
      const slug = this.$route.params.slug;

      await this.$store.dispatch('gallery/loadGallery', slug);
      await this.$store.dispatch('gallery/loadImages', slug);

      this.macy = Macy({
        container: this.$refs['image-container'],
        trueOrder: false,
        waitForImages: true,
        margin: 14,
        columns: 3,
        breakAt: {
          1200: 4,
          940: 3,
          520: 2,
          400: 1
        }
      });
      this.macy.runOnImageLoad(() => {
        this.macy.recalculate(true);
        this.showImages = true;
      });
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

<style>
    img[lazy=loading] {
        -webkit-filter: blur(2px);
        filter: blur(2px);
        transition: filter 400ms, -webkit-filter 400ms;
    }

    img[laze=loaded] {
        -webkit-filter: blur(0);
        filter: blur(0);
    }
</style>