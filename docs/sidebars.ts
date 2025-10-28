import type {SidebarsConfig} from '@docusaurus/plugin-content-docs';

// This runs in Node.js - Don't use client-side code here (browser APIs, JSX...)

/**
 * Creating a sidebar enables you to:
 - create an ordered group of docs
 - render a sidebar for each doc of that group
 - provide next/previous navigation

 The sidebars can be generated from the filesystem, or explicitly defined here.

 Create as many sidebars as you want.
 */
const sidebars: SidebarsConfig = {
  // Laravel Zod Generator documentation sidebar
  docsSidebar: [
    // Getting Started
    'intro',
    'installation', 
    'configuration',
    'quick-start',
    'development',
    
    // Usage Guides
    {
      type: 'category',
      label: 'Usage',
      collapsed: false,
      items: [
        'usage/basic-usage',
        'usage/attributes',
        'usage/custom-validation-rules',
        'usage/generation',
        'usage/typescript-usage',
      ],
    },

    // Advanced Features
    {
      type: 'category',
      label: 'Advanced',
      collapsed: false,
      items: [
        'advanced/custom-extractors',
        'advanced/custom-type-handlers',
        'advanced/inheritance',
        'advanced/integration',
      ],
    },

    // Reference Documentation
    {
      type: 'category',
      label: 'Reference',
      collapsed: false,
      items: [
        'reference/validation-rules',
        'reference/troubleshooting',
      ],
    },

    // Examples
    {
      type: 'category',
      label: 'Examples',
      collapsed: false,
      items: [
        'examples/form-request',
        'examples/spatie-data',
        'examples/custom-validation',
        'examples/real-world',
      ],
    },
  ],
};

export default sidebars;
