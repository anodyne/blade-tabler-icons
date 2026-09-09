import { test, expect } from 'bun:test';
import { mkdtemp, mkdir, writeFile, readFile, readdir, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { build, optimizeSvg, root } from '../bin/compile-icons.mjs';

const svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M1 1h10v10z"/></svg>';
async function fixture(run) {
    const dir = await mkdtemp(path.join(tmpdir(), 'tabler-test-'));
    const options = { source: path.join(dir, 'source'), output: path.join(dir, 'svg'), enumFile: path.join(dir, 'Tabler.php') };
    try {
        for (const style of ['filled', 'outline']) {
            await mkdir(path.join(options.source, style), { recursive: true });
            await writeFile(path.join(options.source, style, 'abc.svg'), svg);
        }
        await mkdir(options.output);
        await writeFile(path.join(options.output, 'stale.svg'), svg);
        await writeFile(options.enumFile, 'previous enum');
        await run(options);
    } finally {
        await rm(dir, { recursive: true, force: true });
    }
}

for (const failure of ['missing', 'empty', 'filename collision', 'enum collision', 'invalid XML', 'empty SVG', 'invalid viewBox', 'hand-maintained asset']) {
    test(`failed build preserves existing output: ${failure}`, () => fixture(async options => {
        const outline = path.join(options.source, 'outline');
        if (failure === 'missing') await rm(outline, { recursive: true });
        if (failure === 'empty') await rm(path.join(outline, 'abc.svg'));
        if (failure === 'filename collision') await writeFile(path.join(outline, 'abc-filled.svg'), svg);
        if (failure === 'enum collision') {
            await writeFile(path.join(outline, 'a-b.svg'), svg);
            await writeFile(path.join(outline, 'a--b.svg'), svg);
        }
        if (failure === 'invalid XML') await writeFile(path.join(outline, 'abc.svg'), '<svg>');
        if (failure === 'empty SVG') await writeFile(path.join(outline, 'abc.svg'), '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"/>');
        if (failure === 'invalid viewBox') await writeFile(path.join(outline, 'abc.svg'), svg.replace('0 0 24 24', '0 0 16 16'));
        if (failure === 'hand-maintained asset') await writeFile(path.join(options.output, 'notes.txt'), 'keep');
        await expect(build(options)).rejects.toThrow();
        expect(await readFile(path.join(options.output, 'stale.svg'), 'utf8')).toBe(svg);
        expect(await readFile(options.enumFile, 'utf8')).toBe('previous enum');
        expect((await readdir(path.dirname(options.output))).some(name => name.startsWith('.tabler-'))).toBe(false);
    }));
}

test('successful build removes stale icons and preserves naming rules', () => fixture(async options => {
    for (const name of ['123', 'class', 'function', 'brand-github', 'a-b-2']) {
        await writeFile(path.join(options.source, 'outline', `${name}.svg`), svg);
    }
    await build(options);
    expect(await readdir(options.output)).not.toContain('stale.svg');
    const contents = await readFile(options.enumFile, 'utf8');
    for (const [name, value] of [['Icon123', '123'], ['IconClass', 'class'], ['Function', 'function'], ['BrandGithub', 'brand-github'], ['AB2', 'a-b-2'], ['AbcFilled', 'abc-filled']]) {
        expect(contents).toContain(`case ${name} = 'tabler-${value}';`);
    }
    const result = spawnSync('php', [path.join(root, 'bin/generate-enum.php'), options.output, options.enumFile], { cwd: tmpdir() });
    expect(result.status).toBe(0);
    expect(await readFile(options.enumFile, 'utf8')).toBe(contents);
}));

test('optimization preserves accessibility references, custom styles and viewBox', () => {
    const result = optimizeSvg('<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 32 32" class="upstream" role="img" aria-labelledby="title desc"><title id="title">Title</title><desc id="desc">Description</desc><path class="drawing" fill="red" stroke="blue" d="M1 1h10v10z"/></svg>');
    for (const text of ['viewBox="0 0 32 32"', 'role="img"', 'aria-labelledby="title desc"', '<title id="title">Title</title>', '<desc id="desc">Description</desc>', 'class="drawing"', 'fill="red"', 'stroke="#00f"']) expect(result).toContain(text);
    expect(result).not.toContain('class="upstream"');
    expect(result).not.toContain('width="24"');
});

test('commit failure rolls back the SVG directory', () => fixture(async options => {
    await rm(options.enumFile);
    await mkdir(options.enumFile);
    await writeFile(path.join(options.enumFile, 'keep.txt'), 'keep');
    await expect(build(options)).rejects.toThrow();
    expect(await readdir(options.output)).toEqual(['stale.svg']);
    expect(await readFile(path.join(options.enumFile, 'keep.txt'), 'utf8')).toBe('keep');
}));

test('builds a missing output directory and repeats deterministically', () => fixture(async options => {
    await rm(options.output, { recursive: true });
    await rm(options.enumFile);
    await writeFile(path.join(options.source, 'outline', 'zebra.svg'), svg);
    await writeFile(path.join(options.source, 'outline', 'a-b-2.svg'), svg);
    await build(options);
    const files = (await readdir(options.output)).sort();
    expect(files).toEqual(['a-b-2.svg', 'abc-filled.svg', 'abc.svg', 'zebra.svg']);
    const contents = await Promise.all(files.map(name => readFile(path.join(options.output, name), 'utf8')));
    const enumContents = await readFile(options.enumFile, 'utf8');
    await build(options);
    expect((await readdir(options.output)).sort()).toEqual(files);
    expect(await Promise.all(files.map(name => readFile(path.join(options.output, name), 'utf8')))).toEqual(contents);
    expect(await readFile(options.enumFile, 'utf8')).toBe(enumContents);
}));

test('failed first build leaves no partially installed output', () => fixture(async options => {
    await rm(options.output, { recursive: true });
    await rm(options.enumFile);
    await mkdir(options.enumFile);
    await writeFile(path.join(options.enumFile, 'keep.txt'), 'keep');
    await expect(build(options)).rejects.toThrow();
    await expect(readdir(options.output)).rejects.toThrow();
    expect(await readFile(path.join(options.enumFile, 'keep.txt'), 'utf8')).toBe('keep');
}));

test('CLI propagates missing input failure from another working directory', () => fixture(async options => {
    const result = spawnSync(process.execPath, [path.join(root, 'bin/compile-icons.mjs'), path.join(options.source, 'missing')], { cwd: tmpdir(), encoding: 'utf8' });
    expect(result.status).toBe(1);
    expect(result.stderr).toContain('ENOENT');
}));
