const mix = require("laravel-mix");

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel applications. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js("resources/js/app.js", "public/js/")
    .sass("resources/sass/app.scss", "public/css/")
    // .copy("resources/css/heebo.css", "public/css")
    // .copy("resources/css/poppins.css", "public/css")
    .copyDirectory("resources/images", "public/images")
    // .copyDirectory("resources/fonts", "public/fonts")
    // .copyDirectory("resources/icons/fonts", "public/fonts")
    // .copyDirectory("resources/doc", "public/doc")
    // .copyDirectory("resources/videos", "public/videos")
    .options({
        clearConsole: false,
    })
    .version()
    .extract();

if (!mix.inProduction()) {
    mix.webpackConfig({ devtool: "source-map" });
    mix.sourceMaps(false, "source-map");
}
