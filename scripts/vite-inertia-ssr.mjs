export function inertiaDevSsr() {
    return {
        name: 'altech-inertia-dev-ssr',
        apply: 'serve',
        configureServer(server) {
            server.middlewares.use('/__inertia_ssr', async (req, res) => {
                if (req.method !== 'POST') {
                    res.writeHead(405).end();
                    return;
                }
                try {
                    let body = '';
                    for await (const chunk of req) {
                        body += chunk;
                        if (body.length > 10 * 1024 * 1024) {
                            res.writeHead(413).end();
                            return;
                        }
                    }
                    // Vite invalidates this module and its dependencies on every edit.
                    const { render } = await server.ssrLoadModule('/resources/js/ssr-render.tsx');
                    const result = await render(JSON.parse(body));
                    res.setHeader('Content-Type', 'application/json');
                    res.end(JSON.stringify(result));
                } catch (error) {
                    server.ssrFixStacktrace(error);
                    server.config.logger.error(error.stack ?? error.message);
                    res.writeHead(500, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ error: error.message }));
                }
            });
        },
    };
}
