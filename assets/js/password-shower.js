import $ from "jquery";

function handleShowPassword() {
  $('.show-password').click(function () {
    $(this).find('i').toggleClass('fa-eye-slash');

    if ($('#password_field').attr('type') === 'text') {
      $('#password_field').attr('type', 'password');
    } else {
      $('#password_field').attr('type', 'text');
    }
  });
}

exports.init = function () {

  /**
   * To run this module you MUST have .show-password plus #password_field defined
   */
  if ($('.show-password').length && $('#password_field').length) {
    handleShowPassword();
  }
};