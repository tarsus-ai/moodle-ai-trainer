<?php

namespace mod_tarsuscoach\local\client;

/**
 * Stable Tarsus client contract.
 *
 * Everything outside the client layer depends on this interface, never on
 * a concrete client. A deterministic mock backs it in mock mode; the HTTP
 * client speaks to the live brain.
 *
 * Nothing here estimates what a call costs: Tarsus records that in the
 * brain's usage ({@see get_usage()}), the only figure the plugin uses.
 *
 * @author  Tarsus GmbH
 * @license GPL-3.0-or-later
 */
interface client_interface
{
    /** Maximum resource ids accepted by one batch status check. */
    public const BATCH_LIMIT = 10;

    /** Question widget types the practice mode supports. */
    public const QUESTION_WIDGET_TYPES = [
        'singleChoiceQuestionWidget',
        'trueFalseQuestionWidget',
        'openQuestionWidget',
        'crossMultiplicationQuestionWidget',
        'tradeCostingQuestionWidget',
    ];

    /**
     * Track (index) a resource into the brain.
     *
     * @param array<string, mixed> $resource Resource payload (type, resource_id, context_id, title, text|url|file).
     * @return array<string, mixed> Result with at least 'status' and optional 'bitref'.
     */
    public function track_resource(array $resource): array;

    /**
     * Untrack (remove) one or more resources from the brain.
     *
     * @param string[] $resourceids
     * @return array<string, string> Per-resource removal result.
     */
    public function untrack_resource(array $resourceids): array;

    /**
     * Untrack every resource filed under the given assets (one asset is one
     * ILIAS course or group).
     *
     * @param string[] $assetids
     * @return array<string, string>
     */
    public function untrack_assets(array $assetids): array;

    /**
     * Brain-side status of one tracked resource.
     *
     * @return array<string, mixed> Result with at least 'status'.
     */
    public function get_resource_status(string $resourceid): array;

    /**
     * Brain-side status of many resources, chunked by BATCH_LIMIT.
     *
     * @param string[] $resourceids
     * @return array<string, array{status: string, tracked: bool, brainstatus: string}>
     */
    public function check_tracked_resources(array $resourceids): array;

    /**
     * Every brain the configured API key can reach.
     *
     * @return array<int, array{brainid: string, active: bool, language: string, tag: string, timecreated: int}>
     */
    public function list_brains(): array;

    /**
     * The voices the brain can read aloud with.
     *
     * @return array<int, array{alias: string, country: string, gender: string}>
     */
    public function list_voices(): array;

    /**
     * What the brain charged between two Unix timestamps, as the brain
     * reports it (credits may be fractions).
     *
     * @return array{total: float, items: array<int, array{key: string, name: string, service: string, credits: float}>}
     */
    public function get_usage(int $start, int $end): array;

    /**
     * Ask the activity chat and return the assistant's answer. A spoken
     * question travels as 'audiofile' (a local path), 'audiomimetype' and
     * 'audioname' instead of 'query'.
     *
     * @param array<string, mixed> $params conversation parameters incl. 'query', 'replytostack', 'replytowidget'.
     * @return array{answer: string, html: string, question: string, widgetid: string, sources: bool, messages: array<int, array{title: string, text: string}>, progress: array<string, float>}
     */
    public function generate_widgets(array $params): array;

    /**
     * Start a live interaction (state discuss) and return its page.
     *
     * @param array<string, mixed> $params conversation parameters plus optional 'voice'.
     * @return array{page: string, progress: array<string, float>}
     */
    public function start_discuss(array $params): array;

    /**
     * The source bits of one answer, in the order of their material.
     *
     * @param array<string, mixed> $params contextid, userid, state, widgetid
     * @return array<int, array<string, mixed>>
     */
    public function get_references(array $params): array;

    /**
     * Every given learner's figures in one context.
     *
     * @param string[] $userids Pseudonymised user ids.
     * @return array<string, array<string, float>> User id => figures.
     */
    public function get_context_statistics(string $contextid, array $userids): array;

    /**
     * How one learner's experience points and learning units grew.
     *
     * @return array{xp: array<int, array{x: int, y: float}>, units: array<int, array{x: int, y: float}>}
     */
    public function get_user_analytics(string $contextid, string $userid, int $start, int $end): array;

    /**
     * Generate the next practice question widgets (Test knowledge).
     *
     * @param array<string, mixed> $params
     * @return array{widgets: array, progress: array}
     */
    public function generate_practice_widget(array $params): array;

    /**
     * Generate the next learning unit (Build knowledge). 'replay' is true
     * when Tarsus sent a widget again (HTTP 409) instead of a new one.
     *
     * @param array<string, mixed> $params
     * @return array{items: array, progress: array, replay: bool}
     */
    public function generate_learn_widget(array $params): array;

    /**
     * Evaluate a learner's answer to a question widget.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function evaluate_practice_answer(array $params): array;

    /**
     * Read one page of the interleaved activity feed back from the brain.
     *
     * @param array<string, mixed> $params
     * @return array{items: array, rawcount: int, progress: array}
     */
    public function retrieve_feed(array $params): array;

    /**
     * Erase one user's conversation in one activity context.
     */
    public function reset_conversation(string $contextid, string $userid): void;

    /**
     * Have the brain read one learning unit (stack) aloud.
     *
     * @param array<string, mixed> $params
     * @return array{audio: string, mimetype: string, voice: string, text: string}
     */
    public function generate_stack_audio(array $params): array;
}
