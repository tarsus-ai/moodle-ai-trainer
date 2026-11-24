# Tarsus for Moodle

**The Tarsus AI learning coach as a Moodle activity.** Explanations, graphics, simulations, exercises and live calls – generated in the dialogue, from your own course material only. Learners **build**, **test**, **talk** and **ask**; teachers choose the material and follow the progress. The same coach as [Tarsus for ILIAS](https://github.com/tarsus-ai/ilias-ai-trainer).

> This repository contains **no API keys and no brain IDs**. The plugin starts in demo mode; the credentials for a real brain are entered in Moodle's site administration and stay in the Moodle database.

The step-by-step installation guide with pictures follows with the first release.

## Accessibility: screen readers and keyboard

The coach works **without a mouse** and **with screen readers** (NVDA, JAWS, VoiceOver, TalkBack). It follows WCAG 2.2 level AA and the WAI-ARIA patterns for dialogs, radio groups and live regions – in every mode, in the teacher's panels and on the materials page.

**Keys**

| Key | What it does |
|---|---|
| <kbd>Tab</kbd> / <kbd>Shift</kbd>+<kbd>Tab</kbd> | Next / previous control. The first stop in the coach is **Skip to the input field** *(Zum Eingabefeld springen)*. |
| <kbd>Enter</kbd> | In the input field: sends the question. On a button: presses it. On a picture: enlarges it. |
| <kbd>Shift</kbd>+<kbd>Enter</kbd> | A new line in a question. |
| <kbd>Space</kbd> | Presses a button, ticks a checkbox. |
| Arrow keys | Choose an answer option of a question. |
| <kbd>Esc</kbd> | Closes a side panel, a picture or the help. In the input field: drops the *Ask about this* reference. |

**What a screen reader says**

- The coach is a region called *AI trainer*. The conversation has a heading, and so has every card – your question, each question and each learning unit – so heading navigation (<kbd>H</kbd> in NVDA and JAWS) jumps from card to card.
- After you send a question: "Tarsus is working on it." Then the answer is read out. Formulas are read as their TeX (`a^2 + b^2 = c^2`); on the card they are MathML, which screen readers with math support (NVDA with MathCAT, JAWS, VoiceOver) read as mathematics.
- Switching the mode says the mode and the next step: "Test knowledge. The button “Start” brings the next step."
- A new question gets the keyboard focus – "Question, Knowledge, easy" – and is read out. The options say their place: "Mars, radio button, 2 of 4".
- **Check** puts the focus on the verdict – "Correct.", "Not correct." or "Partly correct: 60 %" – and reads the feedback, the experience points and the result. Right and wrong are words and a ✓ or ✗, never a colour alone. Pressing **Check** with nothing chosen says so and puts the focus on the first open field.
- In a learning unit, the focus goes to the slide's title and the slide is read. **Next** says "Slide 2 of 5" and reads the slide; the focus stays on the button.
- Side panels (sources, settings, evaluation, history) are dialogs: the focus moves in, <kbd>Tab</kbd> stays inside, <kbd>Esc</kbd> closes them and the focus returns to the button that opened them. Pictures and enlarged pages are dialogs too.
- While Tarsus answers, the input field keeps the focus; you never lose your place.

**For teachers:** switch on **Accessible content** *(Barrierefreie Inhalte)* in the activity's settings. Tarsus then describes in words what pictures, diagrams and simulations show, so learners who cannot see them get the same information.

**Live interaction:** the call itself runs in a page Tarsus provides. Starting it, its frame and the mode around it work with the keyboard and are labelled; the focus moves into the call's frame when it starts.

**How it was checked:** [axe-core](https://github.com/dequelabs/axe-core) (WCAG 2.0, 2.1 and 2.2, levels A and AA, plus best practices) on every screen – question and answer, sources, a question before and after **Check**, learning units, live interaction, the teacher's settings and evaluation panels and the materials page – reports no violations on Moodle 5.3 (Boost theme). A keyboard-only session was recorded with a virtual screen reader ([Guidepup](https://github.com/guidepup/virtual-screen-reader)); an excerpt:

```text
Tab                link, Skip to the input field
Enter              textbox, Ask a question about the course materials…
(question, Enter)  Tarsus is working on it.
                   Tarsus answers: … A formula for good measure: a^2 + b^2 = c^2
Enter on "Start"   Question, Knowledge, easy (heading) – Which planet is known as the Red Planet?
Tab, Tab           radio, Venus, not checked, 1 of 4
Arrow down         radio, Mars, 2 of 4
Tab, Enter         Correct. (heading) – Correct! Well spotted. 2 / 12 XP, Result 17 %
Enter on "View sources"   dialog, References, modal
Esc                button, View sources
```

Found something that does not work with your screen reader? Please [open an issue](https://github.com/tarsus-ai/moodle-ai-trainer/issues) – it is fixed with priority.
