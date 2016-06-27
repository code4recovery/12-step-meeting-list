var gulp 		= require('gulp');
var gutil 		= require('gulp-util');
var notify 		= require('gulp-notify');
var sass 		= require('gulp-ruby-sass');
var autoprefix 	= require('gulp-autoprefixer');
var minifyCSS 	= require('gulp-minify-css');
var rename		= require('gulp-rename');
var include		= require('gulp-include');
var uglify		= require('gulp-uglify');
var importCss	= require('gulp-import-css');

gulp.task('public-css', function(){
	return gulp.src('css/public.scss')
		.pipe(sass())
		.on('error', handleError)
	    .pipe(importCss())
		.pipe(autoprefix('last 3 version'))
		.pipe(minifyCSS({keepSpecialComments:0}))
        .pipe(rename({suffix: '.min'}))
		.pipe(gulp.dest('css/'));
});

gulp.task('admin-css', function(){
	return gulp.src('css/admin.scss')
		.pipe(sass())
		.on('error', handleError)
	    .pipe(importCss())
		.pipe(autoprefix('last 3 version'))
		.pipe(minifyCSS({keepSpecialComments:0}))
        .pipe(rename({suffix: '.min'}))
		.pipe(gulp.dest('css/'));
});

gulp.task('public-js', function(){
	return gulp.src('js/public.js')
		.pipe(include())
		.pipe(uglify({mangle: false}))
        .pipe(rename({suffix: '.min'}))
		.pipe(gulp.dest('js/'));
});

gulp.task('admin-js', function(){
	return gulp.src('js/admin.js')
		.pipe(include())
		.pipe(uglify({mangle: false}))
        .pipe(rename({suffix: '.min'}))
		.pipe(gulp.dest('js/'));
});

gulp.task('watch', function(){
	gulp.watch('css/public.scss', ['public-css']);
	gulp.watch('css/admin.scss',  ['admin-css']);
	gulp.watch('js/public.js',    ['public-js']);
	gulp.watch('js/admin.js',     ['admin-js']);
});

gulp.task('default', ['public-css', 'admin-css', 'public-js', 'admin-js', 'watch']);

//seems wrong to just pick one file when it could be either
function handleError(err) {
	gulp.src('css/public.scss').pipe(notify(err));
	this.emit('end');
}