# Supplied image replacements

Updated 18 October 2026. The existing PHP templates generate the HTML; the site does not have standalone static HTML pages. The overall layout, colors and interactions are preserved.

| Supplied file | Website asset | Placement |
|---|---|---|
| `image.png` | `public/assets/logo.png` | Header, footer, favicon |
| `image2.png` (identical to `image1.png`) | `public/assets/strength.png` | Hero, equipment, strength cards/detail pages, gallery, login artwork |
| `CARDIO.webp` | `public/assets/cardio.webp` | Cardio cards/detail pages and gallery |
| `FUNCT.webp` | `public/assets/functional.webp` | Functional service/classes and gallery |
| `image3.png` | `public/assets/about.png` | About page, full composition |

Original image bytes are preserved. Do not rename WebP images to PNG: their actual format is WebP. Existing image styles continue to use responsive cover crops where appropriate; the logo and About composition use contain. Unrelated Pilates/studio, wellness, trainer and location illustrations remain.

## Updated source files

- [Header/footer and favicon HTML](../app/views/layout.php)
- [Home HTML](../app/views/home.php)
- [About HTML](../app/views/about.php)
- [Shared card/image rendering](../app/views/components.php)
- [Detail HTML](../app/views/detail.php), [class HTML](../app/views/class.php), [gallery HTML](../app/views/gallery.php) and [login/registration HTML](../app/views/auth.php)
- [Responsive CSS](../public/assets/styles.css)
- New `photo_strength`, `photo_cardio`, `photo_functional`, `photo_about`, `gallery_intro` and `gallery_caption` entries in `app/lang/{en,mk,sq,de,fr,it}.php`
- [Existing content-display check](../tests/content_display.php) updated to use the new photo and fallback paths

The shared renderer substitutes bundled placeholder paths so existing and fresh databases both display the supplied photos. CMS uploads and unrelated custom paths are preserved. Directly selected photos retain an editor's nonempty custom alt text. No database migration is needed.

## Narrow CSS addition

```css
.brand img {
  height: auto;
  aspect-ratio: 1;
  object-fit: contain;
  flex-shrink: 0;
  border-radius: 3px;
}
.detail-visual .about-photo {
  height: auto;
  max-height: none;
  aspect-ratio: auto;
  object-fit: contain;
}
```

The existing global image `max-width: 100%` and responsive widths are retained. See [verification](TESTING.md) and [asset hashes](evidence/image-assets.json). Refresh the local browser with Ctrl+F5 to load the updated images/CSS. The full source archive contains these files and all five images; no upload or deployment was performed.
