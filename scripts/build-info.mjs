// Records which release is deployed (shown in the admin dashboard footer).
// Runs after `vite build`; on Vercel the commit comes from the system environment variables.
import { execSync } from 'node:child_process';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';

const git = (args) => {
    try {
        return execSync(`git ${args}`, { stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim() || null;
    } catch {
        return null;
    }
};

const pkg = JSON.parse(readFileSync(new URL('../package.json', import.meta.url)));
const info = {
    version: pkg.version ?? '0.0.0',
    commit: process.env.VERCEL_GIT_COMMIT_SHA || git('rev-parse HEAD'),
    branch: process.env.VERCEL_GIT_COMMIT_REF || git('rev-parse --abbrev-ref HEAD'),
    committed_at: git('log -1 --format=%cI'),
    built_at: new Date().toISOString(),
};

mkdirSync(new URL('../public/build/', import.meta.url), { recursive: true });
writeFileSync(new URL('../public/build/build-info.json', import.meta.url), JSON.stringify(info, null, 2) + '\n');
console.log(`build-info: v${info.version} ${info.commit ? info.commit.slice(0, 7) : 'unknown'} ${info.built_at}`);
