import fs from 'node:fs/promises';
import path from 'node:path';
import { Engine } from 'php-parser';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/(.:)/, '$1')), '..');
const parser = new Engine({ parser: { php7: true, suppressErrors: false }, ast: { withPositions: true } });
const ignored = new Set(['node_modules', '.git', 'vendor', 'tmp', 'dist']);
let checked = 0;

async function walk(directory) {
  for (const entry of await fs.readdir(directory, { withFileTypes: true })) {
    if (ignored.has(entry.name)) continue;
    const file = path.join(directory, entry.name);
    if (entry.isDirectory()) await walk(file);
    else if (entry.isFile() && entry.name.endsWith('.php')) {
      const source = await fs.readFile(file, 'utf8');
      try {
        parser.parseCode(source, path.relative(root, file));
        checked += 1;
      } catch (error) {
        console.error(`${path.relative(root, file)}: ${error.message}`);
        process.exitCode = 1;
      }
    }
  }
}

await walk(root);
if (process.exitCode) process.exit(process.exitCode);
console.log(`Parsed ${checked} PHP files successfully.`);

