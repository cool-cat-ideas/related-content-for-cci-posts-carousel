import { minifyReleaseStyles } from './quality/minify-release.mjs';
import {
  copyFileSync,
  existsSync,
  mkdirSync,
  readFileSync,
  readdirSync,
  rmdirSync,
  statSync,
  unlinkSync,
} from 'node:fs';
import { execFileSync } from 'node:child_process';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const slug = JSON.parse(readFileSync(join(root, 'package.json'), 'utf8')).name;
const pluginFile = join(root, `${slug}.php`);
const pluginContents = readFileSync(pluginFile, 'utf8');
const versionMatch = pluginContents.match(/^\s*\*\s*Version:\s*(.+)$/m);
const version = versionMatch ? versionMatch[1].trim() : '';

if (!version) throw new Error(`Could not read plugin version from ${pluginFile}`);

const releases = process.env.CCI_RELEASE_DIR
  ? resolve(process.env.CCI_RELEASE_DIR)
  : resolve(root, '..', '..', 'releases', 'wordpress');
const stage = join(releases, `.stage-${slug}`);
const target = join(stage, slug);
const zipPath = join(releases, `${slug}-${version}.zip`);

const copy = (source, destination) => {
  const relativePath = source.slice(root.length + 1).replaceAll('\\', '/');
  const toolingDirectory =
    /(?:^|\/)(?:\.(?:git|github|vscode|idea|playwright(?:-cli)?)|node_modules|__pycache__|tests?|test-results|playwright-report|blob-report|coverage)(?:\/|$)/;
  const toolingFile =
    /(?:^|\/)(?:\.env(?:\.[^/]*)?|\.editorconfig|\.gitignore|\.gitattributes|AGENTS\.md|PROJECT_GUIDE\.md|\.DS_Store|Thumbs\.db)$/;
  const buildConfiguration =
    /(?:^|\/)(?:(?:playwright|vitest|vite|webpack|tailwind|postcss|eslint|jest)\.config\.|tsconfig(?:\.|$)|\.prettier|\.eslintrc)/;
  if (
    [
      toolingDirectory,
      toolingFile,
      buildConfiguration,
      /\.(?:map|log|pyc|tsbuildinfo|code-workspace)$/,
    ].some((pattern) => pattern.test(relativePath))
  )
    return;
  if (statSync(source).isDirectory()) {
    mkdirSync(destination, { recursive: true });
    for (const child of readdirSync(source)) copy(join(source, child), join(destination, child));
    return;
  }

  mkdirSync(dirname(destination), { recursive: true });
  copyFileSync(source, destination);
};

const remove = (path) => {
  if (!existsSync(path)) return;
  if (statSync(path).isDirectory()) {
    for (const child of readdirSync(path)) remove(join(path, child));
    rmdirSync(path);
    return;
  }
  unlinkSync(path);
};

remove(stage);
remove(zipPath);
mkdirSync(target, { recursive: true });

for (const entry of [`${slug}.php`, 'includes', 'readme.txt', 'LICENSE']) {
  const source = join(root, entry);
  if (existsSync(source)) copy(source, join(target, entry));
}

minifyReleaseStyles(target);

execFileSync('python3', ['-m', 'zipfile', '-c', zipPath, slug], { cwd: stage });
remove(stage);
console.log(zipPath);
