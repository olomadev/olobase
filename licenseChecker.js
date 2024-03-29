
import axios from "axios";
import messages from "./locale.json";

/**
 * Oloma Dev.
 * 
 * [olobase] <https://github.com/olomadev/olobase>
 *
 * Copyright (c) 2022-2024, Oloma Software.
 *
 * https://oloma.dev/end-user-license-agreement
 */
export default class LicenseChecker {

  constructor(i18n, env) {
    this.env = env;
    this.lang = "en";
    this.i18n = i18n;
    this.interval = 60 * 10000;  // every 10 minute
    this.versionId = this.generateUid();
    if (typeof i18n.global.locale.value !== "undefined") {
      this.lang = i18n.global.locale.value;
    }
  }

  check() {
    let error = null;
    if (typeof this.env.VITE_LICENSE_KEY == "undefined" || this.env.VITE_LICENSE_KEY == "") {
      error = this.trans("Oloma configuration error") + this.trans("Please provide a license key");
      alert(error)
      return;
    }
    const origin = window.location.origin;
    const isProd = this.checkDomain(origin);

    if (isProd) { // check for production server
      const metaLicenseTag = document.querySelector("meta[name='ol:key']")
      if (! metaLicenseTag) {
          error = this.trans("Oloma configuration error") + this.trans("Meta license key undefined");
          alert(error);
          return;
      }
      return;
    }
    const lVal = localStorage.getItem(this.getVersionId());
    let Self = this;
    if (!lVal) {
        this.sendRequest();
    }
    setInterval(
        function() {
          localStorage.removeItem(Self.getVersionId()); // remove old version id
          Self.generateUid();
          Self.sendRequest();
        }, 
        Self.interval
    );
    return error;
  }

  checkDomain(str) {
     const pattern = new RegExp('^https:\\/\\/' + // protocol
        '((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.)+[a-z]{2,}|' + // domain name
        '((\\d{1,3}\\.){3}\\d{1,3}))' + // OR ip (v4) address
        '(\\:\\d+)?(\\/[-a-z\\d%_.~+]*)*' + // port and path
        '(\\?[;&a-z\\d%_.~+=-]*)?' + // query string
        '(\\#[-a-z\\d_]*)?$', 'i'); // fragment locator

    return !!pattern.test(str);
  }

  trans(text) {
    if (typeof messages[this.lang][text] == "undefined") {
      return text
    }
    return messages[this.lang][text];
  }

  getVerifyUrl() {
    return "https://license.oloma.dev";
  }

  generateVersionId() {
    this.versionId = this.generateUid();
  }

  getVersionId() {
    return this.versionId;
  }

  sendRequest() {
    const Self = this;
    axios
      .get(this.getVerifyUrl()  + "/?key=" + this.env.VITE_LICENSE_KEY + "&lang=" + this.lang)
      .then(function (response) {
        if (! response) {
          // let's show connection error in background
          // 
          alert(Self.trans("Oloma configuration error") + Self.trans("Failed to connect to license activation server please make sure you are connected to the internet"));
          return;
        }
        if (response && 
          response["data"] && 
          response["data"]["success"]) {
          localStorage.setItem(Self.getVersionId(), 1);
        } else if (response && 
          response["data"] && 
          response["data"]["error"]) {
          alert(Self.trans("Oloma configuration error") + response.data.error);
        }
    });
  }

  generateUid(uppercase = false) {
    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function (c) {
      var r = (Math.random() * 16) | 0,
        v = c == "x" ? r : (r & 0x3) | 0x8;
      let uuid = v.toString(16);
      return (uppercase) ? uuid.toUpperCase() : uuid;
    });
  }

}