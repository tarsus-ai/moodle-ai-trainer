# Changelog

All notable changes to the Tarsus plugin for Moodle. The plugin follows
[semantic versioning](https://semver.org/); the version is `$plugin->release`
in `version.php`.

## [2.0.1] – 2026-11-28

Two small fixes.

### Fixed

- A picture now opens in the enlarged view also after an interactive page was enlarged before. Until now, the picture did not open until the page was reloaded.
- **Test connection** (*Status* tab of the Tarsus administration) says clearly when Tarsus does not accept the API key, the brain ID or the license key, and what to do. Until now, it said "Could not contact the brain. Please try again in a moment." – which did not help.

To update, install the new zip as under [Updating](https://github.com/tarsus-ai/moodle-ai-trainer#updating) in the README. Your settings, the indexed material, the learners' figures and their grades are kept.

## [2.0.0] – 2026-11-25

The coach as *Tarsus coach* (`mod_tarsuscoach`), reworked to Moodle's standards. It replaces 1.0.0 and 1.0.1, which were called `mod_tarsus` – a name another Moodle plugin also uses – so Moodle installs 2.0.0 as a plugin of its own: see *Coming from 1.0.0 or 1.0.1?* under [Updating](https://github.com/tarsus-ai/moodle-ai-trainer#updating) in the README.

### Changed

- **New name**: the plugin is `mod_tarsuscoach` in the folder `mod/tarsuscoach` (Moodle 5.1 and newer: `public/mod/tarsuscoach`), the activity is called *Tarsus coach* (*Tarsus-Coach*), its tables start with `tarsuscoach_`.
- **The live interaction runs sealed off from Moodle**, like the AI-driven pages: its page runs in a sandboxed frame (`sandbox="allow-scripts allow-forms"`) and cannot reach Moodle, its cookies or the surrounding page. Browsers give such a frame no microphone, so the plugin's own script records it while a call runs and passes the sound on to the call.
- **Moodle 4.5 to 5.3**, PHP 8.1 or newer. Moodle 4.1 to 4.4 are no longer supported.
- **The coach talks to Moodle through external functions** (`mod_tarsuscoach_*`, listed in `db/services.php`) with Moodle's session and capability checks.
- **Pages from Mustache templates**: the coach, the materials page, the help and the administration pages. The scripts are AMD modules that load only on the plugin's own pages.
- **Every call to Tarsus goes through Moodle's `curl` class**: Moodle's web proxy and its list of blocked hosts apply.
- **Moodle's dialogues** ask before a live conversation is ended and before material is removed, in place of the browser's.

### Added

- **Dark colour mode**: the coach, its panels and the plugin's pages turn dark where Moodle shows a page dark – in Moodle 5.3 once *Enable colour modes* is on in the Boost theme's *Experimental settings* and a person chooses *Dark*.
- **Tests**: 60 PHPUnit tests and 8 Behat scenarios. Moodle's code checker finds no errors and no warnings; ESLint and Stylelint with Moodle's rules find nothing.

### Fixed

- **Kill switch**: while it is on, the voice list in the settings and the *Brains* page no longer ask the brain.
- **Names** of courses, activities, groups and material go through Moodle's filters, so names in several languages show in the reader's language.

## [1.0.1] – 2026-11-24

### Fixed

- Moodle's message drawer could fail to start on the coach and the *Materials* page (Moodle 5: "Cannot read properties of undefined (reading 'querySelectorAll')" in the browser console; before Moodle 5 the drawer just did not open on that page). The plugin's scripts held up the page while they loaded, so Moodle's own start-up code sometimes looked for the drawer before it was there. They now load alongside the page and run once it has been read.

## [1.0.0] – 2026-11-24

The Tarsus AI learning coach as a Moodle activity – the same coach as Tarsus for ILIAS 2.1.0.

### Added

- **Four ways to learn**: *Build knowledge* (learning objectives, then units of slides with pictures, narration and check questions), *Test knowledge* (questions at the learner's level, with feedback and experience points), *Live interaction* (a voice call with the AI trainer that shows slides while it talks) and *Ask a question* (typed or spoken; answers come only from the course material, with *View sources*).
- **Course material**: files, folders, pages, web links, books, wikis and glossaries of the course. Teachers index them and choose per activity which ones the coach may use; changed material is sent again by a scheduled task.
- **Gradebook and completion**: the learner's result goes to the gradebook (points or a scale), and a completion rule completes the activity once the learner's progress reaches a percentage.
- **For teachers**, next to the coach: the activity's settings, an evaluation of every learner (result, progress, completion, groups, CSV export) and each learner's history.
- **For administrators** (*Site administration › Plugins › Activity modules › Tarsus*): the connection to the brain, the assistant's name, picture and voice, demo mode, kill switch and rate limit, and the pages *Plan & credits*, *Brains*, *Deletions* and *Status* (with *Test connection*).
- **Text and formulas like in the Tarsus app**: React-Markdown's pipeline with GitHub tables and KaTeX 0.16.47 formulas, rendered in the browser; KaTeX and its fonts ship with the plugin.
- **Screen readers and keyboard** (WCAG 2.2 AA): a skip link, headings on every card, answers, verdicts and results read out, focus where reading goes on, dialogs that keep the focus, right and wrong in words and symbols.
- **Backup and restore**: course backups, imports, copies and *Duplicate* carry the activity's settings and the material it may use.
- **Privacy API**: export and deletion of a learner's data, including the conversation in the brain.
- **Course reset**: deletes the learners' figures and grades.
- **Demo mode** after installation: the whole interface works without a brain.
- **English and German**.
- Runs on **Moodle 4.1 to 5.3** (PHP 8.1 or newer).
