# TODO

## Planned task
Fix UI issues in `index.php`:
- Ensure research media renders MP4/WebM/MOV as `<video>` (not `<img>`)
- Redesign/ensure “Best Times” renders correctly in the calendar side panel

## Steps
1. Backup `index.php` (copy to `index.php.bak_<timestamp>`).
2. Locate the exact JS blocks:
   - `renderResearchImages: function() { ... }`
   - the “Best Times” rendering block that builds `bestTimesStr` / group cards
3. Patch video detection logic to consistently detect video by type + file extension on the actual URL (`img.url`/`media_url`).
4. Patch “Best Times” rendering to target the correct container and update markup.
5. Verify by reloading `index.php` and checking:
   - uploaded mp4 uses a `<video>` element
   - “Best Times” shows in the calendar side panel

Status: 4/5 complete

## Backup

- Created backup: `index.php.bak_20260522_225015`



