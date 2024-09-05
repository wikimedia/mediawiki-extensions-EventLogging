const DEFAULT_EVENTGATE_ORIGIN = 'https://intake-analytics.wikimedia.org';
const DELAYED_SUBMIT_TIMEOUT = 5; // (s)

/**
 * @param {string} [origin]
 * @return {string}
 */
function getEventGateUrl( origin ) {
	const result = new URL( origin || DEFAULT_EVENTGATE_ORIGIN );

	result.pathname = '/v1/events';
	result.searchParams.set( 'hasty', 'true' );

	return result.toString();
}

/**
 * The default event submitter used by {@link MetricsClient}.
 *
 * This event submitter maintains an unbounded internal queue of events, which is drained every
 * 5 seconds or when the page is hidden. When the queue is drained, all events in the queue are
 * submitted to the event intake service in one request. The request is made using the
 * [Navigator: sendBeacon() method][0]. That is, the request is made asynchronously in the
 * background by the browser with no indication whether it succeeded.
 *
 * This event submitter is expected to be used in a browser. As well as the
 * [Navigator: sendBeacon() method][0], the event submitter requires the browser to support for the
 * [Page Visbility API][1].
 *
 * [0]: https://developer.mozilla.org/en-US/docs/Web/API/Navigator/sendBeacon
 * [1]: https://developer.mozilla.org/en-US/docs/Web/API/Page_Visibility_API
 *
 * @param {string} [eventGateOrigin] The origin of the EventGate event intake service to send
 *  events to. `https://intake-analytics.wikimedia.org` by default
 * @constructor
 */
function DefaultEventSubmitter( eventGateOrigin ) {
	this.eventGateUrl = getEventGateUrl( eventGateOrigin );

	/** @type {EventData[]} */
	this.events = [];

	const eventSubmitter = this;

	this.isDocumentUnloading = false;

	window.addEventListener( 'pagehide', () => {
		eventSubmitter.isDocumentUnloading = true;
	} );

	window.addEventListener( 'pageshow', () => {
		eventSubmitter.isDocumentUnloading = false;
	} );

	document.addEventListener( 'visibilitychange', () => {
		if ( document.hidden ) {
			eventSubmitter.doSubmitEvents();
		}
	} );

	this.delayedSubmitTimeoutID = null;
}

/**
 * Submits to the event intake service or enqueues the event for submission to the event
 * intake service.
 *
 * @param {EventData} eventData
 */
DefaultEventSubmitter.prototype.submitEvent = function ( eventData ) {
	this.events.push( eventData );

	if ( this.isDocumentUnloading ) {
		this.doSubmitEvents();
	} else {
		this.doDelayedSubmit();
	}

	this.onSubmitEvent( eventData );
};

/**
 * Submits all queued events to the event intake service immediately and clears the queue.
 *
 * @ignore
 */
DefaultEventSubmitter.prototype.doSubmitEvents = function () {
	if ( this.events.length ) {
		try {
			navigator.sendBeacon(
				this.eventGateUrl,
				JSON.stringify( this.events )
			);
		} catch ( e ) {
			// Some browsers throw when sending a beacon to a blocked URL (by an adblocker, for
			// example). Some browser extensions remove Navigator#sendBeacon() altogether. See also:
			//
			// 1. https://phabricator.wikimedia.org/T86680
			// 2. https://phabricator.wikimedia.org/T273374
			// 3. https://phabricator.wikimedia.org/T308311
			//
			// Regardless, ignore all errors for now.
			//
			// TODO (phuedx, 2024/09/09): Instrument this!
		}
	}

	this.events = [];
	this.delayedSubmitTimeoutID = null;
};

/**
 * Schedules a call to {@link DefaultEventSubmitter#doSubmitEvents} in 5 seconds, if a call is not
 * already scheduled.
 *
 * @ignore
 */
DefaultEventSubmitter.prototype.doDelayedSubmit = function () {
	if ( this.delayedSubmitTimeoutID ) {
		return;
	}

	const eventSubmitter = this;

	this.delayedSubmitTimeoutID = setTimeout(
		() => {
			eventSubmitter.doSubmitEvents();
		},
		DELAYED_SUBMIT_TIMEOUT * 1000
	);
};

/**
 * Called when an event is enqueued for submission to the event intake service.
 *
 * @param {EventData} eventData
 */
DefaultEventSubmitter.prototype.onSubmitEvent = function ( eventData ) {
	// eslint-disable-next-line no-console
	console.info( 'Submitted the following event:', eventData );
};

module.exports = {
	DefaultEventSubmitter,
	getEventGateUrl
};
