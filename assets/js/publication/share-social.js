import $ from 'jquery';
import 'jssocials';

export default () => {
  const $socialShareBox = $('.publication-social-sharing');
  if ($socialShareBox.length) {

    $socialShareBox.jsSocials({
      showLabel: false,
      showCount: "inside",
      shares: ["email", "twitter", "facebook", "googleplus", "pinterest", "whatsapp"]
    });
  }
}