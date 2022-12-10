<template>
  <div>
    <div v-if="editor">
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
        </div>
      </div>
      <editor-content :editor="editor" class="mt-3 editor-forum content p-2"/>
    </div>
  </div>
</template>

<script>
import {Editor, EditorContent} from "@tiptap/vue-2";
import StarterKit from "@tiptap/starter-kit";
import TextAlign from "@tiptap/extension-text-align";
import Image from "@tiptap/extension-image";
import YoutubeIframe from "../../../../tiptap/YoutubeIframe";

export default {
  components: {EditorContent},
  props: {
    previousContent: {
      type: String,
      default: ''
    }
  },
  data() {
    return {
      editor: null,
      content: '',
    }
  },
  mounted() {
    this.content = this.previousContent;
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
      autofocus: true,
      onUpdate: ({editor}) => {
        this.$emit('content-update', {html: editor.getHTML(), text: editor.getText()});
      },
      content: this.content,
    })
  }
}
</script>