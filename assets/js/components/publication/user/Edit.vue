<template>
    <div v-if="loaded">
        <h1>
            <router-link :to="{name:'user_publications'}" class="mr-2"><i class="fas fa-chevron-left"></i></router-link>
            {{ title }}
            <span v-b-modal.modal-publication-properties><i class="fas fa-cog"></i></span>
        </h1>
        <div class="content-box publication">
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
                    <editor-menu-bar :editor="editor" v-slot="{ commands, isActive }">

                        <div class="menubar">

                            <b-button
                                    variant="outline-primary"
                                    :pressed="isActive.bold()"
                                    @click="commands.bold">
                                <i class="fas fa-bold"></i>
                            </b-button>

                            <b-button
                                    variant="outline-primary"
                                    :pressed="isActive.italic()"
                                    @click="commands.italic">
                                <i class="fas fa-italic"></i>
                            </b-button>

                            <b-button
                                    variant="outline-primary"
                                    :pressed="isActive.paragraph()"
                                    @click="commands.paragraph">
                                <i class="fas fa-paragraph"></i>
                            </b-button>

                            <b-button
                                    variant="outline-primary"
                                    :pressed="isActive.heading({ level: 2 })"
                                    @click="commands.heading({ level: 2 })">
                                H2
                            </b-button>

                            <b-button
                                    variant="outline-primary"
                                    :pressed="isActive.heading({ level: 3 })"
                                    @click="commands.heading({ level: 3 })">
                                H3
                            </b-button>

                            <b-button
                                    variant="outline-primary"
                                    :pressed="isActive.bullet_list()"
                                    @click="commands.bullet_list">
                                <i class="fas fa-list-ul"></i>
                            </b-button>

                            <b-button
                                    variant="outline-primary"
                                    :pressed="isActive.ordered_list()"
                                    @click="commands.ordered_list">
                                <i class="fas fa-list-ol"></i>
                            </b-button>

                            <b-button
                                    variant="outline-primary"
                                    :pressed="isActive.blockquote()"
                                    @click="commands.blockquote">
                                <i class="fas fa-quote-right"></i>
                            </b-button>

                            <b-button
                                    variant="outline-primary"
                                    :pressed="isActive.image()"
                                    @click="openUploadModal(commands.image)">
                                <i class="far fa-image"></i>
                            </b-button>

                            <b-button variant="outline-primary" @click="commands.horizontal_rule">
                                _
                            </b-button>

                            <b-button
                                    variant="outline-primary"
                                    @click="commands.undo">
                                <i class="fas fa-undo"></i>
                            </b-button>

                            <b-button
                                    variant="outline-primary"
                                    @click="commands.redo">
                                <i class="fas fa-redo"></i>
                            </b-button>


                            <b-button variant="success" class="float-right" @click="save" :disabled="submitted">
                                <b-spinner small v-if="submitted"></b-spinner>
                                <i class="far fa-save" v-else></i>
                                Enregistrer
                            </b-button>
                        </div>
                    </editor-menu-bar>
                </div>

                <editor-content :editor="editor" class="mt-3"/>
            </div>

        </div>
        <UploadModal ref="uploadModal" @onConfirm="addCommand" :id="id"/>
        <EditModal :title="title" :description="description" :validation="validation" v-on:saveProperties="saveProperties"/>
    </div>
</template>

<script>
  import 'babel-polyfill';
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
  import EditModal from './EditModal';

  export default {
    components: {
      EditorContent,
      EditorMenuBar,
      EditorMenuBubble,
      UploadModal,
      EditModal
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
            new Image()
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
        linkUrl: null,
        linkMenuIsActive: false,
        validation: {
          title: {
            state: null,
            message: '',
          }
        }
      }
    },
    mounted() {

      this.getPublication(this.getPublicationId())
      .then((publication) => {
        this.id = publication.id;
        this.content = publication.content;
        this.editor.setContent(publication.content);
        this.title = publication.title;
        this.description = publication.short_description;
        this.loaded = true;
      });
    },
    methods: {
      saveProperties({title, description}) {
        this.title = title;
        this.description = description;

        this.save()
        .then((finish) => {
          if(finish) {
            this.$bvModal.hide('modal-publication-properties')
          }
        });
      },
      save() {
        this.submitted = true;
        const publication = {'title': this.title, 'short_description': this.description, 'content': this.content};

        return this.saveContent(this.getPublicationId(), publication)
        .then(resp => {
          this.submitted = false;
          this.$bvToast.toast('Votre publication a été enregistrée', {
            title: `Publication enregistrée`,
            variant: 'success',
            solid: true,
            toaster: 'b-toaster-bottom-left',
            append: true
          })

          return true;
        })
        .catch(violation => {
          this.submitted = false;
          if (violation.data.errors.violations) {
            this.displayErrors(violation.data.errors.violations);
          }
        });
      },
      saveContent(id, data) {
        this.resetValidationState();
        return fetch(Routing.generate('api_user_publication_save', {id}), {method: 'POST', body: JSON.stringify(data)})
        .then(this.handleErrors)
        .then(resp => resp.json());
      },
      openUploadModal(command) {
        this.$refs.uploadModal.openModal(command);
      },
      addCommand(data) {
        if (data.command !== null) {
          data.command(data.data);
        }
      },
      getPublication(id) {
        return fetch(Routing.generate('api_user_publication_show', {id}))
        .then(this.handleErrors)
        .then(resp => resp.json())
        .then(resp => resp.data.publication);
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
      async handleErrors(response) {
        if (!response.ok) {
          const data = await response.json();
          return Promise.reject(data)
        }
        return response;
      },
      resetValidationState() {
        this.validation.title = {
          state: null,
          message: ''
        }
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
      this.editor.destroy()
    },
  }
</script>