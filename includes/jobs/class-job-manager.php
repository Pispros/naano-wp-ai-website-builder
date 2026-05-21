<?php
/**
 * Job Manager – stores async generation jobs in WP transients.
 *
 * Pure repository pattern: knows nothing about LLMs or the loopback
 * dispatch. Provides:
 *   - create / get / mark_done / mark_error / delete   (basic CRUD)
 *   - update_status                                    (pending → running)
 *   - get_state / set_state                            (multi-step cursor & intermediate data)
 *   - log                                              (debug trail returned to client via poll)
 *
 * Each job carries a one-time security `token` used by the loopback
 * worker (handle_naano_run_job) to authenticate, since the loopback
 * request runs without the user's cookies.
 *
 * @package NaanoAIWebsiteBuilder
 */

if (!defined("ABSPATH")) {
    exit();
}

class Naano_Job_Manager
{
    private const TRANSIENT_PREFIX = "naano_job_";
    private const JOB_TTL = HOUR_IN_SECONDS;

    /**
     * Create a new job, return its ID. The job carries its own auth token.
     *
     * @param string $type    Job type.
     * @param array  $payload Input data for the runner.
     * @return string Job ID (uuid v4).
     */
    public static function create(string $type, array $payload): string
    {
        $job_id = wp_generate_uuid4();

        $job = [
            "id" => $job_id,
            "type" => $type,
            "status" => "pending",
            "user_id" => get_current_user_id(),
            "token" => wp_generate_password(48, false, false),
            "payload" => $payload,
            "state" => [], // cursor + intermediate data, used between loopback steps
            "log" => [],
            "started_at" => time(),
            "finished_at" => null,
        ];

        set_transient(self::TRANSIENT_PREFIX . $job_id, $job, self::JOB_TTL);

        return $job_id;
    }

    /**
     * Fetch a job by ID.
     */
    public static function get(string $job_id): ?array
    {
        $job = get_transient(self::TRANSIENT_PREFIX . $job_id);
        return is_array($job) ? $job : null;
    }

    /**
     * Update job status (pending → running, etc).
     */
    public static function update_status(string $job_id, string $status): void
    {
        $job = self::get($job_id);
        if (!$job) {
            return;
        }
        $job["status"] = $status;
        self::persist($job_id, $job);
    }

    /**
     * Read the multi-step cursor / intermediate state.
     */
    public static function get_state(string $job_id): array
    {
        $job = self::get($job_id);
        return is_array($job["state"] ?? null) ? $job["state"] : [];
    }

    /**
     * Replace the multi-step cursor / intermediate state.
     */
    public static function set_state(string $job_id, array $state): void
    {
        $job = self::get($job_id);
        if (!$job) {
            return;
        }
        $job["state"] = $state;
        self::persist($job_id, $job);
    }

    /**
     * Mark a job as completed and store the final result.
     */
    public static function mark_done(string $job_id, $data): void
    {
        $job = self::get($job_id);
        if (!$job) {
            return;
        }
        $job["status"] = "done";
        $job["data"] = $data;
        $job["finished_at"] = time();
        self::persist($job_id, $job);
    }

    /**
     * Mark a job as failed.
     */
    public static function mark_error(string $job_id, string $error): void
    {
        $job = self::get($job_id);
        if (!$job) {
            return;
        }
        $job["status"] = "error";
        $job["error"] = $error;
        $job["finished_at"] = time();
        self::persist($job_id, $job);
    }

    /**
     * Append a log entry. Mirrored to PHP's error log so the trail can
     * also be tailed via wp-content/debug.log when WP_DEBUG_LOG is on.
     * The log array is returned to the client by the poll endpoint so
     * the browser console can display it directly.
     *
     * @param string       $job_id
     * @param string|array $entry
     * @return void
     */
    public static function log(string $job_id, $entry): void
    {
        $job = self::get($job_id);
        if (!$job) {
            return;
        }
        if (!isset($job["log"]) || !is_array($job["log"])) {
            $job["log"] = [];
        }
        $job["log"][] = [
            "ts" => round(microtime(true), 3),
            "entry" => $entry,
        ];

        $stringified = is_string($entry) ? $entry : wp_json_encode($entry);
        // Only emit to PHP error log when WP_DEBUG / WP_DEBUG_LOG is on.
        // This keeps the production log clean while preserving the
        // diagnostic trail developers expect on staging.
        if (defined("WP_DEBUG") && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            @error_log(
                "[Naano Job " .
                    $job_id .
                    "] " .
                    substr((string) $stringified, 0, 2000),
            );
        }

        self::persist($job_id, $job);
    }

    /**
     * Delete a job (called once the client has retrieved a terminal status).
     */
    public static function delete(string $job_id): void
    {
        delete_transient(self::TRANSIENT_PREFIX . $job_id);
    }

    /**
     * Internal: write the job back to the transient store.
     */
    private static function persist(string $job_id, array $job): void
    {
        set_transient(self::TRANSIENT_PREFIX . $job_id, $job, self::JOB_TTL);
    }
}
