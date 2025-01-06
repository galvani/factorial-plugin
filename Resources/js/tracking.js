"use strict";

(() => {
  class FactorialTracking {
    constructor() {
      var _ref, _config$debug;
      let config = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
      this.reportPresenceTime = this.reportPresenceTime.bind(this);
      this.handleVisibilityChange = this.handleVisibilityChange.bind(this);
      this.handleUnload = this.handleUnload.bind(this);
      this.lastVisibleTime = document.visibilityState === 'visible' ? Date.now() : null;
      this.isVisible = document.visibilityState === 'visible';
      this.pendingTime = 0;
      this.debug = (_ref = (_config$debug = config.debug) !== null && _config$debug !== void 0 ? _config$debug : window.FACTORIAL_DEBUG) !== null && _ref !== void 0 ? _ref : false; // Ensure debug mode is configurable

      this.sessionId = this.generateSessionId();
      document.addEventListener('visibilitychange', this.handleVisibilityChange);
      this.initializeReporting();
      this.log('Tracking initialized', {
        isVisible: this.isVisible,
        lastVisibleTime: this.lastVisibleTime
      });
    }
    log(message) {
      let data = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};
      if (this.debug && typeof console !== "undefined" && typeof console.log === "function") {
        console.log('[FactorialTracking]', message, data);
      }
    }
    generateSessionId() {
      try {
        return crypto.randomUUID();
      } catch (e) {
        return "".concat(Date.now(), "-").concat(Math.random().toString(36).substr(2, 9));
      }
    }
    handleVisibilityChange() {
      const now = Date.now();
      const newVisibilityState = document.visibilityState === 'visible';
      this.log('Visibility changed', {
        from: this.isVisible,
        to: newVisibilityState,
        timestamp: now
      });
      if (newVisibilityState) {
        this.lastVisibleTime = now;
        this.isVisible = true;
      } else {
        if (this.isVisible && this.lastVisibleTime) {
          const timeSpent = now - this.lastVisibleTime;
          this.pendingTime += Math.max(0, timeSpent);
          this.log('Time added to pending', {
            timeSpent,
            pendingTime: this.pendingTime
          });
        }
        this.lastVisibleTime = null;
        this.isVisible = false;
      }
    }
    getPageUrl() {
      return window.location.href;
    }
    getScrollPercentage() {
      try {
        const doc = document.documentElement || document.body;
        const scrollTop = window.pageYOffset || doc.scrollTop;
        const scrollHeight = doc.scrollHeight;
        const clientHeight = window.innerHeight || doc.clientHeight;
        if (scrollHeight <= clientHeight) return 100;
        return Math.min(100, Math.round(scrollTop / (scrollHeight - clientHeight) * 100));
      } catch (e) {
        return 0;
      }
    }
    convertToSeconds(milliseconds) {
      return Math.round(milliseconds / 1000 * 10) / 10;
    }
    handleUnload() {
      const now = Date.now();
      if (this.isVisible && this.lastVisibleTime) {
        const visibleTime = now - this.lastVisibleTime;
        this.pendingTime += Math.max(0, visibleTime);
        this.log('Unload visible time added', {
          visibleTime,
          pendingTime: this.pendingTime
        });
      }
      if (this.pendingTime > 0 && this.pendingTime < 86400000) {
        // Cap at 24 hours
        this.sendReport(this.pendingTime, now);
      }
    }
    async reportPresenceTime() {
      if (typeof MauticJS === "undefined") return;
      try {
        const now = Date.now();
        let timeToReport = 0;
        if (this.isVisible && this.lastVisibleTime) {
          timeToReport = now - this.lastVisibleTime;
          this.lastVisibleTime = now;
        }
        timeToReport += this.pendingTime;
        if (timeToReport > 0 && timeToReport < 86400000) {
          // Cap at 24 hours
          this.sendReport(timeToReport, now);
          this.pendingTime = 0; // Clear after successful report
        } else {
          this.log('Skipped reporting due to invalid time', {
            timeToReport
          });
        }
      } catch (e) {
        console.error('Error in reportPresenceTime:', e);
      }
    }
    sendReport(timeToReport, now) {
      const timeInSeconds = this.convertToSeconds(timeToReport);
      const timeRange = {
        start: new Date(now - timeToReport).toISOString(),
        end: new Date(now).toISOString(),
        spent: timeInSeconds,
        scrollPercentage: this.getScrollPercentage(),
        pageUrl: this.getPageUrl(),
        session: this.sessionId
      };
      this.log('Sending report', timeRange);
      MauticJS.makeCORSRequest('POST', '{$mauticBaseUrl}mtc/page', timeRange, function (data) {
        console.info(data);
      }, function (error) {
        console.error('Error in sendReport:', error);
      });
    }

    initializeReporting() {
      setInterval(this.reportPresenceTime, 10000);
      window.addEventListener("beforeunload", this.handleUnload);
    }
  }

  // Initialize tracking
  document.factorial = new FactorialTracking({
    debug: {$debug} // Enable debug mode
  });
})();