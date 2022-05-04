$(() => {
  // global lock guard for AJAX requests that must not be combined with
  var globalAjaxLock = false;

  /**
   *
   * @param {*} msg
   */
  function addAjaxError(msg) {}

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
  $("form.ajax").submit(function (ev) {
    ev.preventDefault();

    if (!globalAjaxLock) {
      globalAjaxLock = true;

      var form = $(this)[0];
      fetch(form.action, {
        body: new FormData(form),
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
      })
        .then(postProcessAjax)
        .finally(() => {
          globalAjaxLock = false;
        });
    }
  });

  /*
   * Handling AJAX hyperlinks
   */
  $("a.ajax.post").click(function (ev) {
    ev.preventDefault();

    if (!globalAjaxLock) {
      globalAjaxLock = true;

      var link = $(this)[0];
      fetch(link.href, {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
      })
        .then(postProcessAjax)
        .finally(() => {
          globalAjaxLock = false;
        });
    }
  });
});
