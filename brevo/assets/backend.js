jQuery(function ($) {
  if (typeof brevo !== "undefined") {
    console.log("initializing Brevo backend.js");
    brevo.init($);
  }
});

var brevo = new (function () {
  // Selectors for the settings fields
  this.apiKey = 'input[name="greyd_forms_interface_settings[brevo][api_key]"]';
  this.lists = 'input[name="greyd_forms_interface_settings[brevo][lists]"]';
  this.wrapper = "#brevo"; // The main container for the UI

  this.init = function ($) {
    this.$ = $; // Store jQuery instance

    // Event listener for API key changes
    $(this.apiKey).on("change keyup", this.updateState.bind(this));

    // Event listener for the "Get Lists" button
    $(this.wrapper).on("click", ".button.getLists", this.getLists.bind(this));

    // Initial check to see if we can fetch lists on page load
    this.getLists();
  };

  /**
   * Fetches contact lists from Brevo via AJAX.
   */
  this.getLists = function () {
    var apiKey = $(this.apiKey).val();

    // Don't proceed if there is no API key
    if (typeof apiKey === "undefined" || apiKey.length === 0) {
      return false;
    }

    $(
      this.wrapper +
        " .empty, " +
        this.wrapper +
        " .set, " +
        this.wrapper +
        " ._error",
    ).addClass("hidden");
    $(this.wrapper + " .loader")
      .parent()
      .addClass("loading")
      .css("display", "inline-block");

    $.ajax({
      type: "POST",
      url: local_brevo.ajaxurl,
      data: {
        action: local_brevo.action,
        _ajax_nonce: local_brevo.nonce,
        mode: "brevo",
        data: {
          api_key: apiKey,
        },
      },
      success: function (response) {
        // Hide loader
        $(this.wrapper + " .loader")
          .parent()
          .removeClass("loading")
          .css("display", "none");

        if (response.indexOf("success::") > -1) {
          // Success case
          var msg = response.split("success::");
          var lists = JSON.parse(msg[1]);

          // Store the lists JSON in the hidden input
          $(this.lists).val(msg[1]);

          // Populate the UI list
          var listContainer = $(this.wrapper + " .set ul");
          listContainer.html(""); // Clear previous list
          $.each(lists, function (id, name) {
            listContainer.append(
              "<li><strong>" + name + "</strong> (ID: " + id + ")</li>",
            );
          });

          // Show the populated list
          $(this.wrapper + " .set").removeClass("hidden");
        } else if (response.indexOf("error::") > -1) {
          // Error case
          var msg = response.split("error::");
          $(this.wrapper + " ._error .text").html(msg[1]);
          $(this.wrapper + " ._error").removeClass("hidden");
        }
      }.bind(this),
      error: function (XMLHttpRequest, textStatus, errorThrown) {
        // AJAX error case
        console.warn(errorThrown);
        $(this.wrapper + " .loader")
          .parent()
          .removeClass("loading")
          .css("display", "none");
        $(this.wrapper + " ._error .text").html(
          "An unexpected Error occurred.",
        );
        $(this.wrapper + " ._error").removeClass("hidden");
      }.bind(this),
    });
  };

  /**
   * Updates the UI state based on whether an API key is present.
   */
  this.updateState = function () {
    var apiKey = $(this.apiKey).val();

    if (typeof apiKey === "undefined" || apiKey.length === 0) {
      // No API Key: Show the "not ready" message
      $(this.wrapper + " .not_ready").removeClass("hidden");
      $(this.wrapper + " .ready").addClass("hidden");
    } else {
      // API Key present: Show the main UI
      $(this.wrapper + " .not_ready").addClass("hidden");
      $(this.wrapper + " .ready").removeClass("hidden");
    }

    // Reset the lists display to the empty state whenever the key changes
    $(this.lists).val("");
    $(this.wrapper + " .set, " + this.wrapper + " ._error").addClass("hidden");
    $(this.wrapper + " .empty").removeClass("hidden");
  };
})();
