import $ from 'jquery';
import {handleGoogleSearch} from './map';

export default () => {
  if ($('#musician-info').length) {
    handleGoogleSearch();
    handleSearchClick();
  }
}

function handleSearchClick() {

  $(document).on('keypress', '#musician-info', function(event) {
    return event.keyCode != 13;
  });

}


