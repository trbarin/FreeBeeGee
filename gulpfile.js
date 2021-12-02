/**
 * @copyright 2021 Markus Leupold-Löwenthal
 *
 * @license This file is part of FreeBeeGee.
 *
 * FreeBeeGee is free software: you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * FreeBeeGee is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU Affero General Public License for details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with FreeBeeGee. If not, see <https://www.gnu.org/licenses/>.
 */

const p = require('./package.json')
const gulp = require('gulp')
const rnd = Math.floor(Math.random() * 10000000)

const dirs = {
  build: 'dist',
  site: 'dist/' + p.name,
  docs: 'dist/docs/',
  cache: '.cache'
}

gulp.task('clean', () => {
  const del = require('del')
  return del([
    [dirs.site] + '/**/*',
    [dirs.site] + '/**/.*',
    [dirs.build] + '/*zip',
    [dirs.build] + '/*gz'
  ])
})

gulp.task('clean-cache', () => {
  const del = require('del')
  return del([
    dirs.cache
  ])
})

// --- testing targets ---------------------------------------------------

gulp.task('test-js', () => {
  const standard = require('gulp-standard')

  return gulp.src(['src/js/**/*js'])
    .pipe(gulp.dest('/tmp/gulp-pre'))
    .pipe(gulp.dest('/tmp/gulp-post'))
    .pipe(standard())
    .pipe(standard.reporter('default', {
      breakOnError: true,
      quiet: true
    }))
})

gulp.task('test-sass', () => {
  const sassLint = require('gulp-sass-lint')
  return gulp.src(['src/scss/**/*.s+(a|c)ss'])
    .pipe(sassLint({ configFile: '.sass-lint.yml' }))
    .pipe(sassLint.format())
    .pipe(sassLint.failOnError())
})

gulp.task('test-php', () => {
  const phpcs = require('gulp-phpcs')
  const phplint = require('gulp-phplint')

  return gulp.src([
    'src/php/**/*.php'
  ])
    .pipe(phplint('', { skipPassedFiles: true }))
    .pipe(phpcs({
      bin: 'tools/phpcs.phar',
      standard: 'PSR12',
      colors: 1,
      warningSeverity: 0
    }))
    .pipe(phpcs.reporter('log'))
    .pipe(phpcs.reporter('fail'))
})

gulp.task('tests', gulp.series('test-sass', 'test-js', 'test-php'))

// --- docs targets ------------------------------------------------------------

gulp.task('docs-js', () => {
  const jsdoc = require('gulp-jsdoc3')

  return gulp.src([
    'src/**/*.js'
  ], { read: false })
    .pipe(jsdoc())
})

// --- build targets -----------------------------------------------------------

function replace (pipe) {
  const repl = require('gulp-replace')
  return pipe
    .pipe(repl('$NAME$', p.name, { skipBinary: true }))
    .pipe(repl('$VERSION$', p.version, { skipBinary: true }))
    .pipe(repl('$ENGINE$', p.versionEngine, { skipBinary: true }))
    .pipe(repl('$CODENAME$', p.codename, { skipBinary: true }))
    .pipe(repl('$BUILD$', rnd, { skipBinary: true }))
    .pipe(repl('$DESCRIPTION$', p.description, { skipBinary: true }))
    .pipe(repl('$COLOR$', p.color, { skipBinary: true }))
    .pipe(repl('$URL$', p.homepage, { skipBinary: true }))
    .pipe(repl('$CACHE$', p.cache, { skipBinary: true }))
}

gulp.task('fonts', () => {
  return replace(gulp.src([
    'src/fonts/*/*woff2'
  ]))
    .pipe(gulp.dest(dirs.site + '/fonts/'))
})

function svg2png (svg, outname, size) {
  const svg2png = require('gulp-svg2png')
  const image = require('gulp-image')
  const rename = require('gulp-rename')
  const changed = require('gulp-changed')
  return gulp.src(svg)
    .pipe(rename(outname))
    .pipe(changed(dirs.cache + '/favicon'))
    .pipe(svg2png({ width: size, height: size }))
    .pipe(image({
      optipng: ['-i 1', '-strip all', '-fix', '-o7', '-force'],
      pngquant: ['--speed=1', '--force', 256],
      zopflipng: ['-y', '--lossy_8bit', '--lossy_transparent']
    }))
}

// convert -background none icon.svg -define icon:auto-resize=32,16 favicon.ico
gulp.task('favicon', gulp.series(gulp.parallel(
  // step 1 - generate cached icons
  () => {
    return gulp.src([
      'src/favicon/icon.svg',
      'src/favicon/favicon.ico', // note: .ico is not (re)generated yet
      'src/favicon/manifest.webmanifest'
    ])
      .pipe(gulp.dest(dirs.cache + '/favicon'))
  }, () => {
    return svg2png('src/favicon/icon.svg', '512.png', 512)
      .pipe(gulp.dest(dirs.cache + '/favicon'))
  }, () => {
    return svg2png('src/favicon/icon.svg', 'apple-touch-icon.png', 180)
      .pipe(gulp.dest(dirs.cache + '/favicon'))
  }, () => {
    return svg2png('src/favicon/icon.svg', '192.png', 192)
      .pipe(gulp.dest(dirs.cache + '/favicon'))
  }
), () => {
  // step 2 - use cached icons
  return gulp.src([
    dirs.cache + '/favicon/**/*'
  ])
    .pipe(gulp.dest(dirs.site))
}))

gulp.task('js-vendor', () => {
  const concat = require('gulp-concat')

  return replace(gulp.src([
    'node_modules/@popperjs/core/dist/umd/popper.min.js',
    'node_modules/bootstrap/dist/js/bootstrap.bundle.min.js'
  ]))
    .pipe(concat('vendor.js'))
    .pipe(gulp.dest(dirs.site))
})

gulp.task('js-main', gulp.series('test-js', () => {
  const browserify = require('browserify')
  const babelify = require('babelify')
  const source = require('vinyl-source-stream')

  return replace(browserify([
    'src/js/app.mjs',
    'src/js/main.mjs',
    'src/js/api/index.mjs',
    'src/js/state/index.mjs',
    'src/js/lib/FreeDOM.mjs',
    'src/js/lib/utils.mjs',
    'src/js/view/modal.mjs',
    'src/js/view/screen.mjs',
    'src/js/view/create/index.mjs',
    'src/js/view/error/index.mjs',
    'src/js/view/join/index.mjs',

    'src/js/view/room/index.mjs',
    'src/js/view/room/hotkeys.mjs',
    'src/js/view/room/mouse.mjs',
    'src/js/view/room/sync.mjs',
    'src/js/view/room/tabletop/index.mjs',
    'src/js/view/room/tabletop/tabledata.mjs',
    'src/js/view/room/modal/edit.mjs',
    'src/js/view/room/modal/help.mjs',
    'src/js/view/room/modal/inactive.mjs',
    'src/js/view/room/modal/library.mjs'
  ], {
    paths: []
  }).transform(babelify.configure({
    presets: ['@babel/preset-env']
  })).bundle()
    .pipe(source('main.js')))
    .pipe(gulp.dest(dirs.site))
}))

gulp.task('sass', gulp.series('test-sass', () => {
  const sass = require('gulp-sass')(require('sass'))
  const concat = require('gulp-concat')
  const autoprefixer = require('gulp-autoprefixer')
  const sourcemaps = require('gulp-sourcemaps')
  const repl = require('gulp-replace')

  return replace(gulp.src([
    'src/scss/style.scss'
  ]))
    .pipe(sourcemaps.init())
    .pipe(concat('style.css'))
    .pipe(sass({ outputStyle: 'compressed' }))
    .pipe(repl('$CACHE$', p.cache, { skipBinary: true }))
    .pipe(autoprefixer())
    .pipe(sourcemaps.write('./'))
    .pipe(gulp.dest(dirs.site))
}))

gulp.task('php', gulp.series('test-php', () => {
  return replace(gulp.src([
    'src/php/**/*php',
    'src/php/.htaccess*',
    'src/php/**/*.json'
  ]))
    .pipe(gulp.dest(dirs.site + '/api'))
}))

gulp.task('html', () => {
  return replace(gulp.src([
    'src/html/**/*.html',
    'src/misc/.htaccess*',
    'src/misc/robots.txt',
    'src/misc/README.md',
    'docs/INSTALL.md',
    'LICENSE.md'
  ]))
    .pipe(gulp.dest(dirs.site))
})

gulp.task('img', gulp.series(() => {
  // step 1 - optimize changed images into cache
  const image = require('gulp-image')
  const changed = require('gulp-changed')

  return gulp.src([
    'src/img/**/*.svg',
    'src/img/**/*.jpg',
    'src/img/**/*.png'
  ])
    .pipe(changed(dirs.cache + '/img'))
    .pipe(image({
      optipng: ['-i 1', '-strip all', '-fix', '-o7', '-force'],
      pngquant: ['--speed=1', '--force', 256],
      zopflipng: ['-y', '--lossy_8bit', '--lossy_transparent'],
      jpegRecompress: ['--strip', '--quality', 'medium', '--min', 40, '--max', 80],
      mozjpeg: ['-optimize', '-progressive'],
      gifsicle: ['--optimize'],
      svgo: ['--enable', 'cleanupIDs', '--disable', 'convertColors']
    }))
    .pipe(gulp.dest(dirs.cache + '/img'))
}, () => {
  // step 2 - use cached images
  return gulp.src([
    dirs.cache + '/img/**/*.svg',
    dirs.cache + '/img/**/*.jpg',
    dirs.cache + '/img/**/*.png'
  ])
    .pipe(gulp.dest(dirs.site + '/img'))
}))

function template (name) {
  const image = require('gulp-image')
  const changed = require('gulp-changed')
  const zip = require('gulp-zip')

  return gulp.series(() => { // step 1: optimize & cache content
    return replace(gulp.src('src/templates/' + name + '/**/*'))
      .pipe(changed(dirs.cache + '/templates/' + name))
      .pipe(image({
        optipng: ['-i 1', '-strip all', '-fix', '-o7', '-force'],
        pngquant: ['--speed=1', '--force', 256],
        zopflipng: ['-y', '--lossy_8bit', '--lossy_transparent'],
        jpegRecompress: ['--strip', '--quality', 'high', '--min', 60, '--max', 85],
        mozjpeg: ['-optimize', '-progressive'],
        gifsicle: ['--optimize'],
        svgo: ['--enable', 'cleanupIDs', '--disable', 'convertColors']
      }))
      .pipe(gulp.dest(dirs.cache + '/templates/' + name))
  }, () => { // step 2: zip cache
    return gulp.src(dirs.cache + '/templates/' + name + '/**/*')
      .pipe(zip(name + '.zip'))
      .pipe(gulp.dest(dirs.site + '/api/data/templates'))
  })
}

gulp.task('template-Classic', template('Classic'))
gulp.task('template-RPG', template('RPG'))
gulp.task('template-Tutorial', template('Tutorial'))

gulp.task('build', gulp.parallel(
  'js-main',
  'sass',
  'html',
  'js-vendor',
  'php',
  'template-RPG',
  'template-Classic',
  'template-Tutorial',
  'fonts',
  'img',
  'favicon'
))

gulp.task('dist', gulp.parallel('build'))

gulp.task('dist-test', gulp.series('dist', () => {
  return replace(gulp.src([
    'test/data/server.json'
  ]))
    .pipe(gulp.dest(dirs.site + '/api/data'))
}))

gulp.task('release', gulp.series(
  'clean',
  'build'
))

// --- release targets ---------------------------------------------------------

gulp.task('package-tgz', function () {
  const tar = require('gulp-tar')
  const gzip = require('gulp-gzip')
  const sort = require('gulp-sort')

  return gulp.src([
    dirs.site + '/**/*',
    '!*.css.map',
    '!*.js.map'
  ], { base: dirs.build, dot: true })
    .pipe(sort())
    .pipe(tar(p.name + '-' + p.version + '.tar'))
    .pipe(gzip({ gzipOptions: { level: 9 } }))
    .pipe(gulp.dest(dirs.build))
})

gulp.task('package-zip', function () {
  const zip = require('gulp-zip')
  const sort = require('gulp-sort')

  return gulp.src([
    dirs.site + '/**/*',
    '!*.css.map',
    '!*.js.map'
  ], { base: dirs.build, dot: true })
    .pipe(sort())
    .pipe(zip(p.name + '-' + p.version + '.zip'))
    .pipe(gulp.dest(dirs.build))
})

gulp.task('release', gulp.series(
  'clean',
  'clean-cache',
  'dist',
  'package-tgz',
  'package-zip'
))

// --- default target ----------------------------------------------------------

gulp.task('default', gulp.series('dist'))

// --- dev targets -------------------------------------------------------------

gulp.task('watch', gulp.series('dist', function () {
  gulp.watch('src/scss/**/*scss', gulp.series('sass'))
  gulp.watch('src/js/**/*js', gulp.series('js-main'))
  gulp.watch('src/php/**/*php', gulp.series('php'))
}))
