#!/usr/bin/env bun

import { readdir, readFile, writeFile, mkdir, mkdtemp, rename, rm, access } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { optimize } from 'svgo';

export const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

export function optimizeSvg(svg, filename) {
    return optimize(svg, {
        path: filename,
        plugins: [
            {
                name: 'tabler-root-attributes',
                fn: () => ({ element: { enter(node, parent) {
                    if (node.name === 'svg' && parent.type === 'root') {
                        delete node.attributes.class;
                        if (node.attributes.width === '24') delete node.attributes.width;
                        if (node.attributes.height === '24') delete node.attributes.height;
                    }
                } } }),
            },
            { name: 'preset-default', params: { overrides: {
                removeDesc: false,
                removeUnknownsAndDefaults: { keepRoleAttr: true },
                cleanupIds: false,
            } } },
        ],
    }).data;
}

export async function build({ source = 'node_modules/@tabler/icons/icons', output = 'resources/svg', enumFile = 'src/Tabler.php' } = {}) {
    source = path.resolve(root, source);
    output = path.resolve(root, output);
    enumFile = path.resolve(root, enumFile);
    const icons = new Map();
    for (const style of ['filled', 'outline']) {
        const directory = path.join(source, style);
        const entries = (await readdir(directory, { withFileTypes: true })).sort((a, b) => a.name < b.name ? -1 : a.name > b.name ? 1 : 0);
        const files = entries.filter(entry => entry.isFile() && entry.name.endsWith('.svg'));
        if (!files.length) throw new Error(`No SVG icons in ${directory}`);
        for (const file of files) {
            const name = file.name.slice(0, -4) + (style === 'filled' ? '-filled.svg' : '.svg');
            if (icons.has(name.toLowerCase())) throw new Error(`Filename collision: ${name}`);
            icons.set(name.toLowerCase(), { name, file: path.join(directory, file.name) });
        }
    }
    // This directory is exclusively generated. Refuse to discard other assets.
    let previous = false;
    try {
        const entries = await readdir(output, { withFileTypes: true });
        if (entries.some(entry => !entry.isFile() || !entry.name.endsWith('.svg'))) {
            throw new Error(`Output contains non-generated assets: ${output}`);
        }
        previous = true;
    } catch (error) {
        if (error.code !== 'ENOENT') throw error;
    }
    await access(path.dirname(enumFile));
    await mkdir(path.dirname(output), { recursive: true });
    const stage = await mkdtemp(path.join(path.dirname(output), '.tabler-build-'));
    let installed = false;
    let backedUp = false;
    try {
        await mkdir(path.join(stage, 'svg'));
        for (const { name, file } of [...icons.values()].sort((a, b) => a.name < b.name ? -1 : a.name > b.name ? 1 : 0)) {
            await writeFile(path.join(stage, 'svg', name), optimizeSvg(await readFile(file, 'utf8'), file));
        }
        const generatedEnum = path.join(stage, 'Tabler.php');
        const result = spawnSync('php', [path.join(root, 'bin/generate-enum.php'), path.join(stage, 'svg'), generatedEnum], { encoding: 'utf8' });
        if (result.error || result.status !== 0) throw new Error(result.error?.message ?? result.stderr + result.stdout);
        // Commit only after all SVGs and the enum have been generated and validated.
        if (previous) {
            await rename(output, path.join(stage, 'previous'));
            backedUp = true;
        }
        await rename(path.join(stage, 'svg'), output);
        installed = true;
        await rename(generatedEnum, enumFile);
        console.log(`Built ${icons.size} SVGs and Tabler enum.`);
    } catch (error) {
        if (installed) await rm(output, { recursive: true, force: true });
        if (backedUp) await rename(path.join(stage, 'previous'), output);
        throw error;
    } finally {
        await rm(stage, { recursive: true, force: true });
    }
}

if (import.meta.main) {
    build({ source: process.argv[2] }).catch(error => {
        console.error(error.message);
        process.exitCode = 1;
    });
}
