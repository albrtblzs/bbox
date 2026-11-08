import { buildApp } from './app.js';
import { loadLimits } from './rules.js';

const app = buildApp(loadLimits(process.env));
await app.listen({ host: '0.0.0.0', port: 3000 });
for (const signal of ['SIGTERM', 'SIGINT']) process.on(signal, async () => { await app.close(); process.exit(0); });
