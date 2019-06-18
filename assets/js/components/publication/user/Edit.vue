<template>
    <div>
        <h2>Editer une publication</h2>
        <div class="content-box">

            <b-form-group description="Le titre de votre publication">
                <b-form-input v-model="title" :state="validation.title.state"
                              placeholder="Votre titre ici"></b-form-input>
                <b-form-invalid-feedback :state="validation.title.state">
                    {{ validation.title.message }}
                </b-form-invalid-feedback>
            </b-form-group>

            <b-form-group description="Cette courte description apparaitra sur la page d'accueil">
                <b-form-textarea
                        v-model="description"
                        id="textarea"
                        placeholder="Une courte description de l'article"
                        rows="3"
                ></b-form-textarea>
            </b-form-group>

            <div class="editor">
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

                    </div>
                </editor-menu-bar>

                <editor-content :editor="editor" class="mt-3"/>
            </div>

            <div class="mt-5  clearfix">
                <b-button variant="success" class="float-right" @click="save" :disabled="submitted">
                    <b-spinner small v-if="submitted"></b-spinner>
                    <i class="far fa-save" v-else></i>
                    Enregistrer
                </b-button>
            </div>
        </div>
    </div>
</template>

<script>
  import 'babel-polyfill';
  import {Editor, EditorContent, EditorMenuBar} from 'tiptap'
  import {
    Blockquote,
    Bold,
    BulletList,
    HardBreak,
    Heading,
    History,
    HorizontalRule,
    Italic,
    Link,
    ListItem,
    OrderedList,
  } from 'tiptap-extensions'

  export default {
    components: {
      EditorContent,
      EditorMenuBar,
    },
    data() {
      return {
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
            new History(),
          ],
          onUpdate: ({getHTML}) => {
            // get new content on update
            this.content = getHTML()
          },
          content: '',
        }),
        title: '',
        description: '',
        content: '',
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
        this.content = publication.content;
        this.editor.setContent(publication.content);
        this.title = publication.title;
        this.description = publication.short_description;
      });
    },
    methods: {
      save() {
        this.submitted = true;
        const publication = {'title': this.title, 'short_description': this.description, 'content': this.content};

        this.saveContent(this.getPublicationId(), publication)
        .then(resp => {
          this.submitted = false;
          console.log(resp);
          this.$bvToast.toast('Votre publication a été enregistrée', {
            title: `Publication enregistrée`,
            variant: 'success',
            solid: true,
            toaster: 'b-toaster-bottom-left',
            append: true
          })

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
      getPublication(id) {
        return fetch(Routing.generate('api_user_publication_show', {id}))
        .then(this.handleErrors)
        .then(resp => resp.json())
        .then(resp => resp.data.publication);
      },
      getPublicationId() {
        return document.querySelector('#publication-data').dataset.publicationId;
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
      }
    },
    beforeDestroy() {
      this.editor.destroy()
    },
  }
</script>