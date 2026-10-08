# Accessibility statement

Last reviewed: 2026-10-08.

The icon picker is the one interactive surface of this plugin, and it is built
to be operable without a mouse.

## What we implement

- Full keyboard navigation in the picker: `ArrowUp` / `ArrowDown` move through
  results, `Home` / `End` jump to the ends, `Enter` selects, `Escape` closes
  the popover and returns focus to the selected icon button.
- Focus management: opening the picker moves focus to the search input;
  closing returns it to the control that opened it.
- Decorative SVG output (picker previews and rendered field icons) carries
  `aria-hidden="true"` and `focusable="false"`, so icon-only markup never
  pollutes the accessibility tree. When an icon is meaningful, the theme or
  block author supplies the text alternative - the plugin stores the icon name
  so it can be rendered as a label.
- The search field is a real `<input>` with a visible label; filtering is
  instant and does not trap the keyboard.
- The grid is paginated (100 icons per page) instead of dumping 5,000 focusable
  nodes into the tab order.
- All strings are translatable through the standard text domain.

## Known limitations

- The picker is a custom widget inside the ACF form; screen-reader announcement
  of live result counts depends on the browser/AT combination.
- Color contrast of the picker chrome follows ACF's own admin palette.

## Feedback

Accessibility defects are treated as bugs. Report them through the
WordPress.org support forum for this plugin or at
[support@mudrava.com](mailto:support@mudrava.com) with the screen, browser and
assistive technology you used.
