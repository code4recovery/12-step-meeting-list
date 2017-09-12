var elixir = require('laravel-elixir');

elixir(function(mix) {
	mix
	.sass('./assets/src/admin.scss', './assets/css/admin.css')
	.styles([
		'./node_modules/timepicker/jquery.timepicker.min.css',
		'./assets/css/admin.css'
	], './assets/css/admin.min.css')
	.sass('./assets/src/public.scss', './assets/css/public.min.css')
	.scripts([
		'./node_modules/timepicker/jquery.timepicker.min.js',
		'./node_modules/typeahead.js/dist/typeahead.bundle.js',
		'./assets/src/admin.js',
	], './assets/js/admin.min.js')
	.scripts([
		'./node_modules/mark.js/dist/jquery.mark.js',
		'./node_modules/typeahead.js/dist/typeahead.bundle.js',
		'./assets/js/bootstrap.dropdown.js',
		'./assets/src/public.js'
	], './assets/js/public.min.js');
});
