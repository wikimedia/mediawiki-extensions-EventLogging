'use strict';

/**
 * This class enables pending callbacks to fire all at once, on a
 * synchronized schedule instead of one by one.  This is useful to group
 * operations that wake up expensive resources such as a mobile radio.
 *
 * How to use:
 *
 *     enqueue = ( new BackgroundQueue() ).add;
 *     ...
 *     enqueue( callback );
 *     // callbacks will be fired in batches every 30 seconds (default)
 *
 * @class mw.eventLog.BackgroundQueue
 * @constructor
 * @param {number} [intervalSecs=30] seconds to wait before calling callbacks
 */
module.exports = function BackgroundQueue( intervalSecs ) {
	var timer = null,
		pendingCallbacks = [],
		getVisibilityChanged,
		visibilityEvent,
		queue = this;

	intervalSecs = intervalSecs || 30;

	/**
	 * Add a callback to the queue, to be flushed when the timer runs out.
	 *
	 * @param {Function} callback to add
	 */
	queue.add = function ( fn ) {
		pendingCallbacks.push( fn );
		if ( !timer ) {
			timer = setTimeout( queue.flush, intervalSecs * 1000 );
		}
	};

	/**
	 * Manually execute all the callbacks, same as if the timer runs out.
	 */
	queue.flush = function () {
		if ( timer ) {
			clearTimeout( timer );
			timer = null;
		}
		while ( pendingCallbacks.length ) {
			pendingCallbacks.shift()();
		}
	};

	// Cross referencing to support all browsers we consider "modern":
	// 1. https://developer.mozilla.org/en-US/docs/Web/API/Document/onvisibilitychange#Browser_compatibility
	// 2. https://www.mediawiki.org/wiki/Compatibility#Browsers
	// no longer needed: document.msHidden / msvisibilitychange
	if ( typeof document.hidden !== 'undefined' ) {
		getVisibilityChanged = function () {
			return document.hidden;
		};
		visibilityEvent = 'visibilitychange';
	} else if ( typeof document.mozHidden !== 'undefined' ) {
		getVisibilityChanged = function () {
			return document.mozHidden;
		};
		visibilityEvent = 'mozvisibilitychange';
	} else if ( typeof document.webkitHidden !== 'undefined' ) {
		getVisibilityChanged = function () {
			return document.webkitHidden;
		};
		visibilityEvent = 'webkitvisibilitychange';
	}

	// If the user navigates to another page or closes the tab/window/application,
	// then send any queued events.
	// Listen to the pagehide and visibilitychange events as Safari 12 and Mobile Safari 11
	// don't appear to support the Page Visbility API yet.
	window.addEventListener( 'pagehide', queue.flush );

	if ( getVisibilityChanged ) {
		document.addEventListener( visibilityEvent, function () {
			if ( getVisibilityChanged() ) {
				queue.flush();
			}
		} );
	}

	// Not allowed outside unit tests
	if ( window.QUnit ) {
		queue.getTimer = function () {
			return timer;
		};
		queue.getCallbacks = function () {
			return pendingCallbacks;
		};
	}
};
