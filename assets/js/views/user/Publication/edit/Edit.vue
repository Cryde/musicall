<template>
  <div v-if="loaded">
    <b-button icon-left="cog" class="is-pulled-right">Configuration</b-button>
    <h1 class="subtitle is-3">
      <router-link :to="{name:'user_publications'}" class="mr-2"><i class="fas fa-chevron-left"></i></router-link>
      {{ title }}
    </h1>
    <div class="box content is-shadowless p-lg-3 p-3">
      <div class="editor">
        <editor-menu-bubble class="menububble" :editor="editor" @hide="hideLinkMenu"
                            v-slot="{ commands, isActive, getMarkAttrs, menu }">
          <div
              class="menububble"
              :class="{ 'is-active': menu.isActive }"
              :style="`left: ${menu.left}px; bottom: ${menu.bottom}px;`"
          >

            <form class="menububble__form" v-if="linkMenuIsActive"
                  @submit.prevent="setLinkUrl(commands.link, linkUrl)">
              <input class="menububble__input" type="text" v-model="linkUrl" placeholder="https://"
                     ref="linkInput" @keydown.esc="hideLinkMenu"/>
              <button class="menububble__button" @click="setLinkUrl(commands.link, null)" type="button">
                <i class="fas fa-times"></i>
              </button>
            </form>

            <template v-else>
              <button
                  class="menububble__button"
                  @click="showLinkMenu(getMarkAttrs('link'))"
                  :class="{ 'is-active': isActive.link() }"
              >
                <span>{{ isActive.link() ? 'Mettre à jour le lien' : 'Ajouter un lien' }}</span>
                <i class="fas fa-link"></i>
              </button>
            </template>

          </div>
        </editor-menu-bubble>

        <div class="editor-sticky" v-sticky sticky-offset="offset" sticky-side="top" :sticky-z-index="1000">
          <editor-menu-bar :editor="editor" v-slot="{ commands, isActive, getMarkAttrs }">

            <div class="menubar buttons">

              <div class="buttons has-addons mr-2 mb-0">
                <b-tooltip label="Gras" type="is-black">
                  <b-button size="is-small" type="is-info" icon-left="bold"
                            :class="isActive.bold() ? '' : 'is-light'" @click="commands.bold"/>
                </b-tooltip>

                <b-tooltip label="Italique" type="is-black">
                  <b-button size="is-small" type="is-info" icon-left="italic"
                            :class="isActive.italic() ? '' : 'is-light'" @click="commands.italic"/>
                </b-tooltip>

                <b-tooltip label="Paragraphe" type="is-black">
                  <b-button size="is-small" type="is-info" icon-left="paragraph"
                            :class="isActive.paragraph() ? '' : 'is-light'" @click="commands.paragraph"/>
                </b-tooltip>
              </div>

              <div class="buttons has-addons mr-2 mb-0">
                <b-tooltip label="Titre niveau 2" type="is-black">
                  <b-button size="is-small" type="is-info"
                            :class="isActive.heading({ level: 2 }) ? '' : 'is-light'"
                            @click="commands.heading({ level: 2 })">
                    H2
                  </b-button>
                </b-tooltip>

                <b-tooltip label="Titre niveau 3" type="is-black">
                  <b-button size="is-small" type="is-info"
                            :class="isActive.heading({ level: 3 }) ? '' : 'is-light'"
                            @click="commands.heading({ level: 3 })">
                    H3
                  </b-button>
                </b-tooltip>
              </div>

              <div class="buttons has-addons mr-2 mb-0">
                <b-tooltip label="Aligner à gauche" type="is-black">
                  <b-button size="is-small" type="is-info" icon-left="align-left"
                            :class="getMarkAttrs('align').textAlign === 'left' ? '' : 'is-light'"
                            @click="commands.align({textAlign: 'left'})"/>
                </b-tooltip>

                <b-tooltip label="Aligner au centre" type="is-black">
                  <b-button size="is-small" type="is-info" icon-left="align-center"
                            :class="getMarkAttrs('align').textAlign === 'centered' ? '' : 'is-light'"
                            @click="commands.align({textAlign: 'centered'})"/>
                </b-tooltip>

                <b-tooltip label="Aligner à droite" type="is-black">
                  <b-button size="is-small" type="is-info" icon-left="align-right"
                            :class="getMarkAttrs('align').textAlign === 'right' ? '' : 'is-light'"
                            @click="commands.align({textAlign: 'right'})"/>
                </b-tooltip>
              </div>

              <div class="buttons has-addons mr-2 mb-0">
                <b-tooltip label="Liste à puce" type="is-black">
                  <b-button size="is-small" type="is-info" icon-left="list-ul"
                            :class="isActive.bullet_list() ? '' : 'is-light'" @click="commands.bullet_list"/>
                </b-tooltip>

                <b-tooltip label="Liste numérotée" type="is-black">
                  <b-button size="is-small" type="is-info" icon-left="list-ol"
                            :class="isActive.ordered_list() ? '' : 'is-light'" @click="commands.ordered_list"/>
                </b-tooltip>
              </div>


              <div class="buttons has-addons mr-2 mb-0">
                <b-tooltip label="Citation" type="is-black">
                  <b-button size="is-small" type="is-info" icon-left="quote-right"
                            :class="isActive.blockquote() ? '' : 'is-light'" @click="commands.blockquote"/>
                </b-tooltip>

                <b-tooltip label="Séparation" type="is-black">
                  <b-button size="is-small" type="is-info is-light"
                            @click="commands.horizontal_rule">_
                  </b-button>
                </b-tooltip>
              </div>

              <div class="buttons has-addons mr-2 mb-0">
                <b-tooltip label="Insérer une image" type="is-black">
                  <b-button size="is-small" type="is-info" icon-left="image"
                            :class="isActive.image() ? '' : 'is-light'" @click="$refs.file.click()"/>
                </b-tooltip>

                <b-tooltip label="Insérer une vidéo YouTube" type="is-black">
                  <b-button size="is-small" type="is-info" icon-left="youtube" icon-pack="fab"
                            :class="isActive.youtubeiframe() ? '' : 'is-light'"
                            @click="showVideoModal(commands.youtubeiframe)"/>
                </b-tooltip>
              </div>

              <div class="buttons has-addons mb-0 mr-5 ">
                <b-tooltip label="Défaire" type="is-black">
                  <b-button size="is-small" type="is-info is-light" icon-left="undo"
                            @click="commands.undo"/>
                </b-tooltip>

                <b-tooltip label="Refaire" type="is-black">
                  <b-button size="is-small" type="is-info is-light" icon-left="redo"
                            @click="commands.redo"/>
                </b-tooltip>
              </div>

              <div class="buttons mb-0 ml-5">
                <b-button size="is-small"
                          type="is-success is-light" icon-left="paper-plane"
                          :loading="submitted" :disabled="submitted"
                          @click="publish">
                  Publier
                </b-button>

                <b-button size="is-small" class="mr-1"
                          type="is-success is-light" icon-left="save"
                          :loading="submitted" :disabled="submitted"
                          @click="save">
                  Enregistrer
                </b-button>

                <b-button size="is-small" class="mr-1"
                          type="is-info is-light" icon-left="eye" tag="router-link" target="_blank"
                          :to="{ name: 'publication_show', params: { slug: slug }}"/>

              </div>
            </div>
          </editor-menu-bar>
        </div>

        <editor-content :editor="editor" class="mt-3 publication-container"/>
      </div>

    </div>
    <input type="file" class="is-hidden" ref="file" @change="uploadImage($event)" accept="image/*">
    <upload-modal ref="uploadModal" @onConfirm="addCommand" :id="id"/>
    <edit-modal :id="id" :title="title" :description="description" :cover="cover"
                :publication-errors="errors"
                :submitted="submitted"
                v-on:saveProperties="saveProperties"/>
    <publish-modal
        :errors="publishErrors"
        :loading="isPublishing"
    ></publish-modal>
  </div>
</template>

<script>
import Sticky from 'vue-sticky-directive';
import axios from 'axios';
import {Editor, EditorContent, EditorMenuBar, EditorMenuBubble} from 'tiptap'
import {
  Blockquote,
  Bold,
  BulletList,
  HardBreak,
  Heading,
  History,
  HorizontalRule,
  Image,
  Italic,
  Link,
  ListItem,
  OrderedList
} from 'tiptap-extensions';
import UploadModal from './modal/UploadModal';
import EditModal from './modal/EditModal';
import YoutubeIframe from "../../../../tiptap/YoutubeIframe";
import Align from "../../../../tiptap/Align";
import userPublication from "../../../../api/userPublication";
import userPublicationApi from "../../../../api/userPublication";
import PublishModal from "../list/PublishModal";
import {youtubeParser} from '../../../../helper/youtube-parser-url';

export default {
  components: {
    PublishModal,
    EditorContent,
    EditorMenuBar,
    EditorMenuBubble,
    UploadModal,
    EditModal,
  },
  directives: {Sticky},
  data() {
    return {
      offset: {top: 90},
      loaded: false,
      submitted: false,
      saved: false,
      editor: new Editor({
        extensions: [
          new Blockquote(),
          new BulletList(),
          new HardBreak(),
          new Heading({levels: [2, 3]}),
          new HorizontalRule(),
          new ListItem(),
          new OrderedList(),
          new Link(),
          new Bold(),
          new Italic(),
          new Link(),
          new History(),
          new Image(),
          new YoutubeIframe(),
          new Align()
        ],
        onUpdate: ({getHTML}) => {
          // get new content on update
          this.content = getHTML()
        },
        content: '',
      }),
      id: '',
      title: '',
      description: '',
      content: '',
      cover: '',
      slug: '',
      linkUrl: null,
      linkMenuIsActive: false,
      errors: [],
      publishErrors: [],
      isPublishing: false,
    }
  },
  async mounted() {

    try {
      const publication = await userPublication.getPublication(this.getPublicationId());
      if (publication.status_id === 2 || publication.status_id === 1) { // online / pending review
        this.$router.push({name: 'user_publications'});
        return;
      }
      this.id = publication.id;
      this.content = publication.content;
      this.editor.setContent(publication.content);
      this.title = publication.title;
      this.description = publication.short_description;
      this.cover = publication.cover;
      this.slug = publication.slug;
      this.loaded = true;
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
      this.submitted = true;
      const data = {'title': this.title, 'short_description': this.description, 'content': this.content};

      this.resetErrors();
      try {
        const publication = await userPublication.savePublication({id: this.getPublicationId(), data});
        this.slug = publication.slug;
        this.submitted = false;
        this.$buefy.toast.open({
          message: 'Votre publication a été enregistrée',
          type: 'is-success',
          position: 'is-bottom-left',
        });

        return true;
      } catch (e) {
        this.submitted = false;
        this.errors = e.response.data.violations.map(violation => violation.title);
        return false;
      }
    },
    async publish() {
      this.publishErrors = [];
      const value = await this.$bvModal.msgBoxConfirm('Une fois mise en ligne vous ne pourrez plus modifier la publication.', {
        title: 'Êtes vous sur ?',
        okTitle: 'Oui',
        cancelTitle: 'Annuler',
        centered: true
      });

      if (!value) {
        return;
      }

      try {
        this.isPublishing = true;
        const saved = await this.save();
        if (saved) {
          this.$bvModal.show('modal-publication-control');
          await userPublicationApi.publishPublicationApi(this.getPublicationId());
          this.isPublishing = false;
          setTimeout(() => {
            this.$router.push({name: 'user_publications'});
          }, 2000);
          return;
        }
      } catch (e) {
        this.publishErrors = e.response.data.violations.map(violation => violation.title);
        this.isPublishing = false;
      }
    },
    async uploadImage(command) {

      const input = event.target;
      if (input.files && input.files[0]) {

        try {
          const form = new FormData();
          form.append('image_upload[imageFile][file]', input.files[0]);

          const {data} = await axios.post(Routing.generate('api_user_publication_upload_image', {id: this.id}), form, {
            onUploadProgress: progressEvent => {
              const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total)
              console.log(percentCompleted)
            }
          });

          console.log(data);
        } catch(e) {
          console.log('ERROR');
          console.log(e);
        }

        /*
        const reader = new FileReader();
        reader.onprogress = e => {
            console.log(e);
        };

        reader.onload = e => {
          this.image = e.target.result;

          console.log(e.target.files[0]);


          this.$nextTick(() => {
         //   this.$refs['profile-picture-modal'].open();
          })
        };
        reader.readAsDataURL(input.files[0]);*/
      }

      //this.$refs.uploadModal.openModal(command);
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

      this.addCommand({
        command,
        data: {src: `https://www.youtube.com/embed/${youtubeId}`}
      })

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
    displayErrors(errors) {
      for (let error of errors) {
        const propertyPath = error.propertyPath;
        const message = error.title;

        this.validation[propertyPath].state = false;
        this.validation[propertyPath].message = message;
      }
    },
    resetErrors() {
      this.errors = [];
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
  beforeDestroy() {
    this.editor.destroy();
  },
}
</script>