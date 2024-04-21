<template>
  <div>
    <b-loading v-if="isLoading" active/>
    <div v-else>
      <breadcrumb
          :root="{to: {name: 'home'}, label: 'Home'}"
          :level1="{to: {name: 'publication'}, label: 'Publications'}"
          :level2="{to: {name: 'gallery_list'}, label: 'Photos'}"
          :current="{label: gallery.title}"
      />

      <h1 class="subtitle is-3">{{ gallery.title }}</h1>

      <div class="author">
        Photo de <strong>{{ gallery.author.username }}</strong> <span
          v-if="gallery.publication_datetime">le {{ gallery.publication_datetime | dateFormat }}</span>
      </div>

      <div v-if="isLoadingImages" class="has-text-centered pt-5">
        <spinner/>
      </div>
      <div v-else>
        <masonry-wall :items="images" :column-width="250" :gap="2" class="mt-lg-4 mt-3 image-container">
          <template v-slot:default="{item: image, index}">
            <img
                :src="image.sizes.medium"
                :ref="`image-${index}`"
                :data-full-image="image.sizes.full"
                @click="openLightBox(index)"
            />
          </template>
        </masonry-wall>
      </div>

      <div class="lightbox-container" :class="{'displayed': showLightBox}">
        <img :src="currentImageLightBox" v-show="!imageLoading"/>

        <span v-show="imageLoading" class="l-loading">
            <spinner />
        </span>

        <span @click="nextImage" class="l-next"><i class="fas fa-chevron-right"></i></span>
        <span @click="prevImage" class="l-prev"><i class="fas fa-chevron-left"></i></span>
        <span @click="closeImage" class="l-close"><i class="fas fa-times"></i></span>
      </div>
    </div>
  </div>
</template>

<script>
import {mapGetters} from 'vuex';
import MasonryWall from '@yeger/vue2-masonry-wall'
import {format, parseISO} from 'date-fns';
import Spinner from "../../../components/global/misc/Spinner.vue";
import Breadcrumb from "../../../components/global/Breadcrumb.vue";

export default {
  components: {Breadcrumb, Spinner, MasonryWall},
  metaInfo() {
    return {
      title: this.gallery.title,
      meta: [
        {vmid: 'description', name: 'description', content: this.gallery.description}
      ]
    }
  },
  data() {
    return {
      showImages: false,
      showLightBox: false,
      currentImageLightBox: null,
      currentImageIndex: null,
      imageLoading: false,
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

    this.enableKeyboardNav();
  },
  methods: {
    openLightBox(index) {
      this.showLightBox = true;
      this.currentImageIndex = index;
      this.openImage();
    },
    nextImage() {
      const nextIndex = this.currentImageIndex + 1;
      this.currentImageIndex = nextIndex > this.images.length - 1 ? 0 : nextIndex;
      this.openImage();
    },
    prevImage() {
      const nextIndex = this.currentImageIndex - 1;
      this.currentImageIndex = nextIndex < 0 ? this.images.length - 1 : nextIndex;
      this.openImage();
    },
    openImage() {
      this.imageLoading = true;
      const image = this.$refs[`image-${this.currentImageIndex}`];
      const imageSrc = image.dataset.fullImage;
      const imageObject = new Image();
      imageObject.onload = () => {
        this.currentImageLightBox = imageSrc;
        this.$nextTick(() => {
          this.imageLoading = false;
        });
      };

      imageObject.src = imageSrc;
    },
    closeImage() {
      this.showLightBox = false;
      this.currentImageIndex = null;
      this.currentImageLightBox = null;
    },
    enableKeyboardNav() {
      window.addEventListener('keydown', this.keyboardCallback);
    },
    keyboardCallback(e) {
      if (e.code === 'ArrowRight') {
        this.showLightBox && this.nextImage();
      }

      if (e.code === 'ArrowLeft') {
        this.showLightBox && this.prevImage();
      }

      if (e.code === 'Escape') {
        this.showLightBox && this.closeImage();
      }
    }
  },
  filters: {
    dateFormat(date) {
      return format(parseISO(date), 'dd/MM/yyyy HH:mm');
    }
  },
  destroyed() {
    this.$store.dispatch('gallery/resetState');
    window.removeEventListener('keydown', this.keyboardCallback);
    this.showImages = false;
    this.showLightBox = false;
    this.currentImageLightBox = null;
    this.currentImageIndex = null;
    this.imageLoading = false;
  }
}

</script>

<style scoped>
.author {
  font-size: 0.8em;
}


.lightbox-container {
  position: fixed;
  z-index: 99999;
  height: 100%;
  width: 100%;
  background-color: rgba(0, 0, 0, 0.8);
  top: 0;
  left: 0;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.5s linear;
}

.lightbox-container.displayed {
  visibility: visible;
  opacity: 1;
  pointer-events: initial;
}

.lightbox-container img {
  position: fixed;
  background-color: white;
  margin: 0;
  padding: 0;
  max-height: 90%;
  max-width: 90%;
  top: 50%;
  left: 50%;
  margin-right: -50%;
  transform: translate(-50%, -50%);
  box-shadow: 0 0 20px black;
}

.l-close,
.l-next,
.l-prev {
  position: fixed;
  text-decoration: none;
  visibility: hidden;
  color: white;
  font-size: 36px;
  cursor: pointer;
  transition: color 0.2s;
}

.l-loading {
  position: fixed;
  top: 50%;
  left: 50%;
}

.l-close:hover,
.l-next:hover,
.l-prev:hover {
  color: gray;
}

.l-close {
  top: 1%;
  right: 1%;
  font-size: 32px;
}

.l-next,
.l-prev {
  top: 50%;
  transform: translate(0%, -50%);
}

.l-prev {
  left: 5%;
}

.l-next {
  right: 5%;
}

.lightbox-container.displayed .l-close,
.lightbox-container.displayed .l-next,
.lightbox-container.displayed .l-prev {
  visibility: visible;
}

.image-container {
  max-width: 100%;
}

.image-container img {
  cursor: pointer;
  transition: opacity .25s ease-in-out;
}

.image-container img:hover {
  opacity: .6;
}

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