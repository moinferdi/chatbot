/**
 * esbuild bundler for the chatbot widget.
 *
 * Bundles the TypeScript entry (and every imported module) into a single
 * file at Resources/Public/JavaScript/chatbot.js, formatted as an IIFE so
 * it loads cleanly via TYPO3's <f:asset.script> (defer, CSP-nonce) with no
 * need for <script type="module"> or CORS/ESM considerations.
 *
 * Run:   `npm run build`   (one-shot)   /   `npm run watch`   (rebuild on change)
 *
 * This mirrors how TYPO3 v13 core builds its own JS — esbuild, single-file
 * outputs per entry. The *source* is modular ESM; the *artifact* is one file.
 */
import { build, context } from 'esbuild';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const __dirname = dirname(fileURLToPath(import.meta.url));

const entry = resolve(__dirname, 'Sources/TypeScript/chatbot.ts');
const outfile = resolve(
  __dirname,
  '..',
  'Resources',
  'Public',
  'JavaScript',
  'chatbot.js',
);

const options = {
  entryPoints: [entry],
  outfile,
  bundle: true,
  format: 'iife',
  target: ['es2021'],
  platform: 'browser',
  legalComments: 'inline',
  logLevel: 'info',
  sourcemap: false,
  // Tree-shake dead code so unused exports don't bloat the shipped bundle.
  treeShaking: true,
  resolveExtensions: ['.ts', '.js'],
  // esbuild does its own TS transpile; tsconfig.json is used only by `tsc`
  // for typecheck. Keeping the build decoupled from tsc means `npm run
  // typecheck` is an independent gate.
  tsconfig: resolve(__dirname, 'tsconfig.json'),
};

const watch = process.argv.includes('--watch');

if (watch) {
  const ctx = await context(options);
  await ctx.watch();
  console.log('[chatbot] watching for changes…  (Ctrl+C to stop)');
} else {
  await build(options);
  console.log('[chatbot] build complete →', outfile);
}
