import createServer from '@inertiajs/react/server';
import process from 'node:process';
import { render } from './ssr-render';


createServer(render, { host: '127.0.0.1', port: Number(process.env.INERTIA_SSR_PORT || 13714) });
