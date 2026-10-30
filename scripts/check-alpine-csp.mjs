#!/usr/bin/env node
// Every Alpine / Livewire expression in resources/views must work under the
// CSP builds (audit MB-29): the page sends no 'unsafe-eval', so Alpine
// evaluates x-* / @ / : / wire:* attributes with its own parser, which
// knows no arrow functions, template literals, `?.`, `??`, `+=` or `;`, and
// can read nothing global (Math, window, formatMoney...). See
// resources/js/alpine-csp.js for the helpers to use instead.
//
// This runs each expression through that parser, taken from Livewire's own
// CSP build so it is the same one the browser uses, and flags globals.
// Blade output ({{ }}, @js(...)) is replaced by a placeholder first.
//
//   node scripts/check-alpine-csp.mjs        (exit 1 and a list when any fail)

import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import vm from 'node:vm';

const root = new URL('..', import.meta.url).pathname;
const views = join(root, 'resources/views');

// The tokenizer and parser, out of the CSP build.
const dist = readFileSync(join(root, 'vendor/livewire/livewire/dist/livewire.csp.esm.js'), 'utf8');
const from = dist.indexOf('var Token = class {');
const to = dist.indexOf('var Evaluator = class {');
if (from < 0 || to < 0) {
    console.error('Could not find the CSP parser in livewire.csp.esm.js: has Livewire changed its build?');
    process.exit(2);
}
const sandbox = {};
vm.runInNewContext(dist.slice(from, to) + '\nthis.Tokenizer = Tokenizer; this.Parser = Parser;', sandbox);
const parse = (expr) => new sandbox.Parser(new sandbox.Tokenizer(expr).tokenize()).parse();

const GLOBALS = ['Math', 'Number', 'parseFloat', 'parseInt', 'window', 'document', 'JSON', 'Date', 'console', 'setTimeout',
    'setInterval', 'String', 'Object', 'Array', 'localStorage', 'sessionStorage', 'encodeURIComponent', 'formatMoney',
    'loadChart', 'Livewire', 'Alpine', 'alert', 'confirm', 'navigator', 'location', 'history', 'fetch', 'Intl', 'isNaN'];
const globalRe = new RegExp(`(^|[^\\w.$'"])(${GLOBALS.join('|')})\\b`);
const DIRECTIVE = /^(wire:(?!model|key|loading|target|ignore|navigate|transition|dirty|offline|stream|replace|cloak|confirm|current|sort|id)[\w.:-]+|x-(data|init|show|text|html|model[\w.-]*|bind:[\w.-]+|on:[\w.:-]+|if|for|effect|id)|@[\w.:-]+|:[\w.-]+)$/;

// Blade output becomes a placeholder, keeping line breaks for line numbers.
function stripBlade(source) {
    const keep = (m) => '_b' + '\n'.repeat((m.match(/\n/g) || []).length);
    let s = source
        .replace(/\{\{--[\s\S]*?--\}\}/g, (m) => '\n'.repeat((m.match(/\n/g) || []).length))
        .replace(/\{!![\s\S]*?!!\}/g, keep)
        .replace(/\{\{[\s\S]*?\}\}/g, keep);
    let out = '';
    for (let i = 0; i < s.length;) {
        const m = /^@(js|json|jsText|entangle|money|moneyWhole|fig)\(/.exec(s.slice(i, i + 20));
        if (!m) { out += s[i++]; continue; }
        let j = i + m[0].length, depth = 1, quote = null;
        for (; j < s.length && depth; j++) {
            const c = s[j];
            if (quote) { if (c === '\\') j++; else if (c === quote) quote = null; } else if (c === '"' || c === "'") quote = c;
            else if (c === '(') depth++; else if (c === ')') depth--;
        }
        out += keep(s.slice(i, j)); i = j;
    }
    return out;
}

function* bladeFiles(dir) {
    for (const name of readdirSync(dir)) {
        const path = join(dir, name);
        if (statSync(path).isDirectory()) yield* bladeFiles(path);
        else if (name.endsWith('.blade.php')) yield path;
    }
}

const TAG = /<([a-zA-Z][\w:.-]*)(\s(?:[^>"']|"[^"]*"|'[^']*')*)?\/?>/g;
const ATTR = /([@:a-zA-Z][\w:.@-]*)\s*=\s*(?:"([^"]*)"|'([^']*)')/g;
const decode = (v) => v.replace(/&quot;/g, '"').replace(/&#039;/g, "'").replace(/&amp;/g, '&').replace(/&gt;/g, '>').replace(/&lt;/g, '<');

const problems = [];
let checked = 0;
for (const file of bladeFiles(views)) {
    const source = stripBlade(readFileSync(file, 'utf8'));
    for (const tag of source.matchAll(TAG)) {
        const component = tag[1].startsWith('x-') || tag[1].startsWith('livewire:');
        const line = source.slice(0, tag.index).split('\n').length;
        for (const attr of (tag[2] || '').matchAll(ATTR)) {
            const name = attr[1];
            let value = decode((attr[2] ?? attr[3]).trim());
            const where = `${relative(root, file)}:${line} ${name}`;
            // A component's props are PHP, but strings handed on to Alpine
            // (name="'items[' + i + ']'") must not be template literals.
            if (component) {
                if (value.includes('`')) problems.push(`${where}: template literal handed to a component`);
                continue;
            }
            if (!DIRECTIVE.test(name) || value === '') continue;
            if (name === 'x-for') value = value.replace(/^\(?\s*[\w\s,]+\)?\s+(in|of)\s+/, '');
            checked++;
            try {
                parse(value);
            } catch (e) {
                problems.push(`${where}: ${e.message}\n    ${value.replace(/\s+/g, ' ').slice(0, 160)}`);
                continue;
            }
            const g = globalRe.exec(value);
            if (g) problems.push(`${where}: uses the global ${g[2]}, which the CSP build can't read\n    ${value.replace(/\s+/g, ' ').slice(0, 160)}`);
        }
    }
}

if (problems.length) {
    console.error(`${problems.length} Alpine/Livewire expression(s) won't run under the CSP build:\n`);
    console.error(problems.join('\n'));
    console.error('\nSee resources/js/alpine-csp.js for what an expression may hold, and its helpers.');
    process.exit(1);
}
console.log(`${checked} Alpine/Livewire expressions checked: all run under the CSP build.`);
