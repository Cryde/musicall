<template>
    <div v-if="loaded">
        <h1>
            <router-link :to="{name:'user_publications'}" class="mr-2"><i class="fas fa-chevron-left"></i></router-link>
            {{ title }}
            <span v-b-modal.modal-publication-properties><i class="fas fa-cog"></i></span>
        </h1>
        <div class="content-box p-lg-3 p-3">
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
                                <span>{{ isActive.link() ? 'Mettre à jour le lien' : 'Ajouter un lien'}}</span>
                                <i class="fas fa-link"></i>
                            </button>
                        </template>

                    </div>
                </editor-menu-bubble>

                <div class="editor-sticky" v-sticky sticky-offset="offset" sticky-side="top" :sticky-z-index="1000">
                    <editor-menu-bar :editor="editor" v-slot="{ commands, isActive, getMarkAttrs }">

                        <div class="menubar">

                            <b-button-group>
                                <b-button
                                        variant="outline-primary" size="sm" v-b-tooltip.hover title="Gras"
                                        :pressed="isActive.bold()"
                                        @click="commands.bold">
                                    <i class="fas fa-bold"></i>
                                </b-button>
                                <b-button
                                        variant="outline-primary" size="sm" v-b-tooltip.hover title="Italique"
                                        :pressed="isActive.italic()"
                                        @click="commands.italic">
                                    <i class="fas fa-italic"></i>
                                </b-button>
                                <b-button
                                        variant="outline-primary" size="sm" v-b-tooltip.hover title="Paragraphe"
                                        :pressed="isActive.paragraph()"
                                        @click="commands.paragraph">
                                    <i class="fas fa-paragraph"></i>
                                </b-button>
                            </b-button-group>

                            <b-button-group>
                                <b-button
                                        variant="outline-primary" size="sm" v-b-tooltip.hover title="Titre niveau 2"
                                        :pressed="isActive.heading({ level: 2 })"
                                        @click="commands.heading({ level: 2 })">
                                    H2
                                </b-button>
                                <b-button
                                        variant="outline-primary" size="sm" v-b-tooltip.hover title="Titre niveau 3"
                                        :pressed="isActive.heading({ level: 3 })"
                                        @click="commands.heading({ level: 3 })">
                                    H3
                                </b-button>
                            </b-button-group>

                            <b-button-group>
                                <b-button
                                        variant="outline-primary" size="sm" v-b-tooltip.hover title="Aligner à gauche"
                                        :pressed="getMarkAttrs('align').textAlign === 'left'"
                                        @click="commands.align({textAlign: 'left'})">
                                    <i class="fas fa-align-left"></i>
                                </b-button>
                                <b-button
                                        variant="outline-primary" size="sm" v-b-tooltip.hover title="Aligner au centre"
                                        :pressed="getMarkAttrs('align').textAlign === 'center'"
                                        @click="commands.align({textAlign: 'center'})">
                                    <i class="fas fa-align-center"></i>
                                </b-button>
                                <b-button
                                        variant="outline-primary" size="sm" v-b-tooltip.hover title="Aligner à droite"
                                        :pressed="getMarkAttrs('align').textAlign === 'right'"
                                        @click="commands.align({textAlign: 'right'})">
                                    <i class="fas fa-align-right"></i>
                                </b-button>
                            </b-button-group>

                            <b-button-group>
                                <b-button
                                        variant="outline-primary" size="sm" v-b-tooltip.hover title="Liste à puce"
                                        :pressed="isActive.bullet_list()"
                                        @click="commands.bullet_list">
                                    <i class="fas fa-list-ul"></i>
                                </b-button>
                                <b-button
                                        variant="outline-primary" size="sm" v-b-tooltip.hover title="Liste numérotée"
                                        :pressed="isActive.ordered_list()"
                                        @click="commands.ordered_list">
                                    <i class="fas fa-list-ol"></i>
                                </b-button>
                            </b-button-group>

                            <b-button-group>
                                <b-button
                                        variant="outline-primary" size="sm" v-b-tooltip.hover title="Citation"
                                        :pressed="isActive.blockquote()"
                                        @click="commands.blockquote">
                                    <i class="fas fa-quote-right"></i>
                                </b-button>
                                <b-button variant="outline-primary" size="sm" v-b-tooltip.hover title="Séparation"
                                          @click="commands.horizontal_rule">
                                    _
                                </b-button>
                            </b-button-group>

                            <b-button
                                    variant="outline-primary" size="sm" v-b-tooltip.hover title="Insérer une image"
                                    :pressed="isActive.image()"
                                    @click="openUploadModal(commands.image)">
                                <i class="far fa-image"></i>
                            </b-button>

                            <b-button
                                    variant="outline-primary" size="sm" v-b-tooltip.hover
                                    title="Insérer une vidéo YouTube"
                                    :pressed="isActive.youtubeiframe()"
                                    @click="showVideoModal(commands.youtubeiframe)">
                                <i class="fab fa-youtube"></i>
                            </b-button>

                            <b-button-group>
                                <b-button
                                        variant="outline-primary" size="sm" v-b-tooltip.hover title="Défaire"
                                        @click="commands.undo">
                                    <i class="fas fa-undo"></i>
                                </b-button>
                                <b-button
                                        variant="outline-primary" size="sm" v-b-tooltip.hover title="Refaire"
                                        @click="commands.redo">
                                    <i class="fas fa-redo"></i>
                                </b-button>
                            </b-button-group>

                            <b-button variant="outline-success" size="sm" class="float-right" @click="publish"
                                      :disabled="submitted">
                              <b-spinner small v-if="submitted"></b-spinner>
                              <i class="far fa-paper-plane" v-else></i>
                              Publier
                            </b-button>

                            <b-button variant="outline-success" size="sm" class="float-right mr-1" @click="save"
                                      :disabled="submitted">
                                <b-spinner small v-if="submitted"></b-spinner>
                                <i class="far fa-save" v-else></i>
                                Enregistrer
                            </b-button>

                            <b-button variant="outline-info" size="sm"
                                      :to="{ name: 'publication_show', params: { slug: slug }}"
                                      target="_blank" class="float-right mr-1">
                                <i class="far fa-eye"></i>
                            </b-button>
                        </div>
                    </editor-menu-bar>
                </div>

                <editor-content :editor="editor" class="mt-3 publication-container"/>
            </div>

        </div>
        <UploadModal ref="uploadModal" @onConfirm="addCommand" :id="id"/>
        <VideoModal ref="videoModal" @onConfirm="addCommand"/>
        <EditModal :id="id" :title="title" :description="description" :cover="cover"
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
import UploadModal from './UploadModal';
import VideoModal from './VideoModal';
import EditModal from './EditModal';
import YoutubeIframe from "../../../../tiptap/YoutubeIframe";
import Align from "../../../../tiptap/Align";
import userPublication from "../../../../api/userPublication";
import userPublicationApi from "../../../../api/userPublication";
import PublishModal from "../list/PublishModal";

export default {
    components: {
      PublishModal,
      EditorContent,
      EditorMenuBar,
      EditorMenuBubble,
      UploadModal,
      EditModal,
      VideoModal
    },
    directives: {Sticky},
    data() {
      return {
        offset: {top: 74},
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
          this.$bvToast.toast('Votre publication a été enregistrée', {
            title: `Publication enregistrée`,
            variant: 'success',
            solid: true,
            toaster: 'b-toaster-bottom-left',
            append: true
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
      openUploadModal(command) {
        this.$refs.uploadModal.openModal(command);
      },
      showVideoModal(command) {
        this.$refs.videoModal.openModal(command)
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