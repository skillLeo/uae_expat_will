import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { ZiggyVue } from 'ziggy-js';

const appName = 'UAE Expat Wills';

/**
 * Server-side rendering.
 *
 * The contract requires the public pages to be genuinely server-rendered: an
 * admin's content edit must appear in the HTML immediately, every FAQ answer
 * must be in the markup even when collapsed, and the legal caveats must be
 * readable without running JavaScript. Client-only Inertia would put all of that
 * inside a JSON attribute, which is not the same thing.
 */
createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => (title ? `${title} · ${appName}` : appName),

        resolve: (name) => {
            const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
            return pages[`./Pages/${name}.vue`];
        },

        setup({ App, props, plugin }) {
            const app = createSSRApp({ render: () => h(App, props) }).use(plugin);

            // Ziggy's shared props are absent on an error response: that
            // response is built inside the exception handler, which runs
            // outside the Inertia middleware that shares them. Dereferencing
            // them unguarded threw during setup and took SSR down for every
            // error page, so the 404 fell back to client-side rendering.
            const ziggy = page.props?.ziggy;

            if (ziggy) {
                app.use(ZiggyVue, {
                    ...ziggy,
                    ...(ziggy.location ? { location: new URL(ziggy.location) } : {}),
                });
            }

            return app;
        },
    }),
);
