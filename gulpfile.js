var elixir = require('laravel-elixir');

elixir(function(mix) {
	mix
	.sass('../../../assets/src/admin.scss', 'assets/css/admin.min.css')
	.sass('../../../assets/src/public.scss', 'assets/css/public.min.css')
	.scripts('../../../assets/src/admin.js', 'assets/js/admin.min.js')
	.scripts([
		'../../../node_modules/mark.js/dist/jquery.mark.js',
		'../../../assets/src/public.js'
	], 'assets/js/public.min.js');
});
