import { themes as prismThemes } from "prism-react-renderer";
import type { Config } from "@docusaurus/types";
import type * as Preset from "@docusaurus/preset-classic";

// This runs in Node.js - Don't use client-side code here (browser APIs, JSX...)

const config: Config = {
  title: "Laravel Zod Generator - Romega Software",
  tagline:
    "Generate TypeScript Zod validation schemas from Laravel validation rules",
  favicon: "img/favicon.ico",

  // Future flags, see https://docusaurus.io/docs/api/docusaurus-config#future
  future: {
    v4: true, // Improve compatibility with the upcoming Docusaurus v4
  },

  // Set the production url of your site here
  url: "https://laravel-schema-generator.com",
  // Set the /<baseUrl>/ pathname under which your site is served
  // For GitHub pages deployment, it is often '/<projectName>/'
  baseUrl: "/docs/build/",

  // GitHub pages deployment config.
  // If you aren't using GitHub pages, you don't need these.
  organizationName: "romegasoftware", // Usually your GitHub org/user name.
  projectName: "laravel-schema-generator", // Usually your repo name.

  onBrokenLinks: "throw",
  onBrokenMarkdownLinks: "warn",

  // Even if you don't use internationalization, you can use this field to set
  // useful metadata like html lang. For example, if your site is Chinese, you
  // may want to replace "en" with "zh-Hans".
  i18n: {
    defaultLocale: "en",
    locales: ["en"],
  },

  presets: [
    [
      "classic",
      {
        docs: {
          sidebarPath: "./sidebars.ts",
          // Please change this to your repo.
          // Remove this to remove the "edit this page" links.
          editUrl:
            "https://github.com/romegasoftware/laravel-schema-generator/tree/main/docs/",
        },
      },
    ],
  ],

  themeConfig: {
    // Replace with your project's social card
    image: "img/docusaurus-social-card.jpg",
    navbar: {
      title: "Laravel Zod Generator",
      logo: {
        alt: "Laravel Zod Generator Logo",
        src: "img/logo.svg",
      },
      items: [
        {
          type: "docSidebar",
          sidebarId: "docsSidebar",
          position: "left",
          label: "Documentation",
        },
        {
          href: "https://github.com/romegasoftware/laravel-schema-generator",
          label: "GitHub",
          position: "right",
        },
        {
          href: "https://packagist.org/packages/romegasoftware/laravel-schema-generator",
          label: "Packagist",
          position: "right",
        },
      ],
    },
    footer: {
      style: "dark",
      links: [
        {
          title: "Documentation",
          items: [
            {
              label: "Getting Started",
              to: "/docs/intro",
            },
            {
              label: "Installation",
              to: "/docs/installation",
            },
            {
              label: "Configuration",
              to: "/docs/configuration",
            },
          ],
        },
        {
          title: "Community",
          items: [
            {
              label: "GitHub Issues",
              href: "https://github.com/romegasoftware/laravel-schema-generator/issues",
            },
          ],
        },
        {
          title: "More",
          items: [
            {
              label: "GitHub",
              href: "https://github.com/romegasoftware/laravel-schema-generator",
            },
            {
              label: "Packagist",
              href: "https://packagist.org/packages/romegasoftware/laravel-schema-generator",
            },
            {
              label: "Romega Software",
              href: "https://romegasoftware.com",
            },
          ],
        },
      ],
      copyright: `Copyright © ${new Date().getFullYear()} Romega Software. Built with Docusaurus.`,
    },
    prism: {
      theme: prismThemes.github,
      darkTheme: prismThemes.dracula,
      additionalLanguages: ["php", "bash", "json"],
    },
  } satisfies Preset.ThemeConfig,
};

export default config;
