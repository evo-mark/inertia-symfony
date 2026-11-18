import { defineConfig } from "vitepress";

// https://vitepress.dev/reference/site-config
export default defineConfig({
    title: "Inertia Symfony",
    base: "/inertia-symfony/", // GitHub Pages serves project sites from /<repo>/
    description: "Documentation for the Inertia.js adapter for Symfony",
    themeConfig: {
        nav: [
            { text: "Guide", link: "/guide/getting-started" },
            { text: "Configuration", link: "/reference/configuration" },
        ],

        sidebar: [
            {
                text: "Guide",
                items: [
                    { text: "Getting started", link: "/guide/getting-started" },
                    { text: "Frontend setup", link: "/guide/frontend" },
                    { text: "Pages and responses", link: "/guide/responses" },
                    { text: "Pages without a controller", link: "/guide/routes" },
                    { text: "Props", link: "/guide/props" },
                    { text: "Validation", link: "/guide/validation" },
                    { text: "Error handling", link: "/guide/errors" },
                    { text: "Server-side rendering", link: "/guide/ssr" },
                ],
            },
            {
                text: "Reference",
                items: [{ text: "Configuration", link: "/reference/configuration" }],
            },
        ],

        socialLinks: [{ icon: "github", link: "https://github.com/evo-mark/inertia-symfony" }],
    },
});
