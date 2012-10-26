mediaWiki.eventLog.dataModels = {
	openTask: {
		version: {
			type: 'number'
		},
		action: {
			type: 'string'
		},
		article: {
			type: 'string',
			optional: true
		},
		task: {
			type: 'string',
			optional: true
		},
		referrer: {
			type: 'string',
			optional: true
		},
		token: {
			type: 'string'
		},
		editcount: {
			type: 'number',
			optional: true
		},
		authenticated: {
			type: 'boolean'
		}
	}
};
