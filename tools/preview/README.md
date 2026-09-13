# Admin screen preview

Builds every React admin screen as a plain page with fixture data, so it can be opened
and screenshotted without a running WordPress.

```bash
SUBKIT_PREVIEW_OUT=/path/to/out npx wp-scripts build --config tools/preview/webpack.config.js
```

Then open `index.html` from `tools/preview/` next to the built `preview.js`, with a
`#screen` hash: `#dashboard`, `#list`, `#detail`, `#reports`, `#health`.
