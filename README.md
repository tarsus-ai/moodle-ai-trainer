<p align="center">
  <a href="https://tarsus.ai"><img src="https://framerusercontent.com/images/bhXmm51vAp3AcBXb7St6VJakI4.webp" alt="The Tarsus AI learning coach: a course with learning goals, explanation cards, exercises and progress" width="920"></a>
</p>

<h1 align="center">Tarsus for Moodle</h1>

<p align="center">
  <b>The Tarsus AI learning coach as an activity in every Moodle course.</b><br>
  Explanations, graphics, simulations, exercises and live calls – generated in the dialogue, from your own course material only.<br>
  Learners <b>build</b>, <b>test</b>, <b>talk</b> and <b>ask</b>; teachers choose the material and follow the progress.
</p>

<p align="center">
  <a href="https://github.com/tarsus-ai/moodle-ai-trainer/releases/latest"><img alt="Latest release" src="https://img.shields.io/github/v/release/tarsus-ai/moodle-ai-trainer?label=release&color=1f4e79"></a>
  <img alt="Moodle 4.1 to 5.3" src="https://img.shields.io/badge/Moodle-4.1%20to%205.3-f98012">
  <img alt="Languages" src="https://img.shields.io/badge/UI-English%20%7C%20Deutsch-555">
  <a href="LICENSE"><img alt="Licence GPL-3.0-or-later" src="https://img.shields.io/badge/licence-GPL--3.0--or--later-2e7d32"></a>
</p>

<p align="center">
  <a href="https://tarsus.ai">Website</a> ·
  <a href="https://tarsus.ai/funktionen">Features</a> ·
  <a href="#getting-access">Get access</a> ·
  <a href="https://github.com/tarsus-ai/moodle-ai-trainer/releases/latest">Download</a> ·
  <a href="#installation">Install</a> ·
  <a href="#watch">Videos</a> ·
  <a href="https://www.youtube.com/@tarsus-ai">YouTube</a>
</p>

---

Tarsus is an AI learning coach for training providers, companies and universities. It explains, lets learners practise, talks with them and answers their questions – always from the organisation's own documents, at the right level for each person. **Tarsus for Moodle** brings this coach into Moodle as an activity (`mod_tarsuscoach`): a *Tarsus* activity sits in a course section like a quiz or a forum, uses only the course material the teacher has indexed and included, and reports to Moodle's gradebook and activity completion. The conversations run in the Tarsus brain; the plugin shows exactly what Tarsus sends and keeps only the figures Moodle needs. It is the same coach as [Tarsus for ILIAS](https://github.com/tarsus-ai/ilias-ai-trainer).

> This repository contains **no API keys and no brain IDs**. The plugin starts in demo mode; the credentials for a real brain are entered in Moodle's site administration and stay in the Moodle database – the browsers of learners and teachers never receive the API key. See [Getting access](#getting-access).

**Contents** · [What the coach does](#what-the-coach-does) · [Inside Moodle](#inside-moodle) · [Watch](#watch) · [Getting access](#getting-access) · [Requirements](#requirements) · [Installation](#installation) · [Updating](#updating) · [If something does not work](#if-something-does-not-work) · [Configuration](#configuration) · [Packages, AI credits and the monthly limit](#packages-ai-credits-and-the-monthly-limit) · [Interaction types](#interaction-types) · [Grades and completion](#grades-and-completion) · [Backup, restore and course reset](#backup-restore-and-course-reset) · [Accessibility](#accessibility-screen-readers-and-keyboard) · [Data and privacy](#data-and-privacy) · [Development](#development) · [Licence](#licence)

## What the coach does

### Four ways to learn

| Build knowledge | Test knowledge | Ask a question |
|:--:|:--:|:--:|
| <img src="https://framerusercontent.com/images/s0fmuHNzzQzugFWdZ64BNRzBZVE.webp" alt="Explanation card built from the course material" width="400"> | <img src="https://framerusercontent.com/images/hgagwUKE5rL19zOgU4zCXHDL8wk.webp" alt="Spreadsheet task with line-by-line feedback" width="400"> | <img src="https://framerusercontent.com/images/Gg6PdLZrXoksmnEkebq2iomzcCM.webp" alt="The coach answers a question and shows the calculation path" width="400"> |
| Explanation cards, one idea at a time, with examples for the target group and optional narration. A check question closes each unit. | Exercises with feedback that moves learners on: single choice, true/false, open questions, rule of three, spreadsheet calculations and accounting exercises – with experience points and a progress bar. | Questions in the learner's own words, typed or spoken, answered from the included material – with the sources the answer is based on. |

**Live interaction** – the fourth mode: the learner talks with the AI trainer in a voice call, and the slides for the conversation appear while it talks. Like every mode, teachers switch it on or off per activity.

### Graphics, simulations and 3D models on demand

| | | |
|:--:|:--:|:--:|
| <img src="https://framerusercontent.com/images/Cosivt9X6HrNKgVnRe4uAuwSXI.webp" alt="Rotatable 3D cylinder with diameter and height" width="400"> | <img src="https://framerusercontent.com/images/uZ93hce6VN7ddmDdqNunRz8mg.webp" alt="Interactive characteristic curve: a slider moves the operating point" width="400"> | <img src="https://framerusercontent.com/images/XuhQqOxDZ7REyDl40HnvMnVjpms.webp" alt="Decision tree: what does the body orbit?" width="400"> |
| **3D models** that learners can turn and measure. | **Simulations** where a slider changes the physics and the result follows. | **Diagrams and animations** drawn for the question at hand. |

With the *AI-driven* interaction type the coach answers inside Moodle with such pages – small HTML programs rendered in a sandboxed frame – in *Ask a question* and in *Build knowledge*. See [Interaction types](#interaction-types).

### Many task formats

| | | | |
|:--:|:--:|:--:|:--:|
| <img src="https://framerusercontent.com/images/sdtCvG7M3Q59Nl80mwa2yOOaMTY.webp" alt="Single-choice question with explanation" width="300"> | <img src="https://framerusercontent.com/images/du1FTrsuwAElUTZUEEC5v1euI.webp" alt="Open question with partial credit and feedback on what is missing" width="300"> | <img src="https://framerusercontent.com/images/uNSphTDFSP6R85PZw7wZVvGQK0.webp" alt="True-or-false task with a check button" width="300"> | <img src="https://framerusercontent.com/images/rmkJPlhI2NqsHY2Xt1UFnddNeoU.webp" alt="Calculation chain with two correctly computed values" width="300"> |
| Single choice with an explanation | Open questions with partial credit | True or false | Calculation chains, spreadsheets and accounting exercises |

### The right level for every person

The coach adapts to the **DQR level**, the **taxonomy stage** (Anderson & Krathwohl), the **learning pace**, the **target group** and the **thinking level** that the teacher sets per activity. Which task comes next is decided from the learner's answers – the same ideas as in item response theory – so strong learners are stretched and nobody is left behind. With **accessible content** switched on, the coach describes in words what pictures and diagrams show, for learners who use a screen reader. Read more in the Tarsus [Praxiswissen](https://tarsus.ai/praxiswissen) articles and watch the explainers under [Watch](#watch).

## Inside Moodle

<p align="center">
  <img src="docs/screenshots/app/app_ai_driven.png" alt="A learner asks for a simulation and receives an interactive page as the answer in Moodle" width="900">
</p>

### For learners

| Ask a question | View sources |
|:--:|:--:|
| ![An answer with View sources at the top right](docs/screenshots/app/app_ask_answer.png) | ![The sources of an answer in a panel sliding in from the right](docs/screenshots/app/app_sources.png) |
| Typed or spoken questions, answered from the included material. *View sources* sits at the top right of every answer Tarsus based on the material. | The passages, tables and pictures Tarsus used, in a panel that slides in from the right – each with a link to the material in Moodle. |

| Build knowledge | Test knowledge |
|:--:|:--:|
| ![A learning unit with slides and narration](docs/screenshots/app/app_build.png) | ![A question with feedback, experience points and the result](docs/screenshots/app/app_test.png) |
| Learning objectives, then units of slides with pictures and diagrams, narration on request and check questions. *Ask about this* sends the next question about exactly that. | Questions at the learner's level, feedback after **Check**, experience points and the result in percent. |

| Live interaction |
|:--:|
| ![A voice call with the AI trainer: Tarsus is listening](docs/screenshots/app/app_live.png) |
| A voice call with the AI trainer. The learner just talks; the slides for the conversation appear on the left while Tarsus answers. |

- The modes in the order Tarsus uses: **Build knowledge**, **Test knowledge**, **Live interaction**, **Ask a question**. Widgets form a centred column like in Tarsus.
- **Spoken questions:** *Switch to voice input*, record, listen back, send – the answer shows the recording and the text Tarsus heard.
- **Live interaction:** a voice call with the AI trainer; the page of the call may use the microphone.
- **Screen readers and keyboard:** everything works without a mouse, and screen readers hear the answers, the results and every step – see [Accessibility](#accessibility-screen-readers-and-keyboard).
- **English and German**, following the user's language in Moodle. A **help button** opens the support links and videos the administrator configured.

### For teachers

The buttons above the coach open panels that slide in from the right, as in Tarsus:

| Settings | Evaluation | History |
|:--:|:--:|:--:|
| ![Settings panel](docs/screenshots/app/app_teacher_settings.png) | ![Evaluation panel](docs/screenshots/app/app_teacher_evaluation.png) | ![History panel](docs/screenshots/app/app_teacher_history.png) |
| Modes, interaction type, thinking level, didactics (conversation style, DQR level, target group, taxonomy levels, question types, learning pace), sources, spoken questions and accessible content. Options outside the customer's package are shown locked. The same settings are in the activity's *Settings* page. | Every learner's result, progress and completion, the distribution of results, a filter by group and a CSV export. | One learner's conversation, read only, and how their experience points and learning units grew over the last 30 days. |

| Materials |
|:--:|
| ![The Materials page of an activity](docs/screenshots/app/app_materials.png) |
| Index the course's files, folders, pages, web links, books, wikis and glossaries into the brain and decide per activity which of them the coach may use. Edited material is sent again by itself (see [Keep material up to date automatically](#keep-material-up-to-date-automatically)). |

### For administrators

| Plan & credits | Status |
|:--:|:--:|
| ![Plan and credits](docs/screenshots/app/app_plan.png) | ![Connection test on the Status page](docs/screenshots/app/app_status.png) |
| The customer's package, the included AI credits and the price beyond them, the monthly credit limit and the recipients of the warnings. | Connection test, guardrails, and the month's AI credits exactly as the brain reports them. |

- **Monthly credit limit** with banners at 50 %, 75 %, 90 % and 100 % and one e-mail per threshold and month.
- **Guardrails** on every outbound call: kill switch, circuit breaker, monthly limit and rate limit.
- **Demo mode** answers with built-in samples, so every screen can be tried before an API key exists.

## Watch

Explainers from the [Tarsus YouTube channel](https://www.youtube.com/@tarsus-ai/videos) (German):

<table>
  <tr>
    <td align="center" width="33%">
      <a href="https://www.youtube.com/watch?v=JuZLVYBUBLA"><img src="https://img.youtube.com/vi/JuZLVYBUBLA/hqdefault.jpg" alt="Tarsus | So funktioniert die Tarsus Architektur" width="300"></a><br>
      <b>How the Tarsus architecture works</b><br><sub>What happens between a learner's question and the coach's answer</sub>
    </td>
    <td align="center" width="33%">
      <a href="https://www.youtube.com/watch?v=kXTSVUBS9GQ"><img src="https://img.youtube.com/vi/kXTSVUBS9GQ/hqdefault.jpg" alt="E-Learning vs. Rapid Learning" width="300"></a><br>
      <b>E-learning vs. rapid learning</b><br><sub>Why generating the course in the dialogue beats producing it up front</sub>
    </td>
    <td align="center" width="33%">
      <a href="https://www.youtube.com/watch?v=NSif7VgL_w4"><img src="https://img.youtube.com/vi/NSif7VgL_w4/hqdefault.jpg" alt="Tarsus KI-Lerncoach | Bei BZEcom mit Daniel Hobein" width="300"></a><br>
      <b>Tarsus in practice</b><br><sub>The AI learning coach at BZEcom, with Daniel Hobein</sub>
    </td>
  </tr>
</table>

The ideas behind the coach, explained in short articles with videos:

- **[Item response theory with AI](https://tarsus.ai/praxiswissen/item-response-theory)** – why the coach weighs ability and task difficulty instead of counting right answers, and how that picks the next task.
- **[Taxonomies in e-learning](https://tarsus.ai/praxiswissen/dihk-taxonomie-stufen)** – thinking levels and knowledge types after Anderson & Krathwohl, and the "knowledge cube" the coach uses to see strengths, gaps and learning paths.
- **[The learning rate](https://tarsus.ai/praxiswissen/lernrate-adaptives-lernen)** – how adaptive learning updates a learner's level after each answer, and why surprising answers count more.
- **[DQR levels](https://tarsus.ai/praxiswissen/dqr-niveaus)** · **[Avoiding AI hallucinations](https://tarsus.ai/praxiswissen/ki-halluzinationen)** · **[Exam simulations](https://tarsus.ai/praxiswissen/pruefungssimulation-erstellen)** · all articles: [Praxiswissen](https://tarsus.ai/praxiswissen)

## Getting access

The plugin needs a Tarsus brain: the address of the Tarsus server (the *base URL*), an **API key** and a **brain ID** – or, for existing Tarsus customers, a **license key** in place of the brain ID. Tarsus sends these to your organisation; they are not in this repository and not in any release. Tarsus activates keys and brains by hand; there are no private test brains, only a sandbox brain shared by all partners on which every request is billed like any other.

- **Request access:** write to [info@tarsus.ai](mailto:info@tarsus.ai?subject=Tarsus%20for%20Moodle) or [book an introductory call](https://outlook.office.com/book/Erstgespraech@tarsus.ai/). More about the product on [tarsus.ai](https://tarsus.ai).
- **Try the coach first:** a free account on [zero.tarsus.ai](https://zero.tarsus.ai/) lets you upload your own documents and talk to the coach before anything is installed.
- **On-premise:** Tarsus can also deploy the entire Tarsus stack on your own infrastructure if your organisation requires it. [Talk to us](mailto:info@tarsus.ai?subject=Tarsus%20on-premise).
- **Packages:** Ask Question, Knowledge Test, Build Knowledge or Pay as you grow – see [Packages, AI credits and the monthly limit](#packages-ai-credits-and-the-monthly-limit).

Until a brain is configured the plugin runs in **demo mode** with simulated answers, so administrators and teachers can explore every screen.

## Requirements

- Moodle **4.1** to **5.3** with **PHP 8.1 or newer** (Moodle 4.1 also runs on older PHP; the plugin needs 8.1). PHP's `curl` and `dom` extensions, which Moodle needs anyway – Moodle checks all of this itself during the installation.
- Tested: version 1.0.0 on Moodle 4.1 (PHP 8.1), 4.5 (PHP 8.2), 5.0 (PHP 8.2), 5.1, 5.2 and 5.3 (PHP 8.4), with PostgreSQL – see [Development](#development).
- Your Moodle must be able to open HTTPS connections to the Tarsus server. If your Moodle reaches the internet through a proxy, the plugin uses the one entered in *Site administration › Server › HTTP › Web proxy*.
- Moodle must be reached over **https://** for spoken questions and the live interaction: browsers allow the microphone only on secure pages.
- Moodle's cron, which every Moodle needs anyway (see [Keep material up to date automatically](#keep-material-up-to-date-automatically)).

## Installation

You do not need to know Moodle. Follow the pictures: the **red boxes** show where to click, the **numbers** show the order. It takes about 15 minutes, and everything happens in your web browser.

**You need two things:**

1. **The e-mail from Tarsus** with three details: the *base URL*, the *API key* and the *brain ID*. Not there yet? Install anyway – the coach then shows sample answers until you enter them ([Getting access](#getting-access)).
2. **A Moodle login with administrator rights.** After logging in you see *Site administration* in the bar at the top.

Is your Moodle in German? Everything is in the same place; the German names are given in *(brackets)*. The pictures show Moodle 4.5: Moodle 5 looks a little different, but the names and the order are the same – where a step differs, it says so.

### Part 1 – Download the plugin

**1.** Open the [latest release](https://github.com/tarsus-ai/moodle-ai-trainer/releases/latest) and click **`Tarsus-moodle-plugin-1.0.0.zip`** under *Assets* (the number may be higher). Your browser saves the file, usually in your *Downloads* folder. Leave it as it is – do not unzip it.

### Part 2 – Install it in Moodle

**2.** Open your Moodle and log in with the administrator account: user name (1), password (2), **Log in** (3) *(Anmelden)*.

![Log in](docs/screenshots/guide/g01_login.png)

**3.** Click **Site administration** (1) *(Website-Administration)* in the bar at the top.

![Site administration](docs/screenshots/guide/g02_site_admin.png)

**4.** Click **Plugins** (1), then **Install plugins** (2) *(Plugin installieren)*.

![Plugins, then Install plugins](docs/screenshots/guide/g03_install_plugins.png)

**5.** Drag the zip file from your *Downloads* folder onto the box with the arrow (1). *Or:* click **Choose a file…**, then **Upload a file** *(Datei hochladen)*, **Browse…**, pick the zip file and click **Upload this file**.

![The box for the zip file](docs/screenshots/guide/g04_drop_zip.png)

**6.** The file name now shows in the box (1). Click **Install plugin from the ZIP file** (2) *(Plugin installieren)*.

![Install plugin from the ZIP file](docs/screenshots/guide/g05_install_zip.png)

**7.** Moodle checks the file: *Validation successful, installation can continue*. Click **Continue** (1) *(Weiter)*.

![Validation successful](docs/screenshots/guide/g06_validation.png)

**8.** Moodle checks your server – at the end of the list are the needs of Tarsus. Scroll to the end: it says *Your server environment meets all minimum requirements* (1). Click **Continue** (2) *(Weiter)*.

![The server meets all requirements](docs/screenshots/guide/g07_server_checks.png)

**9.** Moodle shows Tarsus as *To be installed*. Click **Upgrade Moodle database now** (1) *(Aktualisierung der Datenbank starten)*.

![Upgrade Moodle database now](docs/screenshots/guide/g08_upgrade.png)

**10.** *Success*. Click **Continue** (1) *(Weiter)*.

![Success](docs/screenshots/guide/g09_success.png)

**11.** Moodle now shows the settings of Tarsus, *New settings – Tarsus settings*. Copy the three details from the Tarsus e-mail into the boxes – exactly, without spaces at the start or the end: **Base URL** (1) *(Basis-URL)*, **API key** (2) *(API-Schlüssel)* and **Brain ID** (3) *(Brain-ID)*. For the API key, click *Click to enter text* first.

![The three details from the e-mail](docs/screenshots/guide/g10_connection.png)

> You are an existing Tarsus customer and got a *license key*? Put it into **License key**, just below, and leave the brain ID empty.

**12.** Scroll down and remove the tick at **Mock mode (demo)** (4) *(Mock-Modus (Demo))*. While it is ticked, the coach only shows sample answers.

![Remove the tick at Mock mode](docs/screenshots/guide/g11_demo_off.png)

**13.** Scroll to the end and click **Save changes** (5) *(Änderungen speichern)*.

![Save changes](docs/screenshots/guide/g12_save.png)

**14.** Moodle shows its *Notifications* *(Systemnachrichten)*. Click **Plugins** (1).

![Plugins](docs/screenshots/guide/g13_notifications.png)

**15.** Scroll down to **Tarsus** – it is among the *Activity modules* *(Aktivitäten)*, in alphabetical order – and click **Plan & credits** (2) *(Plan & Credits)*.

![Tarsus, then Plan & credits](docs/screenshots/guide/g14_plan_link.png)

**16.** Choose the package of your Tarsus contract (1), scroll down and click **Save changes** (2) *(Änderungen speichern)*.

![Choose the package](docs/screenshots/guide/g15_plan.png)

![Save changes](docs/screenshots/guide/g16_plan_save.png)

**17.** Click **Status** (1), then **Test connection** (2) *(Verbindung testen)*. A green message (3) means: Tarsus is connected. A red message? See [If something does not work](#if-something-does-not-work).

![Test the connection](docs/screenshots/guide/g17_test_connection.png)

The plugin is ready. Now give a course its coach.

### Part 3 – Give a course its coach

**18.** Open the course in which learners should use the coach (*My courses* in the bar at the top) and switch on **Edit mode** (1) *(Bearbeiten)* at the top right.

![Edit mode](docs/screenshots/guide/g18_edit_mode.png)

**19.** In the section where the coach should appear, click **Add an activity or resource** (2) *(Aktivität oder Material anlegen)*. In Moodle 5.0 and newer, click the **+** at the end of the section instead – in Moodle 5.3 it says **Add content** *(Inhalt hinzufügen)* – and then **Activity or resource** *(Aktivität oder Material)*.

![Add an activity or resource](docs/screenshots/guide/g19_add_activity.png)

**20.** Click **Tarsus** (3).

![Tarsus](docs/screenshots/guide/g20_chooser.png)

**21.** Type a name, for example *Learning coach* (1). Scroll to the end of the page and click **Save and display** (2) *(Speichern und anzeigen)*. Everything else can stay as it is; you can change it later.

![The name](docs/screenshots/guide/g21_name.png)

![Save and display](docs/screenshots/guide/g22_save_display.png)

**22.** Click **Materials** (1) *(Materialien)*. You see the files, pages and links of the course.

![Materials](docs/screenshots/guide/g23_materials_tab.png)

**23.** Click **Index this page** (1) *(Diese Seite indexieren)*. Tarsus now reads the material; this takes a few minutes.

![Index this page](docs/screenshots/guide/g24_index.png)

**24.** Click **Sync this page with the brain** (2) *(Diese Seite mit dem Brain abgleichen)* now and then, until the status says **Indexed** (3) *(Indexiert)*. Then click **Include all indexed on this page** (4) *(Alle Indexierten dieser Seite einbeziehen)*.

![Include the material](docs/screenshots/guide/g25_include.png)

**Done!** Your learners now find the coach in the course and can ask their first question:

![What learners see](docs/screenshots/guide/g26_done.png)

Teachers additionally see the buttons **Settings**, **Evaluation** and **History** (1) *(Einstellungen, Auswertung, Verlauf)* above the coach:

![What teachers see](docs/screenshots/guide/g27_teacher.png)

<details>
<summary><b>"Install plugins" is missing, or Moodle cannot write to its folders? Install it by hand.</b></summary>

Some Moodle sites switch off installing from the browser, or the web server may not write into Moodle's folders. Then whoever looks after the server puts the plugin in place:

1. Download `Tarsus-moodle-plugin-<version>.zip` from the [latest release](https://github.com/tarsus-ai/moodle-ai-trainer/releases/latest).
2. Unzip it into the `mod` folder inside the Moodle folder – in Moodle 5.1 and newer, into `public/mod`. The zip contains the folder `tarsuscoach`, so you end up with `mod/tarsuscoach` (or `public/mod/tarsuscoach`).
3. Give the `tarsuscoach` folder to the user that owns Moodle's files, for example `sudo chown -R www-data:www-data tarsuscoach`.
4. In Moodle, open **Site administration › Notifications** *(Website-Administration › Systemnachrichten)* and continue with [step 9](#part-2--install-it-in-moodle). On the command line, `sudo -u www-data php admin/cli/upgrade.php` in the Moodle folder does the same.

</details>

### Keep material up to date automatically

*For your IT.* The plugin has a scheduled task, **Tarsus: synchronise the brain** *(Tarsus: Brain synchronisieren)*, that runs every 15 minutes. When a teacher uploads a new version of a file, edits a page or changes a link, it removes the old version from Tarsus and sends the new one; material deleted in Moodle is removed from Tarsus too. Moodle runs scheduled tasks with its cron, which every Moodle needs anyway.

To check, open **Site administration › Server › Tasks › Scheduled tasks** *(Website-Administration › Server › Tasks › Geplante Tasks)*. Half an hour after the installation, the Tarsus row (1) should show a recent time under **Last run** (2) *(Letzte Ausführung)*:

![The Tarsus task among Moodle's scheduled tasks](docs/screenshots/guide/g28_cron.png)

If it says *Never*, set up Moodle's cron once on the server; replace `/var/www/moodle` with your Moodle folder:

```bash
echo '* * * * * www-data /usr/bin/php /var/www/moodle/admin/cli/cron.php >/dev/null' | sudo tee /etc/cron.d/moodle
```

Without the cron, changed material is sent again when a teacher opens the *Materials* page, or with **Re-index** *(Neu indexieren)* there.

## Updating

1. Download the newest zip, as in [part 1](#part-1--download-the-plugin).
2. In Moodle, open **Site administration › Plugins › Install plugins**, put the zip into the box and click **Install plugin from the ZIP file** – as in [steps 4 to 6](#part-2--install-it-in-moodle).
3. Moodle says that the folder of Tarsus already exists and will be replaced – that is what an update does. Click **Continue**, **Continue** again after the server check, then **Upgrade Moodle database now** and **Continue**.

Your settings, the indexed material, the learners' figures and their grades are kept. The [changelog](CHANGELOG.md) lists what changed in each version. Installed by hand? Replace the `tarsuscoach` folder with the new one and open *Site administration › Notifications*.

## If something does not work

| What you see | What to do |
|---|---|
| *Install plugins* is missing, or the check says Moodle cannot write to the `mod` folder | Install by hand – see *"Install plugins" is missing* under [part 3](#part-3--give-a-course-its-coach). |
| The check says the plugin needs a newer Moodle or PHP | Tarsus needs Moodle 4.1 or newer and PHP 8.1 or newer. Ask whoever looks after your Moodle to update it. |
| *Test connection* says the API key or brain is not accepted | Copy the API key and brain ID again from the e-mail, without spaces, and save. If it persists, ask Tarsus whether the key is activated. |
| *Test connection* says Tarsus cannot be reached | Your server may not reach the internet. Ask your IT to allow HTTPS connections from the Moodle server to the Tarsus address; if Moodle reaches the internet through a proxy, enter it in *Site administration › Server › HTTP › Web proxy* *(Web-Proxy)*. |
| Tarsus is missing when you add an activity | Open **Site administration › Plugins › Activity modules › Manage activities** and switch Tarsus on (the eye). |
| Learners read *The course materials are still being prepared* | Nothing is indexed and included yet, or Tarsus is still reading it. Check the *Materials* page ([steps 22 to 24](#part-3--give-a-course-its-coach)) and wait a few minutes. |
| The microphone button is missing or does nothing | Use Moodle over `https://`, and allow the microphone when the browser asks. Spoken questions and the live interaction can also be switched off in the teacher's *Settings*. |
| The coach still uses an old version of a file or page | The scheduled task sends changed material again within 15 minutes ([Keep material up to date automatically](#keep-material-up-to-date-automatically)). To do it at once, click **Re-index** *(Neu indexieren)* on the *Materials* page. A web link is read once: if only the page behind the same address changes, click **Re-index**. |
| A red message about the monthly limit | The month's AI credits reached the limit set on *Plan & credits*. Raise the limit there, or wait for the next month. |

The plugin writes the details of every failed call to the web server's error log, or to the task's log when the scheduled task runs (never the API key).

## Configuration

*Site administration › Plugins › Activity modules › Tarsus* has five pages:

| Page | What it holds |
|---|---|
| **Tarsus settings** | Connection to the brain (base URL, API key, brain ID, and the license key of an existing Tarsus customer), the assistant's name, ID, description and picture, the voice, demo mode, guardrails (kill switch, rate limit), and the help links shown behind the help button – one `Title \| URL` per line. |
| **Plan & credits** | The booked package, the included AI credits and the price beyond them, the monthly limit and the recipients of the warnings (see below), plus the features per package of the partner agreement. |
| **Brains** | The brains the API key can reach. |
| **Deletions** | Material withdrawals the brain has not confirmed yet. |
| **Status** | Connection test, customer type (partner customer or Tarsus license key), booked package, guardrail state, the month's AI credits as the brain reports them (with a refresh button) and the cost beyond the included amount. |

Changing the connection – leaving demo mode, another server, another brain ID or license key – sends the material the previous brain held to the new one by itself; Moodle says how many after saving.

Per activity, teachers set the modes, the interaction type, the thinking level, the didactics, sources, spoken questions, accessible content and the grade in the activity's settings – or in the *Settings* panel above the coach.

## Packages, AI credits and the monthly limit

The package the customer booked is recorded on the *Plan & credits* page. The plugin implements the rules of the [Tarsus partner agreement](https://tarsus.ai/impressum/partnervereinbarung): a customer reaches only the features of the booked package, and existing Tarsus customers are recognised by their license key. The figures are editable for custom agreements.

**Features per package** – each package contains the one before it:

| Feature | Ask Question | Knowledge Test | Build Knowledge | Pay as you grow |
|---|:--:|:--:|:--:|:--:|
| Ask a question: the AI answers from the documents | ✓ | ✓ | ✓ | ✓ |
| Test knowledge: the AI creates and marks quizzes | – | ✓ | ✓ | ✓ |
| Build knowledge: the coach explains step by step | – | – | ✓ | ✓ |
| Audio explainer in the coach | – | – | ✓ | ✓ |
| Spoken questions (voice input) | – | – | ✓ | ✓ |
| Agent mode (AI-driven interaction type) | – | – | ✓ | ✓ |
| Live interaction | – | – | ✓ | ✓ |

The package is enforced everywhere: features outside it are locked in the activity settings, hidden from the learner's mode switch and refused by the server. While no package is set on a live connection, the administration pages say so – until then Build Knowledge is assumed.

**AI credits come from the brain only.** Tarsus counts the AI credits of every request itself (fractions included – a step can cost 1.57 credits) and reports them per brain. The plugin fetches this figure – at most once a minute while people use the coach, from the scheduled task, and with *Refresh usage from the brain* on the *Status* page – and uses it for the monthly limit, the warnings and the cost. It does not count or estimate credits itself. What each kind of request costs is decided by Tarsus; the public overview is at [tarsus.ai/ki-interaktionen](https://tarsus.ai/ki-interaktionen). A higher **thinking level** (1–4, set per activity, 4 by default) gives more thorough answers and costs more credits.

- The subscription packages include 100,000 AI credits per **calendar month** (editable); Pay as you grow includes none. Included credits do not carry over. Credits beyond them are charged at the agreed price (0.32 EUR flat for partner customers unless agreed otherwise).
- **License key:** an existing Tarsus customer enters the license key next to the API key. Every call then carries the API key and the license key – and no brain ID, the two forms never mix – and the status pages show that Tarsus bills this customer directly; no partner price is applied.
- The **monthly limit** is the most AI credits the installation may spend per calendar month. It defaults to the included amount; `0` means no limit. Once it is reached, AI requests are refused with a clear message for learners and a banner for teachers and administrators. At 50 %, 75 %, 90 % and 100 % a banner appears and one e-mail per threshold and month goes to the configured recipients (or the site's administrator address). Raising the limit takes effect at once; lowering it shows what has already been used and asks for confirmation.

## Interaction types

Each activity has an interaction type, sent to the brain as `interaction_type` with every generate call:

- **trainer-driven** – the coach answers with text, formulas, tables, pictures and the usual question widgets.
- **ai-driven** – the coach may additionally answer with HTML pages: animations, simulations, small tools (a rotating cylinder whose volume follows the sliders, a capacitor discharge curve with a marker, a diagram of a process, …), both in *Ask a question* and in *Build knowledge*.

Pages are rendered in an `<iframe sandbox="allow-scripts allow-forms allow-pointer-lock">` with `srcdoc`, so they can run their scripts but cannot reach Moodle, its cookies or the surrounding page. They size themselves to their content; an *Enlarge* button opens a page in a dialog. The page of the **live interaction** is the one exception: it runs in a frame without the sandbox that may use the microphone (`allow="microphone; autoplay"`), because the call needs it. AI-driven interaction and the live interaction are available on the Build Knowledge and Pay as you grow packages.

## Grades and completion

Tarsus reports experience points (XP), learning units and the progress of every learner. The plugin shows XP and learning units as they are, and turns them into a **result in percent** – the learner's grade in Moodle's gradebook, and the figure in the *Evaluation* panel and the CSV export. Which figures count depends on the modes active in an activity (a mode is active when it is enabled in the settings and allowed by the package):

| Active modes | Result (gradebook) | Progress (for completion) |
|---|---|---|
| Test knowledge only | XP earned in percent of all XP | the brain's practice progress |
| Build knowledge only | learning units completed in percent | the brain's learning progress |
| Test and Build knowledge | XP earned in percent of all XP | learning units completed in percent |
| Neither (Ask a question or Live interaction only) | – (no grade) | – |

- **Grade:** the result in percent of the activity's *Maximum grade* *(Beste Bewertung)*, 100 by default – or, with a scale, the matching step of the scale. Type, maximum and *Grade to pass* *(Bestehensgrenze)* are set in the activity's settings under *Grade*, as for any activity.
- **Completion:** under *Completion conditions* *(Abschlussbedingungen)* choose **Learner reaches a progress of** *(Lernende erreichen einen Fortschritt von)* and a percentage. The activity counts as completed once the learner's progress reaches it. *View*, *Receive a grade* and *Receive a passing grade* work as usual.

A result that falls back to 0 – after *Reset chat history*, or a connection to another brain – is written to the gradebook as well. Figures are recomputed when the package or the modes change.

## Backup, restore and course reset

- **Backup and restore, import, course copy and *Duplicate*** carry the activity with its settings and the list of material it may use; in the new course the list points to the new course's material. The learners' conversations stay in the brain and are not part of a backup.
- **Course reset** *(Kurs zurücksetzen)*: under *Tarsus*, **Delete the learners' figures and results of Tarsus activities** clears the learners' figures and their grades.

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

**How it was checked:** [axe-core](https://github.com/dequelabs/axe-core) (WCAG 2.0, 2.1 and 2.2, levels A and AA, plus best practices) on every screen – question and answer, sources, a question before and after **Check**, learning units, live interaction, the teacher's settings and evaluation panels and the materials page – reports no violations on Moodle 4.1, 4.5, 5.0, 5.1, 5.2 and 5.3 (Boost theme). A keyboard-only session was recorded with a virtual screen reader ([Guidepup](https://github.com/guidepup/virtual-screen-reader)); an excerpt:

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

## Data and privacy

- The plugin talks to the brain over HTTPS only; every call carries the installation's API key and brain ID – or, for an existing Tarsus customer, the API key and the license key – and passes the guardrail pipeline (kill switch → circuit breaker, which opens after five consecutive outages for five minutes → monthly credit limit → rate limit). Refusals from Tarsus are shown with their own clear message and do not trip the breaker; nothing is retried blindly.
- The plugin keeps its data in six tables: `tarsuscoach` (the activities and their settings), `tarsuscoach_material` (the index map of the material sent to the brain), `tarsuscoach_inclusion` (the material each activity may use), `tarsuscoach_progress` (the latest figures per learner), `tarsuscoach_usage` (a log of the requests sent) and `tarsuscoach_guardrail` (the guardrail state), plus its settings in Moodle's configuration. Uninstalling the plugin removes them.
- Learners are sent to the brain under a **pseudonym** – an HMAC of the Moodle user id with a secret generated at installation – together with their display name and interface language. The conversation itself lives in the brain. *Reset chat history* *(Chat-Verlauf zurücksetzen)* erases the learner's own conversation in the brain and the local figures. A spoken question is sent to Tarsus as audio to be transcribed.
- **Moodle's privacy tools** know the plugin: a data request (*Site administration › Users › Privacy and policies › Data requests* – *Datenschutz und Richtlinien › Datenanfragen*) exports a learner's figures and requests, and deleting a learner's data also erases their conversation in the brain.
- Interactive pages from the brain run sandboxed (see above) and are never stored in Moodle beyond the conversation history the brain returns.
- The coach does not search the internet: it generates explanations and answers exclusively from the documents your organisation provides. Tarsus stores your content at Microsoft Azure in Germany, GDPR-compliant – and the entire stack can be deployed on-premise on request ([Getting access](#getting-access)).

## Development

```
version.php                plugin version, release and the Moodle range (4.1 to 5.3)
lib.php                    Moodle callbacks: features, gradebook, completion, reset, navigation
mod_form.php               the activity's settings form
view.php                   the coach
materials.php              the course material: index and include
admin.php                  Plan & credits, Brains, Deletions and Status
settings.php               the Tarsus settings page
ajax.php, export.php       the coach's requests, the CSV export
classes/
  local/Activity/          settings, didactics, conversation state, scoring
  local/Client/            HTTP client, demo (mock) client, client factory
  local/Cron/, task/       the synchronisation task
  local/Guardrail/         kill switch, breaker, budget, rate limit
  local/Index/, Material/  material discovery and indexing state machine
  local/Output/            views, shaper of brain responses, media checks, icons, Markdown
  local/Page/, Service/    the coach page, learner, teacher and settings endpoints
  local/Support/           configuration, package (Plan), budget, help links, Moodle glue
  completion/, privacy/, form/, event/   Moodle APIs
backup/moodle2/            backup and restore
db/                        tables, upgrade steps, capabilities, the scheduled task
assets/                    CSS, JavaScript, images and the demo mode's pages
  js/vendor/, vendor/katex/  the bundled Markdown renderer and KaTeX's stylesheet and fonts
build/markdown/            the renderer's sources: React-Markdown's pipeline with KaTeX (npm ci && npm run build)
lang/                      en, de
docs/screenshots/          the pictures in this README (not in the release zip)
```

- **Demo mode** (default after installation) makes the whole interface usable without a brain: the demo client answers with fixed samples for every call, including interactive pages, a demo live page and sources. All demo brain names start with `mock-`.
- Version 1.0.0 was tested end to end with Playwright on Moodle 4.1 (PHP 8.1), 4.5 (PHP 8.2), 5.0 (PHP 8.2), 5.1, 5.2 and 5.3 (PHP 8.4), all on PostgreSQL, in demo mode: the activity created through its form with a completion rule, the four modes, sources, the teacher panels, formulas and tables, the administration pages, axe on every screen, a learner's grade and completion, the scheduled task, the privacy export and deletion, course reset, *Duplicate* and backup and restore – with every PHP message from the plugin's files recorded, deprecation notices included: none.
- The installation from the release zip through *Install plugins* was recorded on Moodle 4.5 (the pictures above), the update to a newer version through the same page on Moodle 5.0, and the zip published on GitHub was installed the same way on Moodle 5.3. On Moodle 4.5 the plugin then ran against a live Tarsus brain, reached through Moodle's web proxy: leaving demo mode sent the course material to the brain by itself; questions with sources, a test question with feedback, learning objectives and a learning unit, the live interaction, an AI-driven page and the teacher panels worked as shown above; *Reset chat history* erased the conversations in the brain, and removing the material took it out of the brain again.
- Version 1.0.1 ran the same checks on all six Moodle versions again, plus a test with the plugin's scripts slowed down as on a slow line, also on all six: Moodle's message drawer starts and opens on the coach and the *Materials* page, and the coach starts.
- The renderer is React-Markdown's own pipeline (remark-gfm, remark-math, rehype-katex) bundled with esbuild; compared with React-Markdown 10 it gives identical output for 36 sample texts (formulas, tables, lists, links, HTML, footnotes).
- **Releases** are published by the [Release workflow](.github/workflows/release.yml): raise `$plugin->version` and `$plugin->release` in `version.php`, add the version's section to `CHANGELOG.md` and push to `main` – the workflow tags the commit, builds `Tarsus-moodle-plugin-<version>.zip` (folder `tarsus`) with `git archive` (repository-only files such as `docs/` are left out) and creates the GitHub release with the changelog section as notes.

Issues and pull requests are welcome.

## Licence

GPL-3.0-or-later – see [LICENSE](LICENSE). © 2026 Tarsus GmbH, [tarsus.ai](https://tarsus.ai).

<p align="center"><sub>Our goal is to make high-quality education as accessible as Netflix.</sub></p>
