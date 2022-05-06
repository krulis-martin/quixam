$(() => {
  // global lock guard for AJAX requests that must not be combined with
  var globalAjaxLock = false;

  /**
   * Add flash message using javascript.
   * @param {string} message
   * @param {string} type info, warning, danger, success...
   */
  function addFlashMessage(message, type = "info") {
    const msg = $('<div class="alert alert-dismissible fade show"></div>');
    msg.text(message);
    if (type) {
      msg.addClass(`alert-${type}`);
    }
    msg.append(
      $(
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
      )
    );
    $("#flashes").append(msg);
  }

  /**
   * @returns jQuery object representing spiner icon wrapped as <i> element.
   */
  function createWaitingIcon() {
    return $('<i class="fa-solid fa-spinner fa-fw fa-spin-pulse"></i>');
  }

  /**
   * Show an error as dynamically added flash message.
   * @param {string} msg
   */
  function addAjaxError(msg) {
    addFlashMessage(msg, "danger");
  }

  /**
   *
   * @param {*} res
   */
  function postProcessAjax(res) {
    res.json().then((res) => {
      if (res.ok === false && res.error) {
        addAjaxError(res.error);
      }
      if (res.redirect) {
        location.assign(res.redirect);
      }
    });
  }

  /*
   * Handling AJAX form submissions.
   */
  $("form[data-ajax]").submit(function (ev) {
    ev.preventDefault();

    if (!globalAjaxLock) {
      globalAjaxLock = true;

      var form = $(this)[0];

      // block and animate submit button
      if (form.dataset.ajaxButton) {
        var button = $("#" + form.dataset.ajaxButton);
        var waitingIcon = createWaitingIcon();
        button.append(waitingIcon);
        button.attr("disabled", "disabled");
      }

      fetch(form.action, {
        body: new FormData(form),
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
      })
        .then(postProcessAjax)
        .finally(() => {
          globalAjaxLock = false;
          if (button) {
            button.removeAttr("disabled");
            waitingIcon.remove();
          }
        });
    }
  });

  /*
   * Handling AJAX hyperlinks
   */
  $("a[data-ajax]").click(function (ev) {
    ev.preventDefault();

    if (!globalAjaxLock) {
      globalAjaxLock = true;

      var link = $(this)[0];
      fetch(link.href, {
        method: link.dataset.ajax,
        headers: { "X-Requested-With": "XMLHttpRequest" },
      })
        .then(postProcessAjax)
        .finally(() => {
          globalAjaxLock = false;
        });
    }
  });
});
