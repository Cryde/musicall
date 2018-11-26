import $ from "jquery";

exports.init = function () {

  if ($('.actions-menu').length) {

    $.fn.reverse = [].reverse;

    $(".content")
      .on('mouseenter', '.actions-menu', function () {
        const $this = $(this);
        openMenuAction($this);
      })
      .on('mouseleave', '.actions-menu', function () {
        const $this = $(this);
        closeMenuAction($this);
      });
  }
};


function openMenuAction(btn) {
  const $this = btn;

  if (!$this.hasClass('active')) {

    $this.addClass('active');
    $this.find('ul .small-btn').removeClass('btn-animate');

    let time = 0;
    $this.find('ul .small-btn').reverse().each(function () {
      setTimeout(() => {
        $(this).addClass('btn-animate');
      }, time);

      time += 40;
    });
  }
}


function closeMenuAction(btn) {
  const $this = btn;

  if ($this.hasClass('active')) {

    $this.removeClass('active');

    let time = 0;
    $this.find('ul .small-btn').each(function () {
      setTimeout(() => {
        $(this).removeClass('btn-animate');
      }, time);

      time += 40;
    });
  }
}