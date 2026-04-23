/**
 * Prevent divi/font group presets from cascading into checkout modules.
 *
 * Two-part fix:
 * 1. Patch any ALREADY CACHED results (if getGroupPresetDefaultAttr was called before us)
 * 2. Wrap the function for FUTURE calls
 */
(function() {
	var mu = window.divi && window.divi.moduleUtils;
	if (!mu || !mu.getGroupPresetDefaultAttr) {
		return;
	}

	var modules = ['wfacp/checkout-form', 'wfacp/mini-cart'];
	var original = mu.getGroupPresetDefaultAttr;

	function stripDiviFontEntries(result) {
		if (!result || typeof result !== 'object') return result;
		var keys = Object.keys(result);
		for (var i = 0; i < keys.length; i++) {
			if (result[keys[i]] && result[keys[i]].groupName === 'divi/font') {
				delete result[keys[i]];
			}
		}
		return result;
	}

	// Part 1: Fix already-cached results by calling the original (returns from cache)
	// and mutating in place. The cache stores object references, so mutation propagates.
	for (var i = 0; i < modules.length; i++) {
		try {
			var cached = original({ name: modules[i] });
			stripDiviFontEntries(cached);
		} catch(e) { /* module not yet cached, that's fine */ }
	}

	// Part 2: Wrap for future calls
	mu.getGroupPresetDefaultAttr = function(module) {
		var result = original.apply(this, arguments);
		var name = module && module.name;
		if (name === 'wfacp/checkout-form' || name === 'wfacp/mini-cart') {
			stripDiviFontEntries(result);
		}
		return result;
	};
})();
