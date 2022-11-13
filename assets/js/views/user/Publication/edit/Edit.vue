<template>
  <div v-if="isLoading">
    <b-loading active/>
  </div>
  <div v-else>
    <b-loading :active="isPublishing"/>
    <b-button icon-left="cog" class="is-pulled-right" @click="$refs['modal-publication-properties'].open()">
      Configuration
    </b-button>
    <h1 class="subtitle is-3">
      <router-link :to="{name:'user_publications'}" class="mr-2"><i class="fas fa-chevron-left"></i></router-link>
      {{ title }}
    </h1>
    <div class="box content is-shadowless p-lg-3 p-3" v-if="editor">
      <div>
        <div class="menubar buttons">
          <div class="buttons has-addons mr-2 mb-0">
            <b-tooltip label="Gras" type="is-black">
              <b-button size="is-small" type="is-info" icon-left="bold"
                        @click="editor.chain().focus().toggleBold().run()"
                        :class="{ 'is-light': !editor.isActive('bold') }"/>
            </b-tooltip>

            <b-tooltip label="Italique" type="is-black">
              <b-button size="is-small" type="is-info" icon-left="italic"
                        @click="editor.chain().focus().toggleItalic().run()"
                        :class="{ 'is-light': !editor.isActive('italic') }"/>
            </b-tooltip>

            <b-tooltip label="Paragraphe" type="is-black">
              <b-button size="is-small" type="is-info" icon-left="paragraph"
                        @click="editor.chain().focus().setParagraph().run()"
                        :class="{ 'is-light': !editor.isActive('paragraph') }"/>
            </b-tooltip>
          </div>

          <div class="buttons has-addons mr-2 mb-0">
            <b-tooltip label="Titre niveau 2" type="is-black">
              <b-button size="is-small" type="is-info"
                        :class="{ 'is-light': !editor.isActive('heading', { level: 2 }) }"
                        @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">
                H2
              </b-button>
            </b-tooltip>

            <b-tooltip label="Titre niveau 3" type="is-black">
              <b-button size="is-small" type="is-info"
                        :class="{ 'is-light': !editor.isActive('heading', { level: 3 }) }"
                        @click="editor.chain().focus().toggleHeading({ level: 3 }).run()">
                H3
              </b-button>
            </b-tooltip>
          </div>
          <div class="buttons has-addons mr-2 mb-0">
            <b-tooltip label="Aligner à gauche" type="is-black">
              <b-button size="is-small" type="is-info" icon-left="align-left"
                        :class="{ 'is-light': !editor.isActive({ textAlign: 'left' }) }"
                        @click="editor.chain().focus().setTextAlign('left').run()"/>
            </b-tooltip>

            <b-tooltip label="Aligner au centre" type="is-black">
              <b-button size="is-small" type="is-info" icon-left="align-center"
                        :class="{ 'is-light': !editor.isActive({ textAlign: 'center' }) }"
                        @click="editor.chain().focus().setTextAlign('center').run()"/>
            </b-tooltip>

            <b-tooltip label="Aligner à droite" type="is-black">
              <b-button size="is-small" type="is-info" icon-left="align-right"
                        :class="{ 'is-light': !editor.isActive({ textAlign: 'right' }) }"
                        @click="editor.chain().focus().setTextAlign('right').run()"/>
            </b-tooltip>
          </div>

          <div class="buttons has-addons mr-2 mb-0">
            <b-tooltip label="Liste à puce" type="is-black">
              <b-button size="is-small" type="is-info" icon-left="list-ul"
                        :class="{ 'is-light': !editor.isActive('bulletList') }"
                        @click="editor.chain().focus().toggleBulletList().run()"/>
            </b-tooltip>

            <b-tooltip label="Liste numérotée" type="is-black">
              <b-button size="is-small" type="is-info" icon-left="list-ol"
                        @click="editor.chain().focus().toggleOrderedList().run()"
                        :class="{ 'is-light': !editor.isActive('orderedList') }"/>
            </b-tooltip>
          </div>

          <div class="buttons has-addons mr-2 mb-0">
            <b-tooltip label="Citation" type="is-black">
              <b-button size="is-small" type="is-info" icon-left="quote-right"
                        @click="editor.chain().focus().toggleBlockquote().run()"
                        :class="{ 'is-light': !editor.isActive('blockquote') }"/>
            </b-tooltip>

            <b-tooltip label="Séparation" type="is-black">
              <b-button size="is-small" type="is-info is-light"
                        @click="editor.chain().focus().setHorizontalRule().run()">_
              </b-button>
            </b-tooltip>
          </div>

          <div class="buttons has-addons mr-2 mb-0">
            <b-tooltip label="Insérer une image" type="is-black">
              <b-button size="is-small" type="is-info is-light" icon-left="image"
                        @click="$refs.file.click()"/>
            </b-tooltip>
            <b-tooltip label="Insérer une vidéo YouTube" type="is-black">
              <b-button size="is-small" type="is-info is-light" icon-left="youtube" icon-pack="fab"
                        @click="showVideoModal()"/>
            </b-tooltip>
          </div>

          <div class="buttons has-addons mb-0 mr-5 ">
            <b-tooltip label="Défaire" type="is-black">
              <b-button size="is-small" type="is-info is-light" icon-left="undo"
                        @click="editor.chain().focus().undo().run()" :disabled="!editor.can().undo()"/>
            </b-tooltip>

            <b-tooltip label="Refaire" type="is-black">
              <b-button size="is-small" type="is-info is-light" icon-left="redo"
                        @click="editor.chain().focus().redo().run()"
                        :disabled="!editor.can().redo()"/>
            </b-tooltip>
          </div>


          <div class="buttons mb-0 ml-5 is-flex-grow-1 is-justify-content-end">
            <b-button size="is-small"
                      type="is-success" icon-left="paper-plane"
                      :loading="isPublishing" :disabled="isPublishing"
                      @click="publish">
              Publier
            </b-button>

            <b-button size="is-small"
                      type="is-success" icon-left="save"
                      :loading="isSaving" :disabled="isSaving"
                      @click="save">
              Enregistrer
            </b-button>

            <b-button size="is-small"
                      type="is-info" icon-left="eye" tag="router-link" target="_blank"
                      :to="{ name: 'publication_show', params: { slug: slug }}"/>

          </div>
        </div>

      </div>

      <editor-content :editor="editor" class="mt-3 publication-container"/>
    </div>

    <input type="file" class="is-hidden" ref="file" @change="uploadImage($event)" accept="image/*">
    <edit-modal ref="modal-publication-properties"/>
  </div>
</template>

<script>
import Sticky from 'vue-sticky-directive';
import axios from 'axios';
import {mapGetters} from 'vuex';
import StarterKit from '@tiptap/starter-kit'
import TextAlign from '@tiptap/extension-text-align'
import {Editor, EditorContent} from '@tiptap/vue-2'
import EditModal from './modal/EditModal';
import Image from '@tiptap/extension-image'
import YoutubeIframe from "../../../../tiptap/YoutubeIframe";
import {youtubeParser} from '../../../../helper/youtube-parser-url';

export default {
  components: {
    EditorContent,
    EditModal,
  },
  directives: {Sticky},
  data() {
    return {
      offset: {top: 90},
      contentLoaded: false,
      editor: null,
      linkUrl: null,
      linkMenuIsActive: false,
    }
  },
  computed: {
    ...mapGetters('publicationEdit', [
      'id',
      'content',
      'title',
      'description',
      'cover',
      'slug',
      'isDraft',
      'isLoading',
      'errors',
      'isSaving',
      'isPublishing',
      'errorsPublish'
    ])
  },
  async created() {
    try {
      await this.$store.dispatch('publicationEdit/loadPublication', this.getPublicationId());

      if (!this.isDraft) {
        this.$router.push({name: 'user_publications'});
        return;
      }

      this.editor = new Editor({
        extensions: [
          StarterKit.configure({
            heading: [2, 3]
          }),
          TextAlign.configure({
            types: ['paragraph'],
          }),
          Image,
          YoutubeIframe,
        ],
        onUpdate: ({editor}) => {
          // get new content on update
          this.$store.dispatch('publicationEdit/updateContent', editor.getHTML());
        },
        content: this.content,
      })
    } catch (e) {
      console.error(e);
    }
  },
  methods: {
    saveProperties({title, description}) {
      this.title = title;
      this.description = description;

      this.save()
      .then((finish) => {
        if (finish) {
          this.$bvModal.hide('modal-publication-properties')
        }
      });
    },
    async save() {
      await this.$store.dispatch('publicationEdit/save', {
        title: this.title,
        description: this.description,
        content: this.content
      });

      if (!this.errors.length) {
        this.$buefy.toast.open({
          message: 'Votre publication a été enregistrée',
          type: 'is-success',
          position: 'is-bottom-left',
        });
      }
    },
    async publish() {
      const {result, dialog} = await this.$buefy.dialog.confirm({
        title: 'Êtes vous sur ?',
        message: 'Une fois mise en ligne vous ne pourrez plus modifier la publication.',
        confirmText: 'Oui',
        cancelText: 'Annuler'
      });

      dialog.close();

      if (!result) {
        return;
      }

      await this.save();
      await this.$store.dispatch('publicationEdit/publish');

      if (!this.errorsPublish.length) {
        this.$buefy.dialog.alert({
          message: `Votre publication a été publiée !`,
          type: 'is-success',
          hasIcon: true
        })
        setTimeout(() => {
          this.$router.push({name: 'user_publications'});
        }, 2000);
      } else {
        this.$buefy.dialog.alert({
          title: 'Erreur lors de la publication',
          message: `
                <b>Veuillez corriger ces erreurs avant de publier:</b> <br/>
                <ul>${this.errorsPublish.map(error => `<li>${error}</li>`).join('')}</ul>
            `,
          type: 'is-danger',
          hasIcon: true
        })
      }
    },
    async uploadImage(event) {

      this.$Progress.start()
      const input = event.target;
      if (input.files && input.files[0]) {
        const beginToast = this.$buefy.toast.open({
          message: 'Début de l\'upload',
          type: 'is-info',
          position: 'is-bottom-left',
        });

        try {
          const form = new FormData();
          form.append('image_upload[imageFile][file]', input.files[0]);

          const {data} = await axios.post(Routing.generate('api_user_publication_upload_image', {id: this.id}), form, {
            onUploadProgress: progressEvent => {
              const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total)
              this.$Progress.set(percentCompleted);
            }
          });

          this.editor.chain().focus().setImage({src: data.uri}).run();

          beginToast.close();
          this.$Progress.finish();
          this.$buefy.toast.open({
            message: 'Image uploadée',
            type: 'is-info',
            position: 'is-bottom-left',
          });
        } catch (e) {
          beginToast.close();
          this.$Progress.fail();
          this.$buefy.toast.open({
            message: `Erreur lors de l'upload : ${e.response.data.map(item => item.message).join(', ')}`,
            type: 'is-danger',
            position: 'is-bottom-left',
          });
        }
      }
    },
    async showVideoModal(command) {
      const {result, dialog} = await this.$buefy.dialog.prompt({
        message: 'Url de la vidéo YouTube',
        inputAttrs: {
          type: 'text',
          placeholder: 'ex: https://www.youtube.com/watch?v=yoWDxUVIHPU',
        },
        type: 'is-success',
        confirmText: 'Ajouter la video',
        cancelText: 'Annuler',
        trapFocus: true,
        closeOnConfirm: false,
      })

      if (!youtubeParser(result).length) {
        this.$buefy.toast.open({
          message: 'Url YouTube incorrecte',
          type: 'is-danger',
          position: 'is-bottom-left',
        });
        dialog.close();
      }

      const youtubeId = youtubeParser(result);

      this.editor.chain().focus().addYoutubeVideo({
        src: `https://www.youtube.com/embed/${youtubeId}`,
      }).run()

      dialog.close();
    },
    addCommand(data) {
      if (data.command !== null) {
        data.command(data.data);
      }
    },
    getPublicationId() {
      return this.$route.params.id;
    },
    showLinkMenu(attrs) {
      this.linkUrl = attrs.href
      this.linkMenuIsActive = true
      this.$nextTick(() => {
        this.$refs.linkInput.focus()
      })
    },
    hideLinkMenu() {
      this.linkUrl = null
      this.linkMenuIsActive = false
    },
    setLinkUrl(command, url) {
      command({href: url})
      this.hideLinkMenu()
      this.editor.focus()
    },
  },
  destroyed() {
    this.editor.destroy();
  },
}
</script>