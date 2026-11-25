<?php

namespace mod_tarsuscoach\local\service;

use mod_tarsuscoach\local\activity\conversation;
use mod_tarsuscoach\local\activity\didactics;
use mod_tarsuscoach\local\activity\scope;
use mod_tarsuscoach\local\activity\scoring;
use mod_tarsuscoach\local\activity\settings;
use mod_tarsuscoach\local\client\client_factory;
use mod_tarsuscoach\local\client\client_interface;
use mod_tarsuscoach\local\client\configuration_exception;
use mod_tarsuscoach\local\client\transport_exception;
use mod_tarsuscoach\local\guardrail\blocked_exception;
use mod_tarsuscoach\local\guardrail\pipeline;
use mod_tarsuscoach\local\material\resource_id;
use mod_tarsuscoach\local\output\markdown;
use mod_tarsuscoach\local\output\media;
use mod_tarsuscoach\local\output\shaper;
use mod_tarsuscoach\local\support\config;
use mod_tarsuscoach\local\support\platform;
use mod_tarsuscoach\local\support\plan;
use mod_tarsuscoach\local\support\pseudonymizer;
use mod_tarsuscoach\local\support\usage_meter;
use mod_tarsuscoach\local\support\voices;

/**
 * The learner's side of a Tarsus object: the interleaved feed and the four
 * modes (Ask a question - typed or spoken -, Test knowledge, Build
 * knowledge, Live interaction), the sources of an answer and the audio
 * explainer.
 *
 * Every call checks the package and the mode switch, then the scope - an
 * empty scope never reaches the brain - then goes through the guardrails
 * pipeline and records the brain's figures for the learning progress. What
 * a call costs is left to Tarsus (the brain's usage); the local ledger only
 * counts requests. Results are plain arrays with a 'status' (success,
 * noscope, disabled, blocked, error) and a user-facing 'error'.
 *
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
final class learner_api
{
    /** Widgets one feed call reads back (the page size Tarsus recommends). */
    public const FEED_PAGE_SIZE = 10;

    /** Longest question accepted, in characters. */
    public const MAX_QUERY = 4000;

    /** Characters an id from Tarsus (widget, stack) consists of. */
    private const ID_CHARACTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-';

    private int $objid;
    private string $title;
    private string $description;
    private settings $settings;
    private int $containerref;
    private client_interface $client;
    private pipeline $pipeline;
    private usage_meter $usage;

    public function __construct(int $objid, string $title, string $description, settings $settings, int $containerref)
    {
        $this->objid = $objid;
        $this->title = $title;
        $this->description = $description;
        $this->settings = $settings;
        $this->containerref = $containerref;
        $this->client = client_factory::get_client();
        $this->pipeline = new pipeline();
        $this->usage = new usage_meter();
    }

    private function user_id(): int
    {
        return platform::user_id();
    }

    /**
     * @return string[]
     */
    private function scope(): array
    {
        return scope::allowed_resource_ids($this->objid, $this->containerref);
    }

    /**
     * @param string[] $allowed
     * @return array<string, mixed>
     */
    private function params(array $allowed): array
    {
        return conversation::params($this->objid, $this->title, $this->description, $this->settings, $allowed);
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private static function result(string $status, string $error = '', array $extra = []): array
    {
        return $extra + ['status' => $status, 'error' => $error];
    }

    /**
     * Whether a value is an id as Tarsus issues them (widget, stack).
     */
    public static function is_id(string $value): bool
    {
        $length = strlen($value);
        return $length > 0 && $length <= 100 && strspn($value, self::ID_CHARACTERS) === $length;
    }

    /**
     * Run a guarded brain call and map its failures to results.
     *
     * @param callable(): array<string, mixed> $call
     * @param bool $charged Whether the call may cost credits (refused once the month's limit is reached).
     * @param callable(array<string, mixed>): array<string, mixed> $then
     * @param string $context For the message and the log.
     * @return array<string, mixed>
     */
    private function guarded(callable $call, bool $charged, callable $then, string $context): array
    {
        try {
            $response = $this->pipeline->execute($call, $charged);
        } catch (blocked_exception $e) {
            return self::result('blocked', platform::txt('guardrail_blocked_' . $e->reason));
        } catch (transport_exception $e) {
            return self::result('error', messages::for_transport($e, $context));
        } catch (configuration_exception $e) {
            return self::result('error', platform::txt($e->langKey));
        }
        return $then(is_array($response) ? $response : []);
    }

    // ------------------------------------------------------------------
    // Feed
    // ------------------------------------------------------------------

    /**
     * One page of the activity feed and the experience figures. The newest
     * page comes first; older pages follow at multiples of the page size
     * as the learner scrolls up.
     *
     * @return array<string, mixed>
     */
    public function feed(int $offset): array
    {
        $allowed = $this->scope();
        if ($allowed === []) {
            return self::result('noscope', platform::txt('chat_noscope'), ['items' => [], 'hasmore' => false]);
        }
        $params = $this->params($allowed) + ['offset' => max(0, $offset), 'pagesize' => self::FEED_PAGE_SIZE];

        return $this->guarded(
            fn() => $this->client->retrieve_feed($params),
            false,
            function (array $response): array {
                scoring::record($this->objid, $this->user_id(), (array) ($response['progress'] ?? []), false);
                return self::result('success', '', self::feed_page($response) + [
                    'progress' => shaper::progress((array) ($response['progress'] ?? [])),
                ]);
            },
            'feed'
        );
    }

    /**
     * The shaped items of one feed page and whether an older page may follow.
     *
     * @param array<string, mixed> $response
     * @return array{items: array<int, array<string, mixed>>, hasmore: bool}
     */
    public static function feed_page(array $response): array
    {
        $items = [];
        foreach ((array) ($response['items'] ?? []) as $item) {
            if (is_array($item) && ($shaped = shaper::feed_item($item)) !== null) {
                $items[] = $shaped;
            }
        }
        // A full page means an older one may follow.
        return ['items' => $items, 'hasmore' => (int) ($response['rawcount'] ?? 0) >= self::FEED_PAGE_SIZE];
    }

    // ------------------------------------------------------------------
    // Ask a question
    // ------------------------------------------------------------------

    /**
     * Answer a typed question. $replywidget / $replystack anchor it on a
     * card or answer, or on a whole learning unit ("ask about this").
     *
     * @return array<string, mixed>
     */
    public function ask(string $query, string $replywidget = '', string $replystack = ''): array
    {
        $query = trim($query);
        if ($query === '') {
            return self::result('error', platform::txt('empty_query'));
        }
        if (mb_strlen($query) > self::MAX_QUERY) {
            $query = mb_substr($query, 0, self::MAX_QUERY);
        }
        return $this->answer(['query' => $query], $replywidget, $replystack, 'ask');
    }

    /**
     * Answer a spoken question: the recording goes to Tarsus, which turns
     * it into text and answers it. The text comes back as the question.
     *
     * @return array<string, mixed>
     */
    public function ask_voice(string $file, string $mimetype, string $filename, string $replywidget = '', string $replystack = ''): array
    {
        if (!$this->settings->voice_input_active()) {
            return self::result('disabled', platform::txt('voice_unavailable'));
        }
        if ($file === '' || !is_file($file) || filesize($file) === 0) {
            return self::result('error', platform::txt('err_audio_format'));
        }
        return $this->answer([
            'audiofile' => $file,
            'audiomimetype' => $mimetype !== '' ? $mimetype : 'application/octet-stream',
            'audioname' => $filename !== '' ? $filename : 'question.webm',
        ], $replywidget, $replystack, 'voice');
    }

    /**
     * @param array<string, mixed> $question query, or the recording
     * @return array<string, mixed>
     */
    private function answer(array $question, string $replywidget, string $replystack, string $context): array
    {
        if (!$this->settings->askenabled || !plan::allows_mode('ask')) {
            return self::result('disabled', platform::txt('chat_modedisabled'));
        }
        // The answer may only be grounded in what the teacher included -
        // and of that, only what the brain holds right now.
        $allowed = $this->scope();
        if ($allowed === []) {
            return self::result('noscope', platform::txt('chat_noscope'));
        }

        $params = $this->params($allowed) + $question;
        $replystack = trim($replystack);
        $replywidget = trim($replywidget);
        if ($replystack !== '' && self::is_id($replystack)) {
            $params['replytostack'] = $replystack;
        } elseif ($replywidget !== '' && self::is_id($replywidget)) {
            $params['replytowidget'] = $replywidget;
        }

        return $this->guarded(
            fn() => $this->client->generate_widgets($params),
            true,
            function (array $response) use ($context): array {
                $this->usage->record($this->containerref, $this->objid, $this->user_id(), $context === 'voice' ? 'chat_voice' : 'chat_ask');
                scoring::touch($this->objid, $this->user_id());
                $messages = [];
                foreach ((array) ($response['messages'] ?? []) as $message) {
                    if (is_array($message) && trim((string) ($message['text'] ?? '')) !== '') {
                        $messages[] = [
                            'title' => (string) ($message['title'] ?? ''),
                            'text' => markdown::render((string) $message['text']),
                        ];
                    }
                }
                $widgetid = (string) ($response['widgetid'] ?? '');
                return self::result('success', '', [
                    'answer' => markdown::render((string) ($response['answer'] ?? '')),
                    // An interactive page (ai-driven interaction), shown in
                    // a sandboxed frame.
                    'html' => (string) ($response['html'] ?? ''),
                    // The question as Tarsus understood it: for a spoken
                    // question the text it made of the recording.
                    'question' => (string) ($response['question'] ?? ''),
                    'widgetid' => $widgetid,
                    'sources' => $this->settings->referencesenabled && !empty($response['sources']) && $widgetid !== '',
                    'messages' => $messages,
                ]);
            },
            $context
        );
    }

    // ------------------------------------------------------------------
    // Sources of an answer
    // ------------------------------------------------------------------

    /**
     * The sources of one answer: every bit Tarsus took it from, whatever
     * its type, with a link to the material in ILIAS where the learner may
     * open it.
     *
     * @return array<string, mixed>
     */
    public function references(string $widgetid, string $state): array
    {
        $widgetid = trim($widgetid);
        if (!self::is_id($widgetid)) {
            return self::result('error', platform::txt('invalid_request'));
        }
        if (!$this->settings->referencesenabled) {
            return self::result('disabled', platform::txt('references_disabled'));
        }
        $state = in_array($state, ['ask', 'discuss', 'learn', 'practice'], true) ? $state : 'ask';
        $params = [
            'contextid' => scope::conversation_context_id($this->objid),
            'userid' => pseudonymizer::pseudonymize($this->user_id()),
            'state' => $state,
            'widgetid' => $widgetid,
        ];

        return $this->guarded(
            fn() => ['bits' => $this->client->get_references($params)],
            false,
            function (array $response): array {
                $bits = [];
                foreach ((array) ($response['bits'] ?? []) as $bit) {
                    if (is_array($bit)) {
                        $bits[] = $this->shape_reference($bit);
                    }
                }
                return self::result('success', '', ['bits' => $bits]);
            },
            'references'
        );
    }

    /**
     * @param array<string, mixed> $bit
     * @return array<string, mixed>
     */
    private function shape_reference(array $bit): array
    {
        $rows = [];
        foreach ((array) ($bit['rows'] ?? []) as $row) {
            $rows[] = array_values(array_map('strval', (array) $row));
        }
        $link = '';
        $parsed = resource_id::parse((string) ($bit['sourceid'] ?? ''));
        if ($parsed !== null && $parsed['refid'] > 0 && resource_id::is_local((string) ($bit['sourceid'] ?? ''))
            && platform::cm_visible_to_user($parsed['refid'], $parsed['containerref'])) {
            // Only material the learner may open is linked.
            $link = platform::cm_url($parsed['refid'], $parsed['type']);
        }
        return [
            'type' => (string) ($bit['type'] ?? 'text'),
            'title' => (string) ($bit['title'] ?? ''),
            'subtitle' => (string) ($bit['subtitle'] ?? ''),
            'text' => markdown::render((string) ($bit['text'] ?? '')),
            'rows' => $rows,
            'image' => media::image((string) ($bit['image'] ?? '')),
            'link' => $link,
        ];
    }

    // ------------------------------------------------------------------
    // Test knowledge
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function practice_next(): array
    {
        if (!$this->settings->testenabled || !plan::allows_mode('test')) {
            return self::result('disabled', platform::txt('chat_modedisabled'));
        }
        $allowed = $this->scope();
        if ($allowed === []) {
            return self::result('noscope', platform::txt('chat_noscope'));
        }
        $params = $this->params($allowed);

        return $this->guarded(
            fn() => $this->client->generate_practice_widget($params),
            true,
            function (array $response): array {
                $widgets = array_values(array_filter(
                    (array) ($response['widgets'] ?? []),
                    static fn($widget): bool => is_array($widget) && (string) ($widget['id'] ?? '') !== ''
                ));
                if ($widgets === []) {
                    return self::result('error', platform::txt('practice_nowidget'));
                }
                scoring::record($this->objid, $this->user_id(), (array) ($response['progress'] ?? []));
                $this->usage->record($this->containerref, $this->objid, $this->user_id(), 'practice_next');
                return self::result('success', '', [
                    'widgets' => array_map([shaper::class, 'widget'], $widgets),
                    'progress' => shaper::progress((array) ($response['progress'] ?? [])),
                ]);
            },
            'practice'
        );
    }

    /**
     * Evaluate one answer. The payload carries widgetid, widgettype, state
     * ('practice' or 'learn'), and the answer: 'answer' (single-answer
     * types), 'answers' (rule of three) or 'table' + 'columns' (a task
     * table: rows of cell values under the columns the question came with).
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function practice_check(array $payload): array
    {
        $widgetid = trim((string) ($payload['widgetid'] ?? ''));
        $widgettype = (string) ($payload['widgettype'] ?? '');
        $state = (string) ($payload['state'] ?? 'practice');
        if (!in_array($state, ['practice', 'learn'], true)) {
            $state = 'practice';
        }
        if (!self::is_id($widgetid) || !shaper::is_supported_type($widgettype)) {
            return self::result('error', platform::txt('invalid_request'));
        }

        $answer = trim((string) ($payload['answer'] ?? ''));
        $useranswers = [];
        $usertable = [];
        $columns = array_values(array_map(static fn($c): string => trim((string) $c), (array) ($payload['columns'] ?? [])));
        if ($widgettype === didactics::TYPE_CROSSMULTIPLICATION) {
            foreach ((array) ($payload['answers'] ?? []) as $entry) {
                $variable = trim((string) ($entry['variable'] ?? ''));
                $value = trim((string) ($entry['answer'] ?? ''));
                if ($variable === '' || $value === '') {
                    return self::result('error', platform::txt('empty_answer'));
                }
                $useranswers[] = ['variable' => $variable, 'answer' => $value];
            }
            if ($useranswers === []) {
                return self::result('error', platform::txt('empty_answer'));
            }
        } elseif ($widgettype === didactics::TYPE_TRADECOSTING) {
            // The whole table travels; no asked cell may stay unanswered.
            if ($columns === []) {
                return self::result('error', platform::txt('invalid_request'));
            }
            foreach ((array) ($payload['table'] ?? []) as $row) {
                $cells = array_values(array_map(static fn($cell): string => trim((string) $cell), (array) $row));
                if (count($cells) !== count($columns) || in_array('?', $cells, true)) {
                    return self::result('error', platform::txt('empty_answer'));
                }
                $usertable[] = $cells;
            }
            if ($usertable === []) {
                return self::result('error', platform::txt('empty_answer'));
            }
        } elseif ($answer === '') {
            return self::result('error', platform::txt('empty_answer'));
        }

        // A learning unit's check question belongs to Build knowledge.
        $mode = $state === 'learn' ? 'build' : 'test';
        if (!$this->settings->mode_enabled($mode) || !plan::allows_mode($mode)) {
            return self::result('disabled', platform::txt('chat_modedisabled'));
        }
        $allowed = $this->scope();
        if ($allowed === []) {
            return self::result('noscope', platform::txt('chat_noscope'));
        }

        $params = $this->params($allowed) + [
            'widgetid' => $widgetid,
            'widgettype' => $widgettype,
            'useranswer' => mb_substr($answer, 0, self::MAX_QUERY),
            'useranswers' => $useranswers,
            'usertable' => $usertable,
            'columns' => $columns,
            'state' => $state,
        ];

        return $this->guarded(
            fn() => $this->client->evaluate_practice_answer($params),
            true,
            function (array $response) use ($widgettype, $state): array {
                scoring::record($this->objid, $this->user_id(), (array) ($response['progress'] ?? []));
                $this->usage->record($this->containerref, $this->objid, $this->user_id(), $state === 'learn' ? 'learn_check' : 'practice_eval');

                $choices = [];
                foreach ((array) ($response['choices'] ?? []) as $choice) {
                    if (!is_array($choice)) {
                        continue;
                    }
                    $choices[] = [
                        'value' => (string) ($choice['text'] ?? ''),
                        'label' => shaper::choice((string) ($choice['text'] ?? ''), $widgettype),
                        'iscorrect' => !empty($choice['iscorrect']),
                        'clarification' => markdown::render((string) ($choice['clarification'] ?? '')),
                    ];
                }

                return self::result('success', '', [
                    'hasverdict' => ($response['correct'] ?? null) !== null,
                    'correct' => (bool) ($response['correct'] ?? false),
                    'score' => ($response['score'] ?? null) === null ? -1 : (float) $response['score'],
                    'feedback' => markdown::render((string) ($response['feedback'] ?? '')),
                    'answer' => markdown::render(shaper::choice((string) ($response['answer'] ?? ''), $widgettype)),
                    'choices' => $choices,
                    'answers' => shaper::answers((array) ($response['answers'] ?? [])),
                    'usertable' => shaper::values((array) ($response['usertable'] ?? [])),
                    'answertable' => shaper::verdicts((array) ($response['answertable'] ?? [])),
                    'progress' => shaper::progress((array) ($response['progress'] ?? [])),
                ]);
            },
            'check'
        );
    }

    // ------------------------------------------------------------------
    // Build knowledge
    // ------------------------------------------------------------------

    /**
     * The next learning step. When Tarsus sends a widget again instead
     * (HTTP 409: the path is finished, or a learn question still waits
     * for its answer), that widget is shown; nothing is locked here.
     *
     * @return array<string, mixed>
     */
    public function learn_next(): array
    {
        if (!$this->settings->buildenabled || !plan::allows_mode('build')) {
            return self::result('disabled', platform::txt('chat_modedisabled'), ['items' => []]);
        }
        $allowed = $this->scope();
        if ($allowed === []) {
            return self::result('noscope', platform::txt('chat_noscope'), ['items' => []]);
        }
        $params = $this->params($allowed);

        return $this->guarded(
            fn() => $this->client->generate_learn_widget($params),
            true,
            function (array $response): array {
                $items = array_values(array_filter((array) ($response['items'] ?? []), 'is_array'));
                if ($items === []) {
                    return self::result('error', platform::txt('learn_nowidget'), ['items' => []]);
                }
                $replay = !empty($response['replay']);
                if (!$replay) {
                    scoring::record($this->objid, $this->user_id(), (array) ($response['progress'] ?? []));
                    $this->usage->record($this->containerref, $this->objid, $this->user_id(), 'learn_next');
                }
                return self::result('success', '', [
                    'items' => array_map([shaper::class, 'learn_item'], $items),
                    'progress' => $replay ? null : shaper::progress((array) ($response['progress'] ?? [])),
                    'replay' => $replay,
                ]);
            },
            'learn'
        );
    }

    /**
     * Have the brain read a learning unit aloud.
     *
     * @return array<string, mixed>
     */
    public function stack_audio(string $stackid): array
    {
        $stackid = trim($stackid);
        if (!self::is_id($stackid)) {
            return self::result('error', platform::txt('invalid_request'));
        }
        if (!$this->settings->buildenabled || !plan::allows_mode('build')) {
            return self::result('disabled', platform::txt('chat_modedisabled'));
        }
        if (!plan::allows_audio()) {
            // The audio explainer is part of Build Knowledge and Pay as you grow.
            return self::result('disabled', platform::txt('plan_feature_unavailable'));
        }
        $allowed = $this->scope();
        if ($allowed === []) {
            return self::result('noscope', platform::txt('chat_noscope'));
        }
        $params = $this->params($allowed) + ['stackid' => $stackid];
        $params['voice'] = voices::choose((string) $params['userlanguage']);

        return $this->guarded(
            fn() => $this->client->generate_stack_audio($params),
            true,
            function (array $response): array {
                $audio = media::audio((string) ($response['audio'] ?? ''), (string) ($response['mimetype'] ?? ''));
                if ($audio === '') {
                    return self::result('error', platform::txt('learn_audio_unavailable'));
                }
                $this->usage->record($this->containerref, $this->objid, $this->user_id(), 'learn_audio');
                return self::result('success', '', [
                    'audio' => $audio,
                    'voice' => (string) ($response['voice'] ?? ''),
                    'transcript' => (string) ($response['text'] ?? ''),
                ]);
            },
            'audio'
        );
    }

    // ------------------------------------------------------------------
    // Live interaction
    // ------------------------------------------------------------------

    /**
     * Start a live interaction: Tarsus returns the page of the call, which
     * the browser shows in a frame that may use the microphone. The call
     * talks to Tarsus directly; what the coach answers is saved by Tarsus
     * and shows in the feed afterwards.
     *
     * @return array<string, mixed>
     */
    public function discuss(): array
    {
        if (!$this->settings->discussenabled || !plan::allows_mode('discuss')) {
            return self::result('disabled', platform::txt('chat_modedisabled'));
        }
        $allowed = $this->scope();
        if ($allowed === []) {
            return self::result('noscope', platform::txt('chat_noscope'));
        }
        $params = $this->params($allowed);
        $params['voice'] = voices::choose((string) $params['userlanguage']);
        $logo = config::robot_avatar();
        if (strncasecmp($logo, 'https://', 8) === 0 || strncasecmp($logo, 'data:image/', 11) === 0) {
            $params['robotlogo'] = $logo;
        }

        return $this->guarded(
            fn() => $this->client->start_discuss($params),
            true,
            function (array $response): array {
                $page = (string) ($response['page'] ?? '');
                if ($page === '') {
                    return self::result('error', platform::txt('err_live_unavailable'));
                }
                $this->usage->record($this->containerref, $this->objid, $this->user_id(), 'discuss_start');
                scoring::touch($this->objid, $this->user_id());
                return self::result('success', '', ['page' => $page]);
            },
            'discuss'
        );
    }

    // ------------------------------------------------------------------
    // Reset
    // ------------------------------------------------------------------

    /**
     * Erase the current user's conversation: the brain forgets transcript
     * and dialogue memory, and the local figures go too.
     *
     * @return array<string, mixed>
     */
    public function reset(): array
    {
        $contextid = scope::conversation_context_id($this->objid);
        $userid = pseudonymizer::pseudonymize($this->user_id());

        try {
            $this->pipeline->execute(function () use ($contextid, $userid): array {
                $this->client->reset_conversation($contextid, $userid);
                return [];
            });
        } catch (blocked_exception $e) {
            return self::result('blocked', platform::txt('guardrail_blocked_' . $e->reason));
        } catch (transport_exception $e) {
            // Nothing stored for this learner yet: there is nothing to erase.
            if (!$e->says('CONTEXT DOES NOT EXIST')) {
                return self::result('error', messages::for_transport($e, 'reset'));
            }
        } catch (configuration_exception $e) {
            return self::result('error', platform::txt($e->langKey));
        }
        scoring::clear($this->objid, $this->user_id());
        return self::result('success');
    }
}
