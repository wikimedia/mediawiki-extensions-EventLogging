/**
 * @constructor
 * @class EventSubmitter
 * @classdesc Adapts the background queue for the JavaScript Metrics Platform Client (JS MPC).
 *
 *  The class is temporary. It will be removed after Data Products have tested and verified the use
 *  of the `DefaultEventSubmitter` class provided by the JS MPC soon (see [T375749][1]).
 *
 *  Before [T375749][1]:
 *  [<img src="./images/before.png" width="100%">][2]
 *
 *  After [T375749][1]:
 *  [<img src="./images/after.png" width="100%">][3]
 *
 *  See [Metrics Platform](https://wikitech.wikimedia.org/wiki/Metrics_Platform) on Wikitech.
 *
 *  [0]: https://gitlab.wikimedia.org/repos/data-engineering/metrics-platform/-/tree/65abcf66a5327a60b541e9d1729de86895526060/js
 *  [1]: https://phabricator.wikimedia.org/T375749
 *  [2]: https://mermaid.live/edit#pako:eNqFUkFqwzAQ_IrYU0KdPECHQHF6aKmh4EOh-KLYE0XUklx5lbaE_L2K3TRNwVS3HWZ2hxkdqPYNSFKPtwhXY22UDspWTqS3xh6t7xDEYrW6EXd7OH70WhunpbDvS3wDyz5urOHZfJT95o3Kh1IUT7kUBTiYus9bkxhSXstG0knw51KBxqhn82qu5PeOkayy8U5KDc59Aj74lhNnExn9lJ0fN_-cHaZysMgIZ7cDOpuLid3TGcGlhCOmXC0uaUtBGVkEq0yTqjmcBBXxDhYVJdvUYKtiyxVV7pioKrIvP11NkkNERrFrFJ-bJLlVbZ_QTrkX7y9zCpV9KMb6h1-QUfBR78Y1xy-X6bVX
 *  [3]: https://mermaid.live/edit#pako:eNp1kcFqwzAQRH9F7Kmhdj5Ah0Cxe2ipoeBDoPiiWBtFxJJcaZW2hPx7FatJmkB002ieZtjdQ-8kAoeAnxFtj7UWygvTWZZOjTsc3IielYvFI3veoaU3p5S2ijPzNcc_YR7iymh6mGXsvy-Try1r3ivOGiSv-1ANOjk4v8ay6QjcJDUotVjqrb7CXyxhqkraWc4VUuWS8E1PlDyrSBju1Tm3uY0tzy81rkUcaCLbqSShP_Wd1Hufl5ehcQYFGPRGaJkmvD8CHdAGDXaQ0kHmlA46e0hWEcm1P7YHTj5iAXGUgk4LAb4WQ0jqKOyHc5d7mg053-QtTssswLuoNvmbwy-FbqER
 *
 * @param {string} eventGateUri
 * @param {Function} enqueue
 * @param {boolean} isDebugMode
 */
function EventSubmitter( eventGateUri, enqueue, isDebugMode ) {
	this.eventGateUri = eventGateUri;
	this.enqueue = enqueue;
	this.isDebugMode = isDebugMode;
}

/**
 * Enqueues the event to be submitted to the event ingestion service.
 *
 * @param {Object} eventData
 */
EventSubmitter.prototype.submitEvent = function ( eventData ) {
	const eventGateUri = this.eventGateUri;

	if ( eventGateUri ) {
		mw.eventLog.enqueue( () => {
			try {
				navigator.sendBeacon(
					eventGateUri,
					JSON.stringify( eventData )
				);
			} catch ( e ) {
				// Ignore. See T86680, T273374, and T308311.
			}
		} );

		this.onSubmitEvent( eventData );
	}
};

/**
 * Notifies the user that an event has been enqueued for submission if they
 * have enabled
 *
 * @param {Object} eventData
 */
EventSubmitter.prototype.onSubmitEvent = function ( eventData ) {
	if ( this.isDebugMode ) {
		mw.track(
			'eventlogging.eventSubmitDebug',
			{ streamName: eventData.meta.stream, eventData: eventData }
		);
	}
};

module.exports = EventSubmitter;
