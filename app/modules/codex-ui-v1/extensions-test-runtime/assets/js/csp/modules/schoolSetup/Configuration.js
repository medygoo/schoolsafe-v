/**
 * Configuration program (School module) JS
 *
 * @since 12.5
 *
 * @package RosarioSIS
 */

csp.modules.schoolSetup.configuration = {
	// Grade posting date inputs are required when "Graded" is checked.
	passwordStrengthBarsScore: function() {
		var score = this.value;

		$(this).nextAll('.password-strength-bars').children('span').each(function(i, el) {
			$(el).css('visibility', (i <= score ? 'visible' : 'hidden'));
		});
	},
	ready: function() {
		var $input = $('input[name="values[config][PASSWORD_STRENGTH]"');

		// @link https://stackoverflow.com/questions/3630054/how-do-i-pass-the-this-context-to-a-function#3630076
		csp.modules.schoolSetup.configuration.passwordStrengthBarsScore.call($input[0]);

		$input.on('change', csp.modules.schoolSetup.configuration.passwordStrengthBarsScore);
	}
}

$(csp.modules.schoolSetup.configuration.ready);
