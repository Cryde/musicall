import {Node} from '@tiptap/core'
import {mergeAttributes} from "@tiptap/vue-2";

const YoutubeIframe = Node.create({
  name: 'youtube',
  draggable: true,

  group() {
    return 'block'
  },

  addAttributes() {
    return {
      src: {
        default: null,
      },
    }
  },

  parseHTML() {
    return [
      {
        tag: 'div[data-youtube-video] iframe',
      },
    ]
  },
  renderHTML({node, HTMLAttributes}) {
    return [
      'div',
      {'data-youtube-video': '', class: 'image is-16by9'},
      [
        'iframe',
        mergeAttributes(
            HTMLAttributes,
            {
              frameborder: 0,
              allowfullscreen: "true",
              class: "has-ratio",
              allow: "accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
            },
        ),
      ],
    ];
  },
  addCommands() {
    return {
      addYoutubeVideo: options => ({commands}) => {
        return commands.insertContent({
          type: this.name,
          attrs: options,
        })
      },
    }
  },
})

export default YoutubeIframe;