# Supplied image replacements

Updated 18 October 2026. The existing PHP templates generate the HTML; the site does not have standalone static HTML pages. The overall layout, colors and interactions are preserved.

| Supplied file | Website asset | Placement |
|---|---|---|
| `image.png` | `public/assets/logo.png` | Header, footer, favicon |
| `image2.png` (identical to `image1.png`) | `public/assets/strength.png` | Hero, equipment, strength cards/detail pages, gallery, login artwork |
| `CARDIO.webp` | `public/assets/cardio.webp` | Cardio cards/detail pages and gallery |
| `FUNCT.webp` | `public/assets/functional.webp` | Functional service/classes and gallery |
| `image3.png` | `public/assets/about.png` | About page, full composition |

Original image bytes are preserved. Do not rename WebP images to PNG: their actual format is WebP. Existing image styles continue to use responsive cover crops where appropriate; the logo and About composition use contain. Pilates and trainer illustrations retained in this initial update are superseded for the records listed below. Wellness and location illustrations remain.

## Later browser comments — 18 October 2026

| Comment | Website asset or requested change |
|---|---|
| 1 | `public/assets/class-booking.png` — class-booking article |
| 2 | `public/assets/pilates.png` — Pilates service, studio and class imagery |
| 3 | `public/assets/membership-packages.png` — membership artwork on the first-visit article |
| 4 | `public/assets/trainer-boban.png` — Boban portrait |
| 5 | `public/assets/trainer-emilija.png` — Emilija portrait, supplied as `Screenshot 2026-10-18 204508.png` |
| 6 | `public/assets/trainer-martin.png` — Martin portrait |
| 7–9 | Alex → Boban, Mira → Emilija, Noah → Martin; requested Latin spellings in all six locales |

All six additional images preserve their supplied bytes; see [comment asset evidence](evidence/comment-image-assets.json). Bundled-image substitutions retain custom CMS uploads and image paths. The three names are updated in fresh seed data and by [migration 003](../database/migrations/003-trainer-names.sql) for existing installations, preserving custom title/alt edits, stable legacy slugs, IDs and relationships. The focused migration check passed **8/8** locally and the migration was applied to the local preview. Current browser verification is recorded separately in [TESTING.md](TESTING.md); the earlier image results do not validate these later changes.

## Updated source files

- [Header/footer and favicon HTML](../app/views/layout.php)
- [Home HTML](../app/views/home.php)
- [About HTML](../app/views/about.php)
- [Shared card/image rendering](../app/views/components.php)
- [Detail HTML](../app/views/detail.php), [class HTML](../app/views/class.php), [gallery HTML](../app/views/gallery.php) and [login/registration HTML](../app/views/auth.php)
- [Responsive CSS](../public/assets/styles.css)
- New `photo_strength`, `photo_cardio`, `photo_functional`, `photo_about`, `gallery_intro` and `gallery_caption` entries in `app/lang/{en,mk,sq,de,fr,it}.php`
- [Existing content-display check](../tests/content_display.php) updated to use the new photo and fallback paths

The shared renderer substitutes bundled placeholder paths so existing and fresh databases both display the supplied photos. CMS uploads and unrelated custom paths are preserved. Directly selected photos retain an editor's nonempty custom alt text. Image substitutions need no database migration; the later trainer renames require migration 003 on existing installations.

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

The existing global image `max-width: 100%` and responsive widths are retained. See [verification](TESTING.md) and the [initial asset hashes](evidence/image-assets.json). Refresh the local browser with Ctrl+F5 to load the updated images/CSS. Manual upload must include the initial five assets, the six later assets and migration 003. The source update follows the authorized GitHub workflow; deployment remains paused.
