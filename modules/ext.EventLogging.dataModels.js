mediaWiki.eventLog.dataModels = {
	openTask: {
		version: {
			type: 'number',
			required: true
		},
		action: {
			type: 'string',
			required: true
		},
		article: {
			type: 'string'
		},
		task: {
			type: 'string'
		},
		referrer: {
			type: 'string'
		},
		token: {
			type: 'string',
			required: true
		},
		editcount: {
			type: 'number'
		},
		authenticated: {
			type: 'boolean',
			required: true
		}
	}
};
