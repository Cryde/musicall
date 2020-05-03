import { Mark } from 'tiptap'
import {markInputRule, updateMark} from 'tiptap-commands';

export default class Align extends Mark {
  // eslint-disable-next-line class-methods-use-this
  get name() {
    return 'align';
  }

  // eslint-disable-next-line class-methods-use-this
  get schema() {
    return {
      attrs: {
        textAlign: {
          default: 'left',
        },
      },
      parseDOM: [
        {
          style: 'text-align',
          getAttrs: value => ({textAlign: value}),
        },
      ],
      toDOM: mark => ['span', {class: `text-${mark.attrs.textAlign} d-block`}, 0],
    };
  }

  // eslint-disable-next-line class-methods-use-this
  commands({type}) {
    return attrs => updateMark(type, attrs);
  }

  // eslint-disable-next-line class-methods-use-this
  inputRules({type}) {
    return [
      markInputRule(/(?:\*\*|__)([^*_]+)(?:\*\*|__)$/, type),
    ];
  }
}