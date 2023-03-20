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
	var timer = null;
	var pendingCallbacks = [];
	var discardingPage;
	var queue = this;

	intervalSecs = intervalSecs || 30;

	/**
	 * Add a callback to the queue, to be flushed when the timer runs out.
	 *
	 * @param {Function} fn Callback to add
	 */
	queue.add = function ( fn ) {
		if ( discardingPage ) {
			// If we're in the middle of discarding this page, every add will
			// immediately run the callback to avoid losing data.
			fn();
			return;
		}
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

	// If the user navigates to another page or closes the tab/window/application,
	// then send any queued events.
	// Listen to the pagehide and visibilitychange events as Safari 12 and Mobile Safari 11
	// don't appear to support the Page Visbility API yet.
	window.addEventListener( 'pagehide', function () {
		// Record when the page is in the process of being discarded.
		discardingPage = true;
		queue.flush();
	} );

	// If the page was just suspended and gets reactivated, re-enable queuing.
	window.addEventListener( 'pageshow', function () {
		discardingPage = false;
	} );

	// https://developer.mozilla.org/en-US/docs/Web/API/Document/onvisibilitychange
	document.addEventListener( 'visibilitychange', function () {
		if ( document.hidden ) {
			queue.flush();
		}
	} );

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
