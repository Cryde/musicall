import SirTrevor from 'sir-trevor';

export default () => {

  const field = document.querySelector('.js-st-instance');

  if (field) {

    SirTrevor.setDefaults({
      iconUrl: '/build/images/sir-trevor-icons.svg'
    });

    var editor = new SirTrevor.Editor({
      el: field,
      defaultType: 'Text',
      blockTypes: ["Text", "Image", "Video"]
    });
  }
}