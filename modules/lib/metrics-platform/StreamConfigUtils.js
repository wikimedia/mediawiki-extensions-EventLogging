/**
 * @param {?StreamSamplingConfig} sample
 * @return {boolean}
 */
function isValidSample( sample ) {
	return !!(
		sample &&
		sample.unit && sample.rate &&
		sample.rate >= 0 && sample.rate <= 1
	);
}

module.exports = {
	isValidSample: isValidSample
};
