import {Node} from "tiptap";

export default class YoutubeIframe extends Node {
  get name() {
    return "youtubeiframe";
  }

  get schema() {
    return {
      attrs: {
        src: {
          default: null
        }
      },
      group: "block",
      selectable: false,
      parseDOM: [
        {
          tag: "iframe",
          getAttrs: (dom) => {
            return {
              src: dom.getAttribute("src")
            }
          }
        }
      ],
      toDOM: node => [
        'div',
        {
          class: 'embed-responsive embed-responsive-21by9'
        },
        ["iframe",
          {
            src: node.attrs.src,
            frameborder: 0,
            allowfullscreen: "true",
            class: "embed-responsive-item",
            allow:
                "accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
          }]
      ]
    };
  }
  commands({type}) {
    return attrs => (state, dispatch) => {
      const {selection} = state;
      const position = selection.$cursor ? selection.$cursor.pos : selection.$to.pos;
      const node = type.create(attrs);
      const transaction = state.tr.insert(position, node);
      dispatch(transaction);
    };
  }
}