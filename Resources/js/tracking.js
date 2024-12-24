"use strict";

class FactorialTracking {
  constructor() {
    this.reportPresenceTime = this.reportPresenceTime.bind(this);
    this.getPageUrl = this.getPageUrl.bind(this);
    this.getScrollPercentage = this.getScrollPercentage.bind(this);
    this.startTime = this.getCurrentTimestamp();
    this.lastStartTime = this.getCurrentTimestamp();
    this.sessionId = crypto.randomUUID();
  }
  getCurrentTimestamp() {
    return Date.now();
  }
  getPageUrl() {
    return window.location.href;
  }
  getScrollPercentage() {
    var scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
    var scrollHeight = document.documentElement.scrollHeight || document.body.scrollHeight;
    var clientHeight = document.documentElement.clientHeight || window.innerHeight;
    if (scrollHeight <= clientHeight) {
      return 100; // Return 100 if the content is not taller than the viewport
    }
    return Math.min(100, scrollTop / (scrollHeight - clientHeight) * 100);
  }
  reportPresenceTime() {
    if (typeof mt === "undefined") {
      return;
    }
    var now = this.getCurrentTimestamp();
    var timeSpent = document.visibilityState === "visible" ? now - this.startTime : 0;
    var timeRange = {
      start: new Date(this.lastStartTime).toISOString(),
      end: new Date(now).toISOString(),
      spent: timeSpent,
      scrollPercentage: this.getScrollPercentage(),
      pageUrl: this.getPageUrl(),
      session: this.sessionId
    };
    MauticJS.makeCORSRequest('POST', '{$mauticBaseUrl}mtc/page', timeRange, function (data) {
      console.info(data);
    });

    //mt('send', 'pageview', timeRange);
  }
}
window.addEventListener('load', function () {
  console.info('starting timer');
  document.factorial = new FactorialTracking();
  // Periodic reporting
  var REPORT_INTERVAL = 10000; // 60 seconds
  setInterval(document.factorial.reportPresenceTime, REPORT_INTERVAL);

  // Report when the user leaves
  window.addEventListener("beforeunload", document.factorial.reportPresenceTime);
});