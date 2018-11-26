import $ from "jquery";

export default function () {
  $('.navbar-toggle').click(function () {

    const $navbarCollapse = $('.navbar-collapse'),
      $body = $('body');
    if ($navbarCollapse.is(':visible')) {
      $navbarCollapse.removeClass('in');
      $body.css('overflow', '');
    } else {
      $navbarCollapse.addClass('in');
      $body.css('overflow', 'hidden');
    }
  });

  $('body')
    .on('click', '.navbar-collapse.in .dropdown', function (e) {
      e.preventDefault();


      const $contentDropdown = $(this).find('.dropdown-content');

      if ($contentDropdown.is(':visible')) {
        $contentDropdown.removeClass('open');
      } else {
        $('.dropdown-content.open, .submenu ul.open').removeClass('open');
        $contentDropdown.addClass('open');
      }
    })
    .on('click', '.navbar-collapse.in .submenu:not(.no-after)', function (e) {
    e.preventDefault();

    const $contentDropdown = $(this).find('ul');

    if ($contentDropdown.is(':visible')) {
      $contentDropdown.removeClass('open');
    } else {
      $('.dropdown-content.open, .submenu ul.open').removeClass('open');
      $contentDropdown.addClass('open');
    }
  })
    .on('click', '.dropdown-content, .submenu ul', function (e) {
      e.stopPropagation();
    });


  $('.submenu').hover(
    function hoverIn() {
      if(isResponsiveMenu()) {
        return;
      }
      $('.submenu').find('ul').hide();
      $(this).find('ul').show();
    },
    function hoverOut() {
      if(isResponsiveMenu()) {
        return;
      }
      $('.submenu').find('ul').hide();
      $('.submenu.active').find('ul').show();
    });


}

function isResponsiveMenu() {
  return $('body').width() < 750;
}
