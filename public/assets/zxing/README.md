# ZXing Library (Browser UMD)

## Version
- **@zxing/library**: 0.23.0
- **Source**: https://unpkg.com/@zxing/library@0.23.0/umd/index.min.js
- **Downloaded**: 2026-05-27

## Usage
This is the minified UMD bundle of the ZXing library for browser-based QR code scanning.

```html
<script src="/assets/zxing/zxing-0.23.0.min.js"></script>
```

Exposes `window.ZXing` with:
- `ZXing.BrowserQRCodeReader`
- `decodeFromVideoDevice(...)`
- Other decoding methods

## Updates
To upgrade to a new version:
1. Download from: `https://unpkg.com/@zxing/library@<new-version>/umd/index.min.js`
2. Save as `zxing-<new-version>.min.js`
3. Update the `<script src="...">` in `app/views/entrance/entrance-scanner.tpl`

## License
See https://github.com/zxing/zxing for licensing information.

