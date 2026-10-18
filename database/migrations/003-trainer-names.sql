-- Apply the requested trainer names using the same Latin spelling in all six locales.
-- 002-public-copy-refresh first converts the original labelled seed names to these old names.
-- Change only exact seeded title/alt values, preserving CMS edits and all content relationships.
-- Stable legacy slugs remain identifiers and are intentionally not renamed.
-- On a fresh database this runs before seeding, whose current names already match the request.
START TRANSACTION;

UPDATE content_translations tr JOIN content c ON c.id=tr.content_id
SET tr.title='Boban'
WHERE c.kind='trainer' AND BINARY c.slug=BINARY 'alex-demo'
  AND tr.language IN ('en','mk','sq','de','fr','it')
  AND BINARY tr.title=BINARY CASE WHEN tr.language='mk' THEN CONVERT(0xd090d0bbd0b5d0bad181 USING utf8mb4) ELSE 'Alex' END;
UPDATE content_translations tr JOIN content c ON c.id=tr.content_id
SET tr.alt='Boban'
WHERE c.kind='trainer' AND BINARY c.slug=BINARY 'alex-demo'
  AND tr.language IN ('en','mk','sq','de','fr','it')
  AND BINARY tr.alt=BINARY CASE WHEN tr.language='mk' THEN CONVERT(0xd090d0bbd0b5d0bad181 USING utf8mb4) ELSE 'Alex' END;

UPDATE content_translations tr JOIN content c ON c.id=tr.content_id
SET tr.title='Emilija'
WHERE c.kind='trainer' AND BINARY c.slug=BINARY 'mira-demo'
  AND tr.language IN ('en','mk','sq','de','fr','it')
  AND BINARY tr.title=BINARY CASE WHEN tr.language='mk' THEN CONVERT(0xd09cd0b8d180d0b0 USING utf8mb4) ELSE 'Mira' END;
UPDATE content_translations tr JOIN content c ON c.id=tr.content_id
SET tr.alt='Emilija'
WHERE c.kind='trainer' AND BINARY c.slug=BINARY 'mira-demo'
  AND tr.language IN ('en','mk','sq','de','fr','it')
  AND BINARY tr.alt=BINARY CASE WHEN tr.language='mk' THEN CONVERT(0xd09cd0b8d180d0b0 USING utf8mb4) ELSE 'Mira' END;

UPDATE content_translations tr JOIN content c ON c.id=tr.content_id
SET tr.title='Martin'
WHERE c.kind='trainer' AND BINARY c.slug=BINARY 'noah-demo'
  AND tr.language IN ('en','mk','sq','de','fr','it')
  AND BINARY tr.title=BINARY CASE WHEN tr.language='mk' THEN CONVERT(0xd09dd0bed0b0 USING utf8mb4) ELSE 'Noah' END;
UPDATE content_translations tr JOIN content c ON c.id=tr.content_id
SET tr.alt='Martin'
WHERE c.kind='trainer' AND BINARY c.slug=BINARY 'noah-demo'
  AND tr.language IN ('en','mk','sq','de','fr','it')
  AND BINARY tr.alt=BINARY CASE WHEN tr.language='mk' THEN CONVERT(0xd09dd0bed0b0 USING utf8mb4) ELSE 'Noah' END;

COMMIT;
