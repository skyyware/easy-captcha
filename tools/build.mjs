import { build } from 'esbuild';
import { copyFile, mkdir } from 'node:fs/promises';

const output = 'src/Resources/public';
await mkdir(output, { recursive: true });
await build({ entryPoints: ['src/Resources/app/storefront/src/main.mjs'], outfile: `${output}/starcode.js`, bundle: true, format: 'esm', minify: true, legalComments: 'eof', target: ['es2020'], supported: { 'template-literal': false } });
await build({ stdin: { contents: '@import "altcha/altcha.css"; @import "./src/Resources/app/storefront/src/starcode.css";', resolveDir: process.cwd(), loader: 'css' }, outfile: `${output}/starcode.css`, bundle: true, minify: true, conditions: ['import'] });
await copyFile('node_modules/altcha/dist/workers/pbkdf2.js', `${output}/pbkdf2.js`);
await copyFile('node_modules/altcha/LICENSE.txt', `${output}/ALTCHA-LICENSE.txt`);
