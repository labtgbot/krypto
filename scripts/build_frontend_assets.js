const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');

function resolveRoot(relativePath) {
  return path.join(root, relativePath);
}

function ensureDir(relativePath) {
  fs.mkdirSync(resolveRoot(relativePath), { recursive: true });
}

function removeDir(relativePath) {
  fs.rmSync(resolveRoot(relativePath), { recursive: true, force: true });
}

function copyFile(source, destination) {
  const sourcePath = resolveRoot(source);
  const destinationPath = resolveRoot(destination);

  if (!fs.existsSync(sourcePath)) {
    throw new Error(`Missing frontend dependency file: ${source}`);
  }

  fs.mkdirSync(path.dirname(destinationPath), { recursive: true });
  fs.copyFileSync(sourcePath, destinationPath);
}

function copyDir(source, destination) {
  const sourcePath = resolveRoot(source);
  const destinationPath = resolveRoot(destination);

  if (!fs.existsSync(sourcePath)) {
    throw new Error(`Missing frontend dependency directory: ${source}`);
  }

  fs.cpSync(sourcePath, destinationPath, { recursive: true });
}

function writeFile(destination, contents) {
  const destinationPath = resolveRoot(destination);
  fs.mkdirSync(path.dirname(destinationPath), { recursive: true });
  fs.writeFileSync(destinationPath, contents);
}

function readFile(source) {
  return fs.readFileSync(resolveRoot(source), 'utf8');
}

removeDir('assets/bower');
removeDir('assets/node_modules');

copyFile('node_modules/animate.css/animate.min.css', 'assets/bower/animate.css/animate.min.css');

copyFile('node_modules/jquery/dist/jquery.min.js', 'assets/bower/jquery/dist/jquery.min.js');
copyFile('node_modules/jquery-ui-dist/jquery-ui.min.js', 'assets/bower/jquery-ui/jquery-ui.min.js');
copyFile('node_modules/jquery-number/jquery.number.min.js', 'assets/bower/jquery.number.js/jquery.number.min.js');
copyFile('node_modules/jquery.animate-number/jquery.animateNumber.min.js', 'assets/bower/jquery.animateNumber.js/jquery.animateNumber.min.js');

copyDir('node_modules/chosen-js', 'assets/bower/chosen');
copyDir('node_modules/dropzone/dist', 'assets/bower/dropzone/dist');
copyDir('node_modules/air-datepicker/dist', 'assets/bower/air-datepicker/dist');
copyDir('node_modules/chart.js/dist', 'assets/bower/chart.js/dist');
copyDir('node_modules/technicalindicators/dist', 'assets/bower/technicalindicators/dist');
copyDir('node_modules/tether-shepherd/dist', 'assets/bower/tether-shepherd/dist');
copyDir('node_modules/mark.js/dist', 'assets/bower/mark.js/dist');
copyDir('node_modules/lightbox2/dist', 'assets/bower/lightbox2/dist');

copyFile('node_modules/ion-rangeslider/css/ion.rangeSlider.css', 'assets/bower/ion.rangeSlider/css/ion.rangeSlider.css');
copyFile('node_modules/ion-rangeslider/css/ion.rangeSlider.css', 'assets/bower/ion.rangeSlider/css/ion.rangeSlider.skinFlat.css');
copyFile('node_modules/ion-rangeslider/js/ion.rangeSlider.min.js', 'assets/bower/ion.rangeSlider/js/ion.rangeSlider.min.js');

copyFile('node_modules/diff/dist/diff.min.js', 'assets/bower/jsdiff/diff.min.js');
copyFile('node_modules/clipboard/dist/clipboard.min.js', 'assets/bower/clipboard/dist/clipboard.min.js');

copyDir('node_modules/@selectize/selectize/dist/css', 'assets/bower/selectize/dist/css');
copyFile('node_modules/@selectize/selectize/dist/js/selectize.min.js', 'assets/bower/selectize/dist/js/standalone/selectize.min.js');

copyFile('node_modules/tippy.js/dist/tippy.css', 'assets/bower/tippyjs/dist/tippy.css');
copyFile('node_modules/tippy.js/dist/tippy-bundle.umd.min.js', 'assets/bower/tippyjs/dist/tippy.min.js');

copyFile('node_modules/sweetalert2/dist/sweetalert2.css', 'assets/bower/sweetalert2/dist/sweetalert2.css');
writeFile(
  'assets/bower/sweetalert2/dist/sweetalert2.min.js',
  [
    readFile('node_modules/sweetalert2/dist/sweetalert2.all.min.js'),
    '(function(){if(!window.Swal){return;}var legacy=function(options){if(options&&typeof options==="object"&&options.type&&!options.icon){options=Object.assign({},options,{icon:options.type});}return window.Swal.fire(options);};for(var key in window.Swal){if(Object.prototype.hasOwnProperty.call(window.Swal,key)){legacy[key]=window.Swal[key];}}window.swal=legacy;}());',
  ].join('\n')
);

ensureDir('assets/node_modules/babel-polyfill');
writeFile(
  'assets/node_modules/babel-polyfill/browser.js',
  [
    '/* Generated replacement for the retired babel-polyfill package. */',
    readFile('node_modules/core-js-bundle/minified.js'),
    readFile('node_modules/regenerator-runtime/runtime.js'),
  ].join('\n')
);

console.log('Frontend dependency assets generated.');
