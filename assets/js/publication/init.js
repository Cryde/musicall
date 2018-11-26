import descriptionExpander from "./description-expander";
import {init as editorInitializer} from "./text/editor";
import saverInitializer from "./text/saver";
import publishInitializer from "./text/publisher";
import initShareSocial from './share-social';

function init() {
  descriptionExpander();
  editorInitializer();
  saverInitializer();
  publishInitializer();
  initShareSocial();
}

export default init;

