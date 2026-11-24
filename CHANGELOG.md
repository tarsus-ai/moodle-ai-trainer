# Changelog

All notable changes to the Tarsus plugin for Moodle. The plugin follows
[semantic versioning](https://semver.org/); the version is `$plugin->release`
in `version.php`.

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
