#!/usr/bin/env bun
import { createHash } from 'node:crypto';
import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import { build, root } from './compile-icons.mjs';

async function snapshot() {
    const files = (await readdir(path.join(root, 'resources/svg'))).sort();
    const hash = createHash('sha256');
    for (const file of [...files.map(file => `resources/svg/${file}`), 'src/Tabler.php']) {
        hash.update(file).update('\0').update(await readFile(path.join(root, file))).update('\0');
    }
    return hash.digest('hex');
}

const committed = await snapshot();
await build();
const first = await snapshot();
await build();
if (first !== await snapshot()) throw new Error('Build is not deterministic.');
if (committed !== first) throw new Error('Generated assets were stale. Review and include the rebuilt output.');
console.log(`Generated filenames and contents match across both builds: ${first}`);
