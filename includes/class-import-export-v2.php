<?php

if (!defined('ABSPATH')) {
    exit;
}

class MULOPIMFWC_Import_Export_V2_Service
{
    const DB_SCHEMA_VERSION = '1.1.7.5';
    const OPTION_DB_SCHEMA_VERSION = 'mulopimfwc_ie_v2_schema_version';
    const OPTION_FEATURE_FLAG = 'mulopimfwc_ie_v2_enabled';

    const PACKAGE_SCHEMA_VERSION = 2;
    const PACKAGE_FILENAME = 'mulopimfwc_ie_package_v2.zip';
    const ACTION_HOOK_PROCESS_JOB = 'mulopimfwc_ie_process_job';
    const ACTION_GROUP = 'mulopimfwc_ie';

    const STATUS_QUEUED = 'queued';
    const STATUS_RUNNING = 'running';
    const STATUS_UPLOADING = 'uploading';
    const STATUS_UPLOADED = 'uploaded';
    const STATUS_AWAITING_CONFIRMATION = 'awaiting_confirmation';
    const STATUS_PAUSED = 'paused';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_FAILED = 'failed';
    const STATUS_COMPLETED = 'completed';

    const TYPE_EXPORT = 'export';
    const TYPE_IMPORT = 'import';

    const PHASE_EXPORT = 'export';
    const PHASE_UPLOADING = 'uploading';
    const PHASE_UPLOAD_COMPLETE = 'upload_complete';
    const PHASE_DRY_RUN = 'dry_run';
    const PHASE_APPLY = 'apply';
    const PHASE_AWAITING_CONFIRMATION = 'awaiting_confirmation';

    const EXPORT_ACTIVE_LIMIT = 2;
    const IMPORT_ACTIVE_LIMIT = 1;
    const WORKER_BUDGET_SECONDS = 25;
    const MEMORY_GUARD_RATIO = 0.70;
    const CHECKPOINT_ROWS = 2000;
    const CHECKPOINT_SECONDS = 10;
    const EXPORT_BATCH_SIZE = 1000;
    const EXPORT_PART_MAX_ROWS = 250000;
    const EXPORT_PART_MAX_UNCOMPRESSED_BYTES = 134217728; // 128MB
    const UPLOAD_CHUNK_BYTES = 8388608; // 8MB
    const MAX_UPLOAD_BYTES = 2147483648; // 2GB
    const MAX_IMPORT_ROWS = 0; // 0 = unlimited
    const EVENT_LOG_CAP = 20000;
    const PASS_FILE_BUFFER_BYTES = 1048576; // 1MB per pass buffer flush

    const JOB_RETENTION_DAYS = 90;
    const ARTIFACT_RETENTION_DAYS = 14;
    const LOG_RETENTION_DAYS = 90;

    private $wpdb;
    private $legacy;
    private $tables = array();
    private $csv_line_stream = null;
    private $pass_file_buffers = array();
    private $event_seq_cache = array();

    public function __construct($legacy_service)
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->legacy = $legacy_service;
        $this->tables = array(
            'jobs' => $wpdb->prefix . 'mulopimfwc_ie_jobs',
            'events' => $wpdb->prefix . 'mulopimfwc_ie_job_events',
            'artifacts' => $wpdb->prefix . 'mulopimfwc_ie_job_artifacts',
            'uploads' => $wpdb->prefix . 'mulopimfwc_ie_uploads',
        );
    }

    public function register_hooks()
    {
        add_action(self::ACTION_HOOK_PROCESS_JOB, array($this, 'process_job_action'), 10, 1);
        add_action('init', array($this, 'maybe_upgrade_schema'));
        add_action('init', array($this, 'maybe_run_retention_cleanup'));
    }

    public static function install_schema()
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        $jobs = $wpdb->prefix . 'mulopimfwc_ie_jobs';
        $events = $wpdb->prefix . 'mulopimfwc_ie_job_events';
        $artifacts = $wpdb->prefix . 'mulopimfwc_ie_job_artifacts';
        $uploads = $wpdb->prefix . 'mulopimfwc_ie_uploads';

        dbDelta(
            "CREATE TABLE {$jobs} (
                job_id char(36) NOT NULL,
                type varchar(20) NOT NULL,
                phase varchar(32) NOT NULL,
                status varchar(32) NOT NULL,
                user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                options_json longtext NULL,
                summary_json longtext NULL,
                checkpoint_json longtext NULL,
                error_json longtext NULL,
                input_path text NULL,
                output_path text NULL,
                file_sha256 char(64) DEFAULT '',
                progress_percent decimal(5,2) NOT NULL DEFAULT 0,
                rows_total bigint(20) unsigned NOT NULL DEFAULT 0,
                rows_processed bigint(20) unsigned NOT NULL DEFAULT 0,
                rows_failed bigint(20) unsigned NOT NULL DEFAULT 0,
                last_event_seq bigint(20) unsigned NOT NULL DEFAULT 0,
                created_at_gmt datetime NOT NULL,
                started_at_gmt datetime NULL,
                updated_at_gmt datetime NOT NULL,
                finished_at_gmt datetime NULL,
                expires_at_gmt datetime NULL,
                PRIMARY KEY  (job_id),
                KEY type_status (type, status),
                KEY updated_at (updated_at_gmt),
                KEY expires_at (expires_at_gmt)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$events} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                job_id char(36) NOT NULL,
                seq bigint(20) unsigned NOT NULL DEFAULT 0,
                level varchar(16) NOT NULL DEFAULT 'info',
                code varchar(64) NOT NULL DEFAULT '',
                message text NOT NULL,
                context_json longtext NULL,
                created_at_gmt datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY job_id_id (job_id, id),
                KEY job_id_seq (job_id, seq)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$artifacts} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                job_id char(36) NOT NULL,
                kind varchar(32) NOT NULL,
                path text NOT NULL,
                sha256 char(64) DEFAULT '',
                size_bytes bigint(20) unsigned NOT NULL DEFAULT 0,
                created_at_gmt datetime NOT NULL,
                expires_at_gmt datetime NULL,
                PRIMARY KEY  (id),
                KEY job_id_kind (job_id, kind),
                KEY expires_at (expires_at_gmt)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$uploads} (
                upload_id char(36) NOT NULL,
                job_id char(36) NOT NULL,
                chunk_count int(10) unsigned NOT NULL DEFAULT 0,
                received_chunks_json longtext NULL,
                target_sha256 char(64) DEFAULT '',
                assembled_path text NULL,
                size_bytes bigint(20) unsigned NOT NULL DEFAULT 0,
                status varchar(32) NOT NULL DEFAULT 'uploading',
                created_at_gmt datetime NOT NULL,
                updated_at_gmt datetime NOT NULL,
                PRIMARY KEY  (upload_id),
                KEY job_id (job_id)
            ) {$charset_collate};"
        );

        update_option(self::OPTION_DB_SCHEMA_VERSION, self::DB_SCHEMA_VERSION, false);
    }

    public static function ensure_capabilities()
    {
        $caps = array(
            'mulopimfwc_export_products',
            'mulopimfwc_import_products',
            'mulopimfwc_restore_import_snapshot',
        );

        foreach (array('administrator', 'shop_manager') as $role_name) {
            $role = get_role($role_name);
            if (!$role) {
                continue;
            }
            foreach ($caps as $cap) {
                $role->add_cap($cap);
            }
        }
    }

    public function maybe_upgrade_schema()
    {
        if (get_option(self::OPTION_DB_SCHEMA_VERSION, '') !== self::DB_SCHEMA_VERSION) {
            self::install_schema();
        }
        self::ensure_capabilities();
        $this->ensure_storage_root();
    }

    public function is_v2_enabled()
    {
        $raw = get_option(self::OPTION_FEATURE_FLAG, 'yes');
        $enabled = ($raw === 'yes' || $raw === '1' || $raw === 1 || $raw === true);
        return (bool) apply_filters('mulopimfwc_ie_v2_enabled', $enabled);
    }

    public function maybe_run_retention_cleanup()
    {
        if (!is_admin()) {
            return;
        }
        $flag = get_transient('mulopimfwc_ie_v2_retention_ran');
        if ($flag) {
            return;
        }
        set_transient('mulopimfwc_ie_v2_retention_ran', 1, DAY_IN_SECONDS);

        $cutoff_jobs = gmdate('Y-m-d H:i:s', time() - (DAY_IN_SECONDS * $this->get_job_retention_days()));
        $cutoff_logs = gmdate('Y-m-d H:i:s', time() - (DAY_IN_SECONDS * $this->get_log_retention_days()));
        $now_gmt = $this->now_gmt();

        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->tables['events']} WHERE created_at_gmt < %s",
                $cutoff_logs
            )
        );

        $expired_artifacts = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, path FROM {$this->tables['artifacts']} WHERE expires_at_gmt IS NOT NULL AND expires_at_gmt < %s",
                $now_gmt
            ),
            ARRAY_A
        );
        if (is_array($expired_artifacts)) {
            foreach ($expired_artifacts as $artifact) {
                $path = isset($artifact['path']) ? (string) $artifact['path'] : '';
                if ($path !== '' && file_exists($path) && is_file($path)) {
                    @unlink($path);
                }
            }
            $this->wpdb->query(
                $this->wpdb->prepare(
                    "DELETE FROM {$this->tables['artifacts']} WHERE expires_at_gmt IS NOT NULL AND expires_at_gmt < %s",
                    $now_gmt
                )
            );
        }

        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->tables['jobs']} WHERE created_at_gmt < %s AND status IN (%s,%s,%s)",
                $cutoff_jobs,
                self::STATUS_COMPLETED,
                self::STATUS_FAILED,
                self::STATUS_CANCELLED
            )
        );
    }

    public function ajax_start_export()
    {
        $this->assert_ajax_permission('mulopimfwc_export_products');

        if (!$this->can_start_new_job(self::TYPE_EXPORT, self::EXPORT_ACTIVE_LIMIT)) {
            $payload = array(
                'message' => __('Export queue is full. Maximum two active export jobs are allowed.', 'multi-location-product-and-inventory-management-pro'),
            );
            $active_jobs = $this->get_active_jobs(self::TYPE_EXPORT, 2);
            if (!empty($active_jobs)) {
                $visible_jobs = array();
                foreach ($active_jobs as $active_job) {
                    if ($this->can_access_job($active_job)) {
                        $visible_jobs[] = $this->format_job_status($active_job);
                    }
                }
                if (!empty($visible_jobs)) {
                    $payload['active_jobs'] = $visible_jobs;
                }
            }
            wp_send_json_error($payload);
        }

        $user_id = get_current_user_id();
        $options = $this->get_request_options();
        $job = $this->create_job(self::TYPE_EXPORT, self::PHASE_EXPORT, self::STATUS_QUEUED, $user_id, $options);

        $checkpoint = array(
            'stage' => 'init',
            'rows_since_checkpoint' => 0,
            'last_checkpoint_ts' => time(),
            'exported_at' => gmdate('c'),
            'source_site' => home_url(),
            'last_product_id' => 0,
            'processed_products' => 0,
            'rows_written' => 0,
            'estimated_products' => $this->count_products_for_export(),
            'parts' => array(),
            'current_part' => null,
            'summary' => array(
                'products' => 0,
                'variations' => 0,
                'taxonomy_terms' => 0,
                'locations' => 0,
                'relationships' => 0,
                'location_inventory' => 0,
                'media_refs' => 0,
                'processed_products' => 0,
            ),
        );

        $this->update_job(
            $job['job_id'],
            array(
                'checkpoint_json' => $this->encode_json($checkpoint),
                'rows_total' => max(0, (int) $checkpoint['estimated_products']),
                'rows_processed' => 0,
                'rows_failed' => 0,
                'progress_percent' => 0,
            )
        );

        $this->append_event($job['job_id'], 'info', 'job_created', 'Export job created.', array(
            'phase' => self::PHASE_EXPORT,
        ));
        $this->schedule_worker($job['job_id']);

        $job = $this->get_job($job['job_id']);
        wp_send_json_success($this->format_job_status($job));
    }

    public function ajax_start_import()
    {
        $this->assert_ajax_permission('mulopimfwc_import_products');

        if (!$this->can_start_new_job(self::TYPE_IMPORT, self::IMPORT_ACTIVE_LIMIT)) {
            $payload = array(
                'message' => __('An import job is already active. Pause/cancel it before starting a new one.', 'multi-location-product-and-inventory-management-pro'),
            );
            $active_job = $this->get_latest_active_job(self::TYPE_IMPORT);
            if ($active_job && $this->can_access_job($active_job)) {
                $payload['active_job'] = $this->format_job_status($active_job);
            }
            wp_send_json_error($payload);
        }

        $user_id = get_current_user_id();
        $options = $this->get_request_options();
        $target_sha256 = isset($_POST['target_sha256']) ? sanitize_text_field(wp_unslash($_POST['target_sha256'])) : '';

        $job = $this->create_job(self::TYPE_IMPORT, self::PHASE_UPLOADING, self::STATUS_UPLOADING, $user_id, $options);
        $upload = $this->create_upload($job['job_id'], $target_sha256);

        $checkpoint = array(
            'stage' => 'uploading',
            'phase' => self::PHASE_UPLOADING,
            'upload_id' => $upload['upload_id'],
            'rows_since_checkpoint' => 0,
            'last_checkpoint_ts' => time(),
        );

        $this->update_job($job['job_id'], array(
            'checkpoint_json' => $this->encode_json($checkpoint),
            'progress_percent' => 0,
            'rows_total' => 0,
            'rows_processed' => 0,
            'rows_failed' => 0,
        ));

        $this->append_event($job['job_id'], 'info', 'job_created', 'Import job created. Waiting for upload chunks.');

        wp_send_json_success(array(
            'job_id' => $job['job_id'],
            'type' => self::TYPE_IMPORT,
            'phase' => self::PHASE_UPLOADING,
            'status' => self::STATUS_UPLOADING,
            'upload_id' => $upload['upload_id'],
            'chunk_size_bytes' => self::UPLOAD_CHUNK_BYTES,
            'max_upload_bytes' => self::MAX_UPLOAD_BYTES,
            'status_url' => $this->build_status_url($job['job_id']),
            'events_url' => $this->build_events_url($job['job_id']),
        ));
    }

    public function ajax_upload_chunk()
    {
        $this->assert_ajax_permission('mulopimfwc_import_products');

        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
        $upload_id = isset($_POST['upload_id']) ? sanitize_text_field(wp_unslash($_POST['upload_id'])) : '';
        $chunk_index = isset($_POST['chunk_index']) ? (int) $_POST['chunk_index'] : -1;
        $chunk_count = isset($_POST['chunk_count']) ? (int) $_POST['chunk_count'] : 0;
        $chunk_sha = isset($_POST['chunk_sha256']) ? sanitize_text_field(wp_unslash($_POST['chunk_sha256'])) : '';

        if ($job_id === '' || $upload_id === '' || $chunk_index < 0 || $chunk_count <= 0) {
            wp_send_json_error(array(
                'message' => __('Invalid upload chunk payload.', 'multi-location-product-and-inventory-management-pro'),
            ));
        }

        if ($chunk_count > 1000000) {
            wp_send_json_error(array(
                'message' => __('Chunk count is invalid.', 'multi-location-product-and-inventory-management-pro'),
            ));
        }

        $job = $this->get_job($job_id);
        if (!$job || $job['type'] !== self::TYPE_IMPORT) {
            wp_send_json_error(array('message' => __('Import job not found.', 'multi-location-product-and-inventory-management-pro')));
        }
        if (!$this->can_access_job($job)) {
            wp_send_json_error(array('message' => __('You do not have permission to access this job.', 'multi-location-product-and-inventory-management-pro')));
        }

        $upload = $this->get_upload($upload_id, $job_id);
        if (!$upload) {
            wp_send_json_error(array('message' => __('Upload session not found.', 'multi-location-product-and-inventory-management-pro')));
        }

        if (!isset($_FILES['chunk']) || !isset($_FILES['chunk']['tmp_name']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('Missing chunk file upload.', 'multi-location-product-and-inventory-management-pro')));
        }

        $chunk_tmp = (string) $_FILES['chunk']['tmp_name'];
        $chunk_size = filesize($chunk_tmp);
        if ($chunk_size === false || $chunk_size < 0) {
            wp_send_json_error(array('message' => __('Unable to read uploaded chunk size.', 'multi-location-product-and-inventory-management-pro')));
        }
        if ((int) $chunk_size > (self::UPLOAD_CHUNK_BYTES + 65536)) {
            wp_send_json_error(array(
                'message' => __('Chunk size exceeds the 8MB limit.', 'multi-location-product-and-inventory-management-pro'),
            ));
        }

        $computed_sha = hash_file('sha256', $chunk_tmp);
        if ($chunk_sha !== '' && !hash_equals(strtolower($chunk_sha), strtolower($computed_sha))) {
            wp_send_json_error(array(
                'message' => __('Chunk checksum mismatch.', 'multi-location-product-and-inventory-management-pro'),
            ));
        }

        $upload_dir = $this->get_upload_chunks_dir($job_id, $upload_id);
        $this->ensure_directory($upload_dir);
        $chunk_path = $upload_dir . DIRECTORY_SEPARATOR . sprintf('chunk-%06d.part', $chunk_index);
        if (!@move_uploaded_file($chunk_tmp, $chunk_path)) {
            if (!@copy($chunk_tmp, $chunk_path)) {
                wp_send_json_error(array('message' => __('Failed to persist uploaded chunk.', 'multi-location-product-and-inventory-management-pro')));
            }
        }

        $received = $this->decode_json($upload['received_chunks_json'], array());
        if (!is_array($received)) {
            $received = array();
        }
        $received[(string) $chunk_index] = array(
            'size' => (int) $chunk_size,
            'sha256' => strtolower((string) $computed_sha),
        );

        $this->update_upload($upload_id, array(
            'chunk_count' => max($chunk_count, (int) $upload['chunk_count']),
            'received_chunks_json' => $this->encode_json($received),
            'status' => 'uploading',
        ));

        $received_count = count($received);
        $this->append_event($job_id, 'info', 'chunk_received', sprintf('Chunk %d/%d received.', $chunk_index + 1, $chunk_count), array(
            'chunk_index' => $chunk_index,
            'chunk_count' => $chunk_count,
            'chunk_size' => (int) $chunk_size,
        ));

        wp_send_json_success(array(
            'job_id' => $job_id,
            'upload_id' => $upload_id,
            'chunk_index' => $chunk_index,
            'chunk_count' => $chunk_count,
            'received_count' => $received_count,
            'progress_percent' => $chunk_count > 0 ? round(($received_count / $chunk_count) * 100, 2) : 0,
            'chunk_sha256' => strtolower((string) $computed_sha),
        ));
    }

    public function ajax_finish_upload()
    {
        $this->assert_ajax_permission('mulopimfwc_import_products');

        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
        $upload_id = isset($_POST['upload_id']) ? sanitize_text_field(wp_unslash($_POST['upload_id'])) : '';
        $chunk_count = isset($_POST['chunk_count']) ? (int) $_POST['chunk_count'] : 0;
        $target_sha256 = isset($_POST['target_sha256']) ? sanitize_text_field(wp_unslash($_POST['target_sha256'])) : '';
        $original_filename = isset($_POST['filename']) ? sanitize_file_name(wp_unslash($_POST['filename'])) : 'upload.bin';

        if ($job_id === '' || $upload_id === '' || $chunk_count <= 0) {
            wp_send_json_error(array('message' => __('Invalid finish upload payload.', 'multi-location-product-and-inventory-management-pro')));
        }

        $job = $this->get_job($job_id);
        if (!$job || $job['type'] !== self::TYPE_IMPORT) {
            wp_send_json_error(array('message' => __('Import job not found.', 'multi-location-product-and-inventory-management-pro')));
        }
        if (!$this->can_access_job($job)) {
            wp_send_json_error(array('message' => __('You do not have permission to access this job.', 'multi-location-product-and-inventory-management-pro')));
        }

        $upload = $this->get_upload($upload_id, $job_id);
        if (!$upload) {
            wp_send_json_error(array('message' => __('Upload session not found.', 'multi-location-product-and-inventory-management-pro')));
        }

        $received = $this->decode_json($upload['received_chunks_json'], array());
        if (!is_array($received)) {
            $received = array();
        }
        for ($i = 0; $i < $chunk_count; $i++) {
            if (!isset($received[(string) $i])) {
                wp_send_json_error(array(
                    'message' => sprintf(
                        /* translators: %d: chunk index number */
                        __('Upload is incomplete. Missing chunk index: %d', 'multi-location-product-and-inventory-management-pro'),
                        $i
                    ),
                ));
            }
        }

        $job_dir = $this->get_job_dir($job_id);
        $input_dir = $job_dir . DIRECTORY_SEPARATOR . 'input';
        $this->ensure_directory($input_dir);
        $assembled_path = $input_dir . DIRECTORY_SEPARATOR . 'uploaded-source.bin';
        $upload_dir = $this->get_upload_chunks_dir($job_id, $upload_id);

        $output = fopen($assembled_path, 'wb');
        if (!$output) {
            wp_send_json_error(array('message' => __('Unable to create assembled upload file.', 'multi-location-product-and-inventory-management-pro')));
        }

        $bytes_written = 0;
        for ($i = 0; $i < $chunk_count; $i++) {
            $chunk_path = $upload_dir . DIRECTORY_SEPARATOR . sprintf('chunk-%06d.part', $i);
            if (!file_exists($chunk_path)) {
                fclose($output);
                wp_send_json_error(array('message' => __('Missing chunk file during assembly.', 'multi-location-product-and-inventory-management-pro')));
            }
            $input = fopen($chunk_path, 'rb');
            if (!$input) {
                fclose($output);
                wp_send_json_error(array('message' => __('Failed to open chunk for assembly.', 'multi-location-product-and-inventory-management-pro')));
            }
            $copied = stream_copy_to_stream($input, $output);
            fclose($input);
            if ($copied === false) {
                fclose($output);
                wp_send_json_error(array('message' => __('Failed while assembling upload.', 'multi-location-product-and-inventory-management-pro')));
            }
            $bytes_written += (int) $copied;
            if ($bytes_written > self::MAX_UPLOAD_BYTES) {
                fclose($output);
                wp_send_json_error(array(
                    'message' => __('Upload exceeds maximum supported size of 2GB.', 'multi-location-product-and-inventory-management-pro'),
                ));
            }
        }
        fclose($output);

        $assembled_sha = hash_file('sha256', $assembled_path);
        if ($target_sha256 !== '' && !hash_equals(strtolower($target_sha256), strtolower($assembled_sha))) {
            wp_send_json_error(array('message' => __('Final upload checksum mismatch.', 'multi-location-product-and-inventory-management-pro')));
        }

        $ext = strtolower((string) pathinfo($original_filename, PATHINFO_EXTENSION));
        $package_path = $assembled_path;
        if ($ext === 'csv') {
            $package_path = $this->build_package_from_single_csv($job_id, $assembled_path, $original_filename);
        } elseif ($ext !== 'zip') {
            wp_send_json_error(array(
                'message' => __('Unsupported import file format. Upload a v2 package ZIP or canonical CSV.', 'multi-location-product-and-inventory-management-pro'),
            ));
        }

        $this->update_upload($upload_id, array(
            'assembled_path' => $package_path,
            'size_bytes' => (int) filesize($package_path),
            'status' => 'assembled',
            'target_sha256' => $target_sha256 !== '' ? strtolower($target_sha256) : strtolower($assembled_sha),
        ));

        $checkpoint = $this->decode_json($job['checkpoint_json'], array());
        if (!is_array($checkpoint)) {
            $checkpoint = array();
        }
        $checkpoint['stage'] = 'upload_complete';
        $checkpoint['phase'] = self::PHASE_UPLOAD_COMPLETE;
        $checkpoint['upload_id'] = $upload_id;
        $checkpoint['package_path'] = $package_path;
        $checkpoint['rows_since_checkpoint'] = 0;
        $checkpoint['last_checkpoint_ts'] = time();

        $this->update_job($job_id, array(
            'phase' => self::PHASE_UPLOAD_COMPLETE,
            'status' => self::STATUS_UPLOADED,
            'input_path' => $package_path,
            'file_sha256' => strtolower($target_sha256 !== '' ? $target_sha256 : $assembled_sha),
            'checkpoint_json' => $this->encode_json($checkpoint),
        ));

        $this->append_event($job_id, 'success', 'upload_finished', 'Upload assembled and validated.', array(
            'bytes' => (int) filesize($package_path),
            'sha256' => strtolower($target_sha256 !== '' ? $target_sha256 : $assembled_sha),
        ));

        wp_send_json_success(array(
            'job_id' => $job_id,
            'upload_id' => $upload_id,
            'status' => self::STATUS_UPLOADED,
            'phase' => self::PHASE_UPLOAD_COMPLETE,
            'file_sha256' => strtolower($target_sha256 !== '' ? $target_sha256 : $assembled_sha),
        ));
    }

    public function ajax_start_dry_run()
    {
        $this->assert_ajax_permission('mulopimfwc_import_products');
        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
        if ($job_id === '') {
            wp_send_json_error(array('message' => __('Job ID is required.', 'multi-location-product-and-inventory-management-pro')));
        }

        $job = $this->get_job($job_id);
        if (!$job || $job['type'] !== self::TYPE_IMPORT) {
            wp_send_json_error(array('message' => __('Import job not found.', 'multi-location-product-and-inventory-management-pro')));
        }
        if (!$this->can_access_job($job)) {
            wp_send_json_error(array('message' => __('You do not have permission to access this job.', 'multi-location-product-and-inventory-management-pro')));
        }
        if (empty($job['input_path']) || !file_exists($job['input_path'])) {
            wp_send_json_error(array('message' => __('Upload is not complete. Finish upload first.', 'multi-location-product-and-inventory-management-pro')));
        }

        $options = $this->get_request_options();
        $options['mode'] = 'dry_run';

        $checkpoint = array(
            'stage' => 'prepare',
            'phase' => self::PHASE_DRY_RUN,
            'rows_since_checkpoint' => 0,
            'last_checkpoint_ts' => time(),
            'options' => $options,
            'prepare' => array(
                'part_index' => 0,
                'part_offset' => 0,
                'rows_total' => 0,
                'rows_by_pass' => array(),
                'canonical_headers' => array(),
                'current_line' => 0,
                'pass_files' => array(),
                'verified_parts' => array(),
            ),
            'process' => array(
                'pass_index' => 0,
                'byte_offset' => 0,
                'log_cursor' => 0,
            ),
            'pipeline_ref' => array(),
        );

        $this->update_job($job_id, array(
            'phase' => self::PHASE_DRY_RUN,
            'status' => self::STATUS_QUEUED,
            'options_json' => $this->encode_json($options),
            'checkpoint_json' => $this->encode_json($checkpoint),
            'rows_total' => 0,
            'rows_processed' => 0,
            'rows_failed' => 0,
            'progress_percent' => 0,
            'started_at_gmt' => null,
            'finished_at_gmt' => null,
        ));
        $this->append_event($job_id, 'info', 'dry_run_queued', 'Dry-run phase queued.');
        $this->schedule_worker($job_id);

        $job = $this->get_job($job_id);
        wp_send_json_success($this->format_job_status($job));
    }

    public function ajax_confirm_apply()
    {
        $this->assert_ajax_permission('mulopimfwc_import_products');

        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
        if ($job_id === '') {
            wp_send_json_error(array('message' => __('Job ID is required.', 'multi-location-product-and-inventory-management-pro')));
        }

        $job = $this->get_job($job_id);
        if (!$job || $job['type'] !== self::TYPE_IMPORT) {
            wp_send_json_error(array('message' => __('Import job not found.', 'multi-location-product-and-inventory-management-pro')));
        }
        if (!$this->can_access_job($job)) {
            wp_send_json_error(array('message' => __('You do not have permission to access this job.', 'multi-location-product-and-inventory-management-pro')));
        }
        if ($job['status'] !== self::STATUS_AWAITING_CONFIRMATION || $job['phase'] !== self::PHASE_AWAITING_CONFIRMATION) {
            wp_send_json_error(array('message' => __('Job is not awaiting apply confirmation.', 'multi-location-product-and-inventory-management-pro')));
        }

        $options = $this->get_request_options();
        $mode = isset($options['mode']) ? sanitize_key((string) $options['mode']) : 'create_update';
        if ($mode !== 'update_only') {
            $mode = 'create_update';
        }
        $options['mode'] = $mode;
        $options['confirmed'] = true;

        $checkpoint = $this->decode_json($job['checkpoint_json'], array());
        if (!is_array($checkpoint)) {
            $checkpoint = array();
        }
        $checkpoint['stage'] = 'process';
        $checkpoint['phase'] = self::PHASE_APPLY;
        if (!isset($checkpoint['process']) || !is_array($checkpoint['process'])) {
            $checkpoint['process'] = array();
        }
        $checkpoint['process']['pass_index'] = 0;
        $checkpoint['process']['byte_offset'] = 0;
        $checkpoint['process']['log_cursor'] = 0;
        $checkpoint['pipeline_ref'] = array();
        $checkpoint['rows_since_checkpoint'] = 0;
        $checkpoint['last_checkpoint_ts'] = time();
        $checkpoint['options'] = $options;

        $this->update_job($job_id, array(
            'phase' => self::PHASE_APPLY,
            'status' => self::STATUS_QUEUED,
            'options_json' => $this->encode_json($options),
            'checkpoint_json' => $this->encode_json($checkpoint),
            'rows_processed' => 0,
            'rows_failed' => 0,
            'progress_percent' => 0,
            'started_at_gmt' => null,
            'finished_at_gmt' => null,
        ));
        $this->append_event($job_id, 'info', 'apply_queued', 'Apply phase queued.');
        $this->schedule_worker($job_id);

        $job = $this->get_job($job_id);
        wp_send_json_success($this->format_job_status($job));
    }

    public function ajax_get_job_status()
    {
        $this->assert_ajax_permission('manage_woocommerce');
        $job_id = isset($_REQUEST['job_id']) ? sanitize_text_field(wp_unslash($_REQUEST['job_id'])) : '';
        if ($job_id === '') {
            wp_send_json_error(array('message' => __('Job ID is required.', 'multi-location-product-and-inventory-management-pro')));
        }
        $job = $this->get_job($job_id);
        if (!$job) {
            wp_send_json_error(array('message' => __('Job not found.', 'multi-location-product-and-inventory-management-pro')));
        }
        if (!$this->can_access_job($job)) {
            wp_send_json_error(array('message' => __('You do not have permission to access this job.', 'multi-location-product-and-inventory-management-pro')));
        }
        wp_send_json_success($this->format_job_status($job));
    }

    public function ajax_get_job_events()
    {
        $this->assert_ajax_permission('manage_woocommerce');
        $job_id = isset($_REQUEST['job_id']) ? sanitize_text_field(wp_unslash($_REQUEST['job_id'])) : '';
        $cursor = isset($_REQUEST['cursor']) ? (int) $_REQUEST['cursor'] : 0;
        if ($job_id === '') {
            wp_send_json_error(array('message' => __('Job ID is required.', 'multi-location-product-and-inventory-management-pro')));
        }
        $job = $this->get_job($job_id);
        if (!$job) {
            wp_send_json_error(array('message' => __('Job not found.', 'multi-location-product-and-inventory-management-pro')));
        }
        if (!$this->can_access_job($job)) {
            wp_send_json_error(array('message' => __('You do not have permission to access this job.', 'multi-location-product-and-inventory-management-pro')));
        }

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, seq, level, code, message, context_json, created_at_gmt
                 FROM {$this->tables['events']}
                 WHERE job_id = %s AND id > %d
                 ORDER BY id ASC
                 LIMIT 200",
                $job_id,
                max(0, $cursor)
            ),
            ARRAY_A
        );

        $events = array();
        $next_cursor = $cursor;
        foreach ((array) $rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id > $next_cursor) {
                $next_cursor = $id;
            }
            $events[] = array(
                'seq' => isset($row['seq']) ? (int) $row['seq'] : 0,
                'level' => isset($row['level']) ? (string) $row['level'] : 'info',
                'code' => isset($row['code']) ? (string) $row['code'] : '',
                'message' => isset($row['message']) ? (string) $row['message'] : '',
                'context' => $this->decode_json(isset($row['context_json']) ? $row['context_json'] : '', array()),
                'timestamp' => $this->gmt_to_iso(isset($row['created_at_gmt']) ? $row['created_at_gmt'] : ''),
            );
        }

        wp_send_json_success(array(
            'events' => $events,
            'next_cursor' => $next_cursor,
        ));
    }

    public function ajax_get_active_jobs()
    {
        $this->assert_ajax_permission('manage_woocommerce');

        $type = isset($_REQUEST['type']) ? sanitize_key(wp_unslash($_REQUEST['type'])) : '';
        if ($type !== '' && !in_array($type, array(self::TYPE_IMPORT, self::TYPE_EXPORT), true)) {
            wp_send_json_error(array(
                'message' => __('Invalid job type filter.', 'multi-location-product-and-inventory-management-pro'),
            ));
        }

        $limit = isset($_REQUEST['limit']) ? (int) $_REQUEST['limit'] : 10;
        $limit = max(1, min(20, $limit));

        $jobs = $this->get_active_jobs($type, $limit);
        $payload = array();
        foreach ($jobs as $job) {
            if (!$this->can_access_job($job)) {
                continue;
            }
            $payload[] = $this->format_job_status($job);
        }

        wp_send_json_success(array(
            'jobs' => $payload,
            'count' => count($payload),
        ));
    }

    public function ajax_pause_job()
    {
        $this->assert_ajax_permission('manage_woocommerce');
        $job = $this->get_job_or_error_from_request();
        if (!$job) {
            return;
        }
        if (!in_array($job['status'], array(self::STATUS_QUEUED, self::STATUS_RUNNING, self::STATUS_UPLOADING), true)) {
            wp_send_json_error(array('message' => __('Job cannot be paused in its current state.', 'multi-location-product-and-inventory-management-pro')));
        }
        $this->update_job($job['job_id'], array('status' => self::STATUS_PAUSED));
        $this->append_event($job['job_id'], 'warning', 'job_paused', 'Job paused by user.');
        wp_send_json_success($this->format_job_status($this->get_job($job['job_id'])));
    }

    public function ajax_resume_job()
    {
        $this->assert_ajax_permission('manage_woocommerce');
        $job = $this->get_job_or_error_from_request();
        if (!$job) {
            return;
        }
        if ($job['status'] !== self::STATUS_PAUSED) {
            wp_send_json_error(array('message' => __('Only paused jobs can be resumed.', 'multi-location-product-and-inventory-management-pro')));
        }
        $this->update_job($job['job_id'], array('status' => self::STATUS_QUEUED));
        $this->append_event($job['job_id'], 'info', 'job_resumed', 'Job resumed by user.');
        $this->schedule_worker($job['job_id']);
        wp_send_json_success($this->format_job_status($this->get_job($job['job_id'])));
    }

    public function ajax_cancel_job()
    {
        $this->assert_ajax_permission('manage_woocommerce');
        $job = $this->get_job_or_error_from_request();
        if (!$job) {
            return;
        }
        if (in_array($job['status'], array(self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED), true)) {
            wp_send_json_error(array('message' => __('Job is already finalized.', 'multi-location-product-and-inventory-management-pro')));
        }
        $this->update_job($job['job_id'], array(
            'status' => self::STATUS_CANCELLED,
            'finished_at_gmt' => $this->now_gmt(),
            'progress_percent' => 100,
        ));
        $this->append_event($job['job_id'], 'warning', 'job_cancelled', 'Job cancelled by user.');
        wp_send_json_success($this->format_job_status($this->get_job($job['job_id'])));
    }

    public function ajax_download_artifact()
    {
        $job_id = isset($_GET['job_id']) ? sanitize_text_field(wp_unslash($_GET['job_id'])) : '';
        $artifact_id = isset($_GET['artifact_id']) ? (int) $_GET['artifact_id'] : 0;
        $expires = isset($_GET['expires']) ? (int) $_GET['expires'] : 0;
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';

        if ($job_id === '' || $artifact_id <= 0 || $expires <= 0 || $token === '') {
            wp_die(esc_html__('Invalid artifact download request.', 'multi-location-product-and-inventory-management-pro'), 400);
        }
        if (time() > $expires) {
            wp_die(esc_html__('Download link expired.', 'multi-location-product-and-inventory-management-pro'), 403);
        }

        $job = $this->get_job($job_id);
        if (!$job) {
            wp_die(esc_html__('Job not found.', 'multi-location-product-and-inventory-management-pro'), 404);
        }
        if (!$this->can_access_job($job)) {
            wp_die(esc_html__('Unauthorized.', 'multi-location-product-and-inventory-management-pro'), 403);
        }
        $expected = $this->build_download_token($job_id, $artifact_id, get_current_user_id(), $expires);
        if (!hash_equals($expected, $token)) {
            wp_die(esc_html__('Invalid download token.', 'multi-location-product-and-inventory-management-pro'), 403);
        }

        $artifact = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['artifacts']} WHERE id = %d AND job_id = %s LIMIT 1",
                $artifact_id,
                $job_id
            ),
            ARRAY_A
        );
        if (!$artifact) {
            wp_die(esc_html__('Artifact not found.', 'multi-location-product-and-inventory-management-pro'), 404);
        }
        $path = isset($artifact['path']) ? (string) $artifact['path'] : '';
        if ($path === '' || !file_exists($path) || !is_file($path)) {
            wp_die(esc_html__('Artifact file is unavailable.', 'multi-location-product-and-inventory-management-pro'), 404);
        }

        $this->stream_file_with_range($path, basename($path));
        exit;
    }

    public function handle_legacy_export_proxy_ajax()
    {
        $this->assert_ajax_permission('mulopimfwc_export_products');
        if (!$this->is_v2_enabled()) {
            $this->legacy->handle_export_ajax();
            return;
        }

        if (!$this->can_start_new_job(self::TYPE_EXPORT, self::EXPORT_ACTIVE_LIMIT)) {
            $payload = array(
                'message' => __('Export queue is full. Please wait for active jobs to complete.', 'multi-location-product-and-inventory-management-pro'),
            );
            $active_jobs = $this->get_active_jobs(self::TYPE_EXPORT, 2);
            if (!empty($active_jobs)) {
                $visible_jobs = array();
                foreach ($active_jobs as $active_job) {
                    if ($this->can_access_job($active_job)) {
                        $visible_jobs[] = $this->format_job_status($active_job);
                    }
                }
                if (!empty($visible_jobs)) {
                    $payload['active_jobs'] = $visible_jobs;
                }
            }
            wp_send_json_error($payload);
        }

        $user_id = get_current_user_id();
        $options = $this->get_request_options();
        $job = $this->create_job(self::TYPE_EXPORT, self::PHASE_EXPORT, self::STATUS_QUEUED, $user_id, $options);
        $estimated_products = $this->count_products_for_export();
        $checkpoint = array(
            'stage' => 'init',
            'rows_since_checkpoint' => 0,
            'last_checkpoint_ts' => time(),
            'exported_at' => gmdate('c'),
            'source_site' => home_url(),
            'last_product_id' => 0,
            'processed_products' => 0,
            'rows_written' => 0,
            'estimated_products' => $estimated_products,
            'parts' => array(),
            'current_part' => null,
            'summary' => array(
                'products' => 0,
                'variations' => 0,
                'taxonomy_terms' => 0,
                'locations' => 0,
                'relationships' => 0,
                'location_inventory' => 0,
                'media_refs' => 0,
                'processed_products' => 0,
            ),
        );
        $this->update_job($job['job_id'], array(
            'checkpoint_json' => $this->encode_json($checkpoint),
            'rows_total' => max(0, (int) $estimated_products),
        ));
        $this->append_event($job['job_id'], 'info', 'job_created', 'Legacy export request routed to v2 job flow.');
        $this->schedule_worker($job['job_id']);

        if ($estimated_products < 20000) {
            $this->run_job_inline($job['job_id'], 15);
        }

        $job = $this->get_job($job['job_id']);
        $status_payload = $this->format_job_status($job);
        wp_send_json_success(array(
            'job_id' => $job['job_id'],
            'status' => $status_payload['status'],
            'phase' => $status_payload['phase'],
            'status_url' => $status_payload['status_url'],
            'events_url' => $status_payload['events_url'],
            'download_url' => isset($status_payload['download_url']) ? $status_payload['download_url'] : '',
        ));
    }

    public function handle_legacy_import_proxy_ajax()
    {
        $this->assert_ajax_permission('mulopimfwc_import_products');
        if (!$this->is_v2_enabled()) {
            $this->legacy->handle_import_ajax();
            return;
        }

        if (!isset($_FILES['csv_file']) || !isset($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array(
                'message' => __('Please upload a valid CSV or package ZIP file.', 'multi-location-product-and-inventory-management-pro'),
            ));
        }
        if (!$this->can_start_new_job(self::TYPE_IMPORT, self::IMPORT_ACTIVE_LIMIT)) {
            $payload = array(
                'message' => __('An import job is already active.', 'multi-location-product-and-inventory-management-pro'),
            );
            $active_job = $this->get_latest_active_job(self::TYPE_IMPORT);
            if ($active_job && $this->can_access_job($active_job)) {
                $payload['active_job'] = $this->format_job_status($active_job);
            }
            wp_send_json_error($payload);
        }

        $file = $_FILES['csv_file'];
        $filename = sanitize_file_name((string) $file['name']);
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        $job = $this->create_job(self::TYPE_IMPORT, self::PHASE_UPLOADING, self::STATUS_UPLOADING, get_current_user_id(), $this->get_request_options());
        $upload = $this->create_upload($job['job_id'], '');
        $job_dir = $this->get_job_dir($job['job_id']);
        $input_dir = $job_dir . DIRECTORY_SEPARATOR . 'input';
        $this->ensure_directory($input_dir);
        $raw_path = $input_dir . DIRECTORY_SEPARATOR . 'legacy-upload.' . ($ext !== '' ? $ext : 'bin');

        if (!@move_uploaded_file((string) $file['tmp_name'], $raw_path)) {
            if (!@copy((string) $file['tmp_name'], $raw_path)) {
                wp_send_json_error(array('message' => __('Failed to persist upload for import job.', 'multi-location-product-and-inventory-management-pro')));
            }
        }
        if (!file_exists($raw_path)) {
            wp_send_json_error(array('message' => __('Upload file is unavailable after move.', 'multi-location-product-and-inventory-management-pro')));
        }
        if ((int) filesize($raw_path) > self::MAX_UPLOAD_BYTES) {
            wp_send_json_error(array('message' => __('File exceeds 2GB upload limit.', 'multi-location-product-and-inventory-management-pro')));
        }

        $package_path = $raw_path;
        if ($ext === 'csv') {
            $package_path = $this->build_package_from_single_csv($job['job_id'], $raw_path, $filename);
        } elseif ($ext !== 'zip') {
            wp_send_json_error(array('message' => __('Legacy import proxy only supports CSV or ZIP files.', 'multi-location-product-and-inventory-management-pro')));
        }

        $file_sha = hash_file('sha256', $package_path);
        $this->update_upload($upload['upload_id'], array(
            'chunk_count' => 1,
            'received_chunks_json' => $this->encode_json(array('0' => array('size' => (int) filesize($raw_path)))),
            'assembled_path' => $package_path,
            'size_bytes' => (int) filesize($package_path),
            'status' => 'assembled',
            'target_sha256' => $file_sha,
        ));

        $options = $this->get_request_options();
        $options['mode'] = 'dry_run';
        $checkpoint = array(
            'stage' => 'prepare',
            'phase' => self::PHASE_DRY_RUN,
            'upload_id' => $upload['upload_id'],
            'package_path' => $package_path,
            'rows_since_checkpoint' => 0,
            'last_checkpoint_ts' => time(),
            'options' => $options,
            'prepare' => array(
                'part_index' => 0,
                'part_offset' => 0,
                'rows_total' => 0,
                'rows_by_pass' => array(),
                'canonical_headers' => array(),
                'current_line' => 0,
                'pass_files' => array(),
                'verified_parts' => array(),
            ),
            'process' => array(
                'pass_index' => 0,
                'byte_offset' => 0,
                'log_cursor' => 0,
            ),
            'pipeline_ref' => array(),
        );

        $this->update_job($job['job_id'], array(
            'phase' => self::PHASE_DRY_RUN,
            'status' => self::STATUS_QUEUED,
            'input_path' => $package_path,
            'file_sha256' => $file_sha,
            'options_json' => $this->encode_json($options),
            'checkpoint_json' => $this->encode_json($checkpoint),
        ));
        $this->append_event($job['job_id'], 'info', 'legacy_proxy', 'Legacy import request routed to v2 dry-run workflow.');
        $this->schedule_worker($job['job_id']);

        wp_send_json_success(array(
            'job_id' => $job['job_id'],
            'status' => self::STATUS_QUEUED,
            'phase' => self::PHASE_DRY_RUN,
            'status_url' => $this->build_status_url($job['job_id']),
            'events_url' => $this->build_events_url($job['job_id']),
        ));
    }

    public function process_job_action($job_id)
    {
        $job_id = sanitize_text_field((string) $job_id);
        if ($job_id === '') {
            return;
        }
        $job = $this->get_job($job_id);
        if (!$job) {
            return;
        }

        if (in_array($job['status'], array(self::STATUS_CANCELLED, self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_PAUSED, self::STATUS_AWAITING_CONFIRMATION), true)) {
            return;
        }

        $updates = array(
            'status' => self::STATUS_RUNNING,
            'updated_at_gmt' => $this->now_gmt(),
        );
        if (empty($job['started_at_gmt'])) {
            $updates['started_at_gmt'] = $this->now_gmt();
        }
        $this->update_job($job_id, $updates);
        $job = $this->get_job($job_id);

        $deadline = microtime(true) + $this->get_worker_budget_seconds();
        $done = false;
        try {
            if ($job['type'] === self::TYPE_EXPORT) {
                $done = $this->process_export_job($job, $deadline);
            } elseif ($job['type'] === self::TYPE_IMPORT) {
                $done = $this->process_import_job($job, $deadline);
            } else {
                throw new Exception('Unknown job type: ' . $job['type']);
            }
        } catch (Throwable $e) {
            $this->append_event($job_id, 'error', 'job_failed', $e->getMessage());
            $this->update_job($job_id, array(
                'status' => self::STATUS_FAILED,
                'finished_at_gmt' => $this->now_gmt(),
                'error_json' => $this->encode_json(array(
                    'message' => $e->getMessage(),
                    'trace' => (defined('WP_DEBUG') && WP_DEBUG) ? $e->getTraceAsString() : '',
                )),
                'progress_percent' => 100,
            ));
            return;
        }

        $job = $this->get_job($job_id);
        if (!$job) {
            return;
        }

        if (!$done && !in_array($job['status'], array(self::STATUS_PAUSED, self::STATUS_CANCELLED, self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_AWAITING_CONFIRMATION), true)) {
            $this->schedule_worker($job_id, 1);
        }
    }

    private function prime_export_batch_data($product_ids)
    {
        $product_ids = array_values(array_unique(array_filter(array_map('intval', (array) $product_ids))));
        if (empty($product_ids)) {
            return array();
        }

        $this->prime_post_caches_for_export($product_ids, 'product');
        $variation_ids_by_parent = $this->fetch_export_variation_ids_by_parent($product_ids);

        if (!empty($variation_ids_by_parent)) {
            $variation_ids = array();
            foreach ($variation_ids_by_parent as $ids) {
                foreach ((array) $ids as $variation_id) {
                    $variation_id = (int) $variation_id;
                    if ($variation_id > 0) {
                        $variation_ids[] = $variation_id;
                    }
                }
            }

            if (!empty($variation_ids)) {
                $this->prime_post_caches_for_export($variation_ids, 'product_variation');
            }
        }

        return $variation_ids_by_parent;
    }

    private function prime_post_caches_for_export($post_ids, $object_type)
    {
        $post_ids = array_values(array_unique(array_filter(array_map('intval', (array) $post_ids))));
        if (empty($post_ids)) {
            return;
        }

        if (function_exists('_prime_post_caches')) {
            _prime_post_caches($post_ids, false, true);
        } else {
            update_meta_cache('post', $post_ids);
            foreach ($post_ids as $post_id) {
                get_post($post_id);
            }
        }

        if (function_exists('update_object_term_cache')) {
            update_object_term_cache($post_ids, $object_type);
        }
    }

    private function fetch_export_variation_ids_by_parent($product_ids)
    {
        $product_ids = array_values(array_unique(array_filter(array_map('intval', (array) $product_ids))));
        if (empty($product_ids)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
        $query = $this->wpdb->prepare(
            "SELECT ID, post_parent
             FROM {$this->wpdb->posts}
             WHERE post_type = 'product_variation'
             AND post_parent IN ($placeholders)
             AND post_status <> 'trash'
             ORDER BY post_parent ASC, menu_order ASC, ID ASC",
            $product_ids
        );
        $rows = $query ? $this->wpdb->get_results($query) : array();
        $grouped = array();

        if (!is_array($rows)) {
            return $grouped;
        }

        foreach ($rows as $row) {
            $variation_id = isset($row->ID) ? (int) $row->ID : 0;
            $parent_id = isset($row->post_parent) ? (int) $row->post_parent : 0;
            if ($variation_id <= 0 || $parent_id <= 0) {
                continue;
            }
            if (!isset($grouped[$parent_id])) {
                $grouped[$parent_id] = array();
            }
            $grouped[$parent_id][] = $variation_id;
        }

        return $grouped;
    }

    private function process_export_job($job, $deadline)
    {
        $job_id = $job['job_id'];
        $options = $this->decode_json($job['options_json'], array());
        $checkpoint = $this->decode_json($job['checkpoint_json'], array());
        if (!is_array($checkpoint) || empty($checkpoint)) {
            $checkpoint = array(
                'stage' => 'init',
                'rows_since_checkpoint' => 0,
                'last_checkpoint_ts' => time(),
                'exported_at' => gmdate('c'),
                'source_site' => home_url(),
                'last_product_id' => 0,
                'processed_products' => 0,
                'rows_written' => 0,
                'estimated_products' => $this->count_products_for_export(),
                'parts' => array(),
                'current_part' => null,
                'summary' => array(),
            );
        }

        $headers = $this->legacy->get_canonical_headers_for_v2();

        if ($checkpoint['stage'] === 'init') {
            $this->append_event($job_id, 'info', 'export_init', 'Export initialization started.');

            $init_rows = array();
            $product_taxonomies = $this->legacy->get_supported_product_taxonomies_for_v2();
            foreach ($product_taxonomies as $taxonomy) {
                if ($taxonomy === 'mulopimfwc_store_location') {
                    continue;
                }
                $terms = get_terms(array(
                    'taxonomy' => $taxonomy,
                    'hide_empty' => false,
                ));
                if (is_wp_error($terms) || empty($terms)) {
                    continue;
                }
                foreach ($terms as $term) {
                    if (!$term || is_wp_error($term)) {
                        continue;
                    }
                    $row = array_fill_keys($headers, '');
                    $row['schema_version'] = $this->legacy->get_schema_version();
                    $row['exported_at'] = $checkpoint['exported_at'];
                    $row['source_site'] = $checkpoint['source_site'];
                    $row['row_type'] = 'taxonomy_term';
                    $row['row_key'] = 'term:' . $taxonomy . ':' . (string) $term->slug;
                    $row['source_term_id'] = (string) $term->term_id;
                    $row['taxonomy'] = $taxonomy;
                    $row['term_slug'] = (string) $term->slug;
                    $row['term_name'] = (string) $term->name;
                    $row['term_description'] = (string) $term->description;
                    $row['term_parent_slug'] = $term->parent ? (string) $this->legacy->get_term_slug_for_v2($term->parent, $taxonomy) : '';
                    $row['term_meta_json'] = wp_json_encode($this->legacy->get_sanitized_term_meta_for_v2($term->term_id));
                    $init_rows[] = $row;
                    $checkpoint['summary']['taxonomy_terms'] = isset($checkpoint['summary']['taxonomy_terms']) ? ((int) $checkpoint['summary']['taxonomy_terms'] + 1) : 1;
                }
            }

            $locations = get_terms(array(
                'taxonomy' => 'mulopimfwc_store_location',
                'hide_empty' => false,
            ));
            if (!is_wp_error($locations) && !empty($locations)) {
                foreach ($locations as $location) {
                    if (!$location || is_wp_error($location)) {
                        continue;
                    }
                    $row = array_fill_keys($headers, '');
                    $row['schema_version'] = $this->legacy->get_schema_version();
                    $row['exported_at'] = $checkpoint['exported_at'];
                    $row['source_site'] = $checkpoint['source_site'];
                    $row['row_type'] = 'location';
                    $row['row_key'] = 'location:' . (string) $location->slug;
                    $row['source_term_id'] = (string) $location->term_id;
                    $row['location_slug'] = (string) $location->slug;
                    $row['location_name'] = (string) $location->name;
                    $row['location_description'] = (string) $location->description;
                    $row['location_parent_slug'] = $location->parent ? (string) $this->legacy->get_term_slug_for_v2($location->parent, 'mulopimfwc_store_location') : '';
                    $row['location_term_meta_json'] = wp_json_encode($this->legacy->get_sanitized_term_meta_for_v2($location->term_id));
                    $init_rows[] = $row;
                    $checkpoint['summary']['locations'] = isset($checkpoint['summary']['locations']) ? ((int) $checkpoint['summary']['locations'] + 1) : 1;
                }
            }

            if (!empty($init_rows)) {
                $this->write_export_rows_block($job_id, $headers, $init_rows, $checkpoint);
            }

            $checkpoint['stage'] = 'products';
            $this->persist_export_checkpoint($job_id, $checkpoint, true);
            $this->append_event($job_id, 'info', 'export_init_done', 'Export initialization finished.');
        }

        if ($checkpoint['stage'] === 'products') {
            $product_taxonomies = $this->legacy->get_supported_product_taxonomies_for_v2();
            $locations = get_terms(array(
                'taxonomy' => 'mulopimfwc_store_location',
                'hide_empty' => false,
            ));
            $location_by_id = array();
            if (!is_wp_error($locations) && is_array($locations)) {
                foreach ($locations as $location) {
                    if ($location && !is_wp_error($location)) {
                        $location_by_id[(int) $location->term_id] = $location;
                    }
                }
            }

            while (microtime(true) < $deadline && !$this->memory_guard_reached()) {
                $product_ids = $this->fetch_export_batch_ids((int) $checkpoint['last_product_id'], $this->get_export_batch_size());
                if (empty($product_ids)) {
                    $checkpoint['stage'] = 'finalize';
                    break;
                }
                $variation_ids_by_parent = $this->prime_export_batch_data($product_ids);

                foreach ($product_ids as $product_id) {
                    if (microtime(true) >= $deadline || $this->memory_guard_reached()) {
                        break 2;
                    }

                    $product = wc_get_product($product_id);
                    if (!$product || $product->is_type('variation')) {
                        $checkpoint['last_product_id'] = (int) $product_id;
                        continue;
                    }

                    $block_rows = array();
                    $product_row = $this->legacy->build_product_export_row_for_v2(
                        $product,
                        $product_taxonomies,
                        $checkpoint['exported_at'],
                        $checkpoint['source_site'],
                        $options
                    );
                    $block_rows[] = $product_row;
                    $checkpoint['summary']['products'] = isset($checkpoint['summary']['products']) ? ((int) $checkpoint['summary']['products'] + 1) : 1;

                    $product_key = isset($product_row['row_key']) ? (string) $product_row['row_key'] : '';
                    $product_sku = (string) $product->get_sku();
                    $relationship_rows = 0;
                    $location_rows = 0;
                    $media_rows = 0;
                    $media_seen = array();

                    $this->legacy->append_relationship_rows_for_v2($block_rows, $product, $product_key, $checkpoint['exported_at'], $checkpoint['source_site'], $relationship_rows);
                    $this->legacy->append_location_inventory_rows_for_v2($block_rows, $product->get_id(), $product_sku, $product_key, $location_by_id, $location_rows, $checkpoint['exported_at'], $checkpoint['source_site']);
                    $this->legacy->append_media_rows_for_v2($block_rows, $product, 'product', $product_key, $media_seen, $media_rows, $checkpoint['exported_at'], $checkpoint['source_site']);

                    $checkpoint['summary']['relationships'] = isset($checkpoint['summary']['relationships']) ? ((int) $checkpoint['summary']['relationships'] + (int) $relationship_rows) : (int) $relationship_rows;
                    $checkpoint['summary']['location_inventory'] = isset($checkpoint['summary']['location_inventory']) ? ((int) $checkpoint['summary']['location_inventory'] + (int) $location_rows) : (int) $location_rows;
                    $checkpoint['summary']['media_refs'] = isset($checkpoint['summary']['media_refs']) ? ((int) $checkpoint['summary']['media_refs'] + (int) $media_rows) : (int) $media_rows;

                    if ($product->is_type('variable')) {
                        $child_ids = isset($variation_ids_by_parent[(int) $product_id]) ? (array) $variation_ids_by_parent[(int) $product_id] : array();
                        if (empty($child_ids)) {
                            $child_ids = (array) $product->get_children();
                        }

                        foreach ($child_ids as $variation_id) {
                            $variation = wc_get_product($variation_id);
                            if (!$variation || !$variation->is_type('variation')) {
                                continue;
                            }
                            $variation_row = $this->legacy->build_variation_export_row_for_v2(
                                $variation,
                                $product,
                                $product_key,
                                $checkpoint['exported_at'],
                                $checkpoint['source_site'],
                                $options
                            );
                            $block_rows[] = $variation_row;
                            $checkpoint['summary']['variations'] = isset($checkpoint['summary']['variations']) ? ((int) $checkpoint['summary']['variations'] + 1) : 1;

                            $variation_key = isset($variation_row['row_key']) ? (string) $variation_row['row_key'] : '';
                            $variation_sku = (string) $variation->get_sku();
                            $variation_location_rows = 0;
                            $variation_media_rows = 0;
                            $this->legacy->append_location_inventory_rows_for_v2($block_rows, $variation->get_id(), $variation_sku, $variation_key, $location_by_id, $variation_location_rows, $checkpoint['exported_at'], $checkpoint['source_site']);
                            $this->legacy->append_media_rows_for_v2($block_rows, $variation, 'variation', $variation_key, $media_seen, $variation_media_rows, $checkpoint['exported_at'], $checkpoint['source_site']);
                            $checkpoint['summary']['location_inventory'] = isset($checkpoint['summary']['location_inventory']) ? ((int) $checkpoint['summary']['location_inventory'] + (int) $variation_location_rows) : (int) $variation_location_rows;
                            $checkpoint['summary']['media_refs'] = isset($checkpoint['summary']['media_refs']) ? ((int) $checkpoint['summary']['media_refs'] + (int) $variation_media_rows) : (int) $variation_media_rows;
                        }
                    }

                    $this->write_export_rows_block($job_id, $headers, $block_rows, $checkpoint);
                    $checkpoint['last_product_id'] = (int) $product_id;
                    $checkpoint['processed_products'] = (int) $checkpoint['processed_products'] + 1;
                    $checkpoint['summary']['processed_products'] = (int) $checkpoint['processed_products'];

                    if ($this->should_checkpoint($checkpoint)) {
                        $this->persist_export_checkpoint($job_id, $checkpoint, false);
                    }
                }
            }

            if ($checkpoint['stage'] === 'products') {
                $this->persist_export_checkpoint($job_id, $checkpoint, false);
            }
        }

        if ($checkpoint['stage'] === 'finalize' && microtime(true) < $deadline) {
            $this->finalize_current_export_part($job_id, $checkpoint);

            $job_dir = $this->get_job_dir($job_id);
            $manifest = array(
                'package_schema_version' => self::PACKAGE_SCHEMA_VERSION,
                'canonical_schema_version' => $this->legacy->get_schema_version(),
                'source_site' => home_url(),
                'exported_at' => isset($checkpoint['exported_at']) ? $checkpoint['exported_at'] : gmdate('c'),
                'parts' => isset($checkpoint['parts']) ? array_values($checkpoint['parts']) : array(),
                'summary' => isset($checkpoint['summary']) ? $checkpoint['summary'] : array(),
                'options' => is_array($options) ? $options : array(),
            );
            $manifest_path = $job_dir . DIRECTORY_SEPARATOR . 'manifest.json';
            $this->atomic_write($manifest_path, wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $reports_dir = $job_dir . DIRECTORY_SEPARATOR . 'reports';
            $this->ensure_directory($reports_dir);
            $summary_path = $reports_dir . DIRECTORY_SEPARATOR . 'summary.json';
            $this->atomic_write($summary_path, wp_json_encode($manifest['summary'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $zip_path = $job_dir . DIRECTORY_SEPARATOR . self::PACKAGE_FILENAME;
            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception('Failed to create export package archive.');
            }
            $zip->addFile($manifest_path, 'manifest.json');
            if (is_array($manifest['parts'])) {
                foreach ($manifest['parts'] as $part_meta) {
                    $name = isset($part_meta['name']) ? (string) $part_meta['name'] : '';
                    if ($name === '') {
                        continue;
                    }
                    $part_path = $job_dir . DIRECTORY_SEPARATOR . 'parts' . DIRECTORY_SEPARATOR . basename($name);
                    if (file_exists($part_path)) {
                        $zip->addFile($part_path, 'parts/' . basename($name));
                    }
                }
            }
            if (file_exists($summary_path)) {
                $zip->addFile($summary_path, 'reports/summary.json');
            }
            $zip->close();

            $sha = hash_file('sha256', $zip_path);
            $size = (int) filesize($zip_path);
            $this->add_artifact($job_id, 'package', $zip_path, $sha, $size);
            $this->add_artifact($job_id, 'summary', $summary_path, hash_file('sha256', $summary_path), (int) filesize($summary_path));

            $checkpoint['stage'] = 'done';
            $this->update_job($job_id, array(
                'status' => self::STATUS_COMPLETED,
                'phase' => self::PHASE_EXPORT,
                'progress_percent' => 100,
                'rows_total' => max((int) $job['rows_total'], (int) $checkpoint['rows_written']),
                'rows_processed' => (int) $checkpoint['rows_written'],
                'rows_failed' => 0,
                'output_path' => $zip_path,
                'checkpoint_json' => $this->encode_json($checkpoint),
                'summary_json' => $this->encode_json($manifest['summary']),
                'finished_at_gmt' => $this->now_gmt(),
            ));

            $this->append_event($job_id, 'success', 'export_completed', 'Export job completed successfully.', array(
                'rows_written' => (int) $checkpoint['rows_written'],
                'artifact' => basename($zip_path),
                'size_bytes' => $size,
            ));
            return true;
        }

        return false;
    }

    private function process_import_job($job, $deadline)
    {
        $job_id = $job['job_id'];
        $checkpoint = $this->decode_json($job['checkpoint_json'], array());
        if (!is_array($checkpoint)) {
            $checkpoint = array();
        }
        if (!isset($checkpoint['stage'])) {
            $checkpoint['stage'] = 'prepare';
        }
        if (!isset($checkpoint['phase'])) {
            $checkpoint['phase'] = $job['phase'];
        }

        if (empty($job['input_path']) || !file_exists($job['input_path'])) {
            throw new Exception('Import package path is missing.');
        }

        if ($checkpoint['stage'] === 'prepare') {
            $prepared = $this->prepare_import_pass_files($job, $checkpoint, $deadline);
            if (!$prepared) {
                $this->persist_import_checkpoint($job_id, $checkpoint, $job);
                return false;
            }
            $checkpoint['stage'] = 'process';
            $checkpoint['process'] = array(
                'pass_index' => 0,
                'byte_offset' => 0,
                'log_cursor' => 0,
            );
            $checkpoint['pipeline_ref'] = array();
            $this->append_event($job_id, 'info', 'prepare_done', 'Import package pre-processing completed.');
            $this->persist_import_checkpoint($job_id, $checkpoint, $job, true);
        }

        if ($checkpoint['stage'] === 'process') {
            $done = $this->process_import_pass_slices($job, $checkpoint, $deadline);
            $this->persist_import_checkpoint($job_id, $checkpoint, $job, true);
            if (!$done) {
                return false;
            }
            $checkpoint['stage'] = 'finalize';
        }

        if ($checkpoint['stage'] === 'finalize') {
            $pipeline = $this->load_pipeline_context($job_id, $checkpoint);
            if (!$pipeline) {
                throw new Exception('Failed to load import pipeline state for finalization.');
            }
            $result = $this->legacy->finalize_incremental_import_pipeline_for_v2($pipeline['runtime'], $pipeline['state']);
            $this->emit_new_pipeline_logs($job_id, $checkpoint, $pipeline['state']);

            $summary = isset($result['summary']) && is_array($result['summary']) ? $result['summary'] : array();
            $reports_dir = $this->get_job_dir($job_id) . DIRECTORY_SEPARATOR . 'reports';
            $this->ensure_directory($reports_dir);

            $summary_path = $reports_dir . DIRECTORY_SEPARATOR . ($job['phase'] === self::PHASE_DRY_RUN ? 'dry-run-summary.json' : 'apply-summary.json');
            $this->atomic_write($summary_path, wp_json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->add_artifact($job_id, 'summary', $summary_path, hash_file('sha256', $summary_path), (int) filesize($summary_path));

            $errors_path = $reports_dir . DIRECTORY_SEPARATOR . ($job['phase'] === self::PHASE_DRY_RUN ? 'dry-run-errors.json' : 'apply-errors.json');
            $warnings_path = $reports_dir . DIRECTORY_SEPARATOR . ($job['phase'] === self::PHASE_DRY_RUN ? 'dry-run-warnings.json' : 'apply-warnings.json');
            $this->atomic_write($errors_path, wp_json_encode(isset($result['errors']) ? $result['errors'] : array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->atomic_write($warnings_path, wp_json_encode(isset($result['warnings']) ? $result['warnings'] : array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->add_artifact($job_id, 'errors', $errors_path, hash_file('sha256', $errors_path), (int) filesize($errors_path));
            $this->add_artifact($job_id, 'warnings', $warnings_path, hash_file('sha256', $warnings_path), (int) filesize($warnings_path));

            if (!empty($result['failed_rows_csv'])) {
                $failed_csv_path = $reports_dir . DIRECTORY_SEPARATOR . ($job['phase'] === self::PHASE_DRY_RUN ? 'dry-run-failed-rows.csv.gz' : 'apply-failed-rows.csv.gz');
                $gz = gzopen($failed_csv_path, 'wb9');
                if ($gz) {
                    gzwrite($gz, (string) $result['failed_rows_csv']);
                    gzclose($gz);
                    $this->add_artifact($job_id, 'failed_rows', $failed_csv_path, hash_file('sha256', $failed_csv_path), (int) filesize($failed_csv_path));
                }
            }

            $status = self::STATUS_COMPLETED;
            $phase = $job['phase'];
            if ($job['phase'] === self::PHASE_DRY_RUN) {
                $status = self::STATUS_AWAITING_CONFIRMATION;
                $phase = self::PHASE_AWAITING_CONFIRMATION;
                $this->append_event($job_id, 'success', 'dry_run_completed', 'Dry-run phase completed. Awaiting confirmation for apply.');
            } else {
                $this->append_event($job_id, 'success', 'import_completed', 'Import apply phase completed successfully.');
            }

            $checkpoint['stage'] = ($status === self::STATUS_AWAITING_CONFIRMATION) ? 'awaiting_confirmation' : 'done';
            $this->update_job($job_id, array(
                'status' => $status,
                'phase' => $phase,
                'summary_json' => $this->encode_json($summary),
                'checkpoint_json' => $this->encode_json($checkpoint),
                'rows_total' => isset($summary['rows_total']) ? (int) $summary['rows_total'] : (int) $job['rows_total'],
                'rows_processed' => isset($summary['rows_processed']) ? (int) $summary['rows_processed'] : (int) $job['rows_processed'],
                'rows_failed' => isset($summary['rows_failed']) ? (int) $summary['rows_failed'] : (int) $job['rows_failed'],
                'progress_percent' => 100,
                'finished_at_gmt' => $status === self::STATUS_AWAITING_CONFIRMATION ? null : $this->now_gmt(),
            ));

            return $status !== self::STATUS_AWAITING_CONFIRMATION;
        }

        return false;
    }

    private function prepare_import_pass_files($job, &$checkpoint, $deadline)
    {
        $job_id = $job['job_id'];
        $package = $this->load_import_package_manifest($job, $checkpoint);
        $parts = isset($package['parts']) && is_array($package['parts']) ? $package['parts'] : array();

        if (!isset($checkpoint['prepare']) || !is_array($checkpoint['prepare'])) {
            $checkpoint['prepare'] = array();
        }
        $prepare = &$checkpoint['prepare'];
        if (!isset($prepare['part_index'])) {
            $prepare['part_index'] = 0;
        }
        if (!isset($prepare['part_offset'])) {
            $prepare['part_offset'] = 0;
        }
        if (!isset($prepare['rows_total'])) {
            $prepare['rows_total'] = 0;
        }
        if (!isset($prepare['rows_by_pass']) || !is_array($prepare['rows_by_pass'])) {
            $prepare['rows_by_pass'] = array();
        }
        if (!isset($prepare['canonical_headers']) || !is_array($prepare['canonical_headers'])) {
            $prepare['canonical_headers'] = array();
        }
        if (!isset($prepare['current_line'])) {
            $prepare['current_line'] = 0;
        }
        if (!isset($prepare['pass_files']) || !is_array($prepare['pass_files']) || empty($prepare['pass_files'])) {
            $prepare['pass_files'] = $this->initialize_pass_files($job_id, $checkpoint['phase'] === self::PHASE_DRY_RUN);
        }
        if (!isset($prepare['verified_parts']) || !is_array($prepare['verified_parts'])) {
            $prepare['verified_parts'] = array();
        }

        while ($prepare['part_index'] < count($parts)) {
            if (microtime(true) >= $deadline || $this->memory_guard_reached()) {
                return false;
            }

            $part_meta = $parts[(int) $prepare['part_index']];
            $part_name = isset($part_meta['name']) ? basename((string) $part_meta['name']) : '';
            if ($part_name === '') {
                $prepare['part_index']++;
                $prepare['part_offset'] = 0;
                $prepare['current_line'] = 0;
                continue;
            }

            $extracted_part_path = $this->extract_package_part($job_id, (string) $job['input_path'], $part_name);
            if (!file_exists($extracted_part_path)) {
                throw new Exception('Unable to extract package part: ' . esc_html($part_name));
            }

            if (!isset($prepare['verified_parts'][$part_name])) {
                $expected_sha = isset($part_meta['sha256']) ? strtolower((string) $part_meta['sha256']) : '';
                if ($expected_sha !== '') {
                    $actual_sha = strtolower((string) hash_file('sha256', $extracted_part_path));
                    if (!hash_equals($expected_sha, $actual_sha)) {
                        throw new Exception('Part checksum mismatch for ' . esc_html($part_name));
                    }
                }
                $prepare['verified_parts'][$part_name] = 1;
            }

            $handle = fopen('compress.zlib://' . $extracted_part_path, 'rb');
            if (!$handle) {
                throw new Exception('Unable to open package part: ' . esc_html($part_name));
            }

            $headers = fgetcsv($handle);
            if (!is_array($headers) || empty($headers)) {
                fclose($handle);
                throw new Exception('Invalid CSV part format. Header row missing: ' . esc_html($part_name));
            }
            $headers = array_map(function ($header) {
                $header = str_replace("\xEF\xBB\xBF", '', (string) $header);
                return trim((string) $header);
            }, $headers);

            if (empty($prepare['canonical_headers'])) {
                $prepare['canonical_headers'] = $headers;
            }

            if ((int) $prepare['part_offset'] > 0) {
                if (@fseek($handle, (int) $prepare['part_offset'], SEEK_SET) !== 0) {
                    $resume_line = isset($prepare['current_line']) ? (int) $prepare['current_line'] : 0;
                    $skipped = 0;
                    while ($skipped < $resume_line) {
                        $skip_row = fgetcsv($handle);
                        if ($skip_row === false) {
                            break;
                        }
                        $skipped++;
                    }
                }
            } else {
                $prepare['part_offset'] = (int) ftell($handle);
            }

            while (true) {
                if (microtime(true) >= $deadline || $this->memory_guard_reached()) {
                    $prepare['part_offset'] = (int) ftell($handle);
                    fclose($handle);
                    return false;
                }

                $raw_row = fgetcsv($handle);
                if ($raw_row === false) {
                    break;
                }
                $prepare['part_offset'] = (int) ftell($handle);
                $prepare['current_line'] = (int) $prepare['current_line'] + 1;

                if (count($raw_row) < count($headers)) {
                    $raw_row = array_pad($raw_row, count($headers), '');
                } elseif (count($raw_row) > count($headers)) {
                    $raw_row = array_slice($raw_row, 0, count($headers));
                }
                $assoc = array_combine($headers, $raw_row);
                if (!is_array($assoc)) {
                    continue;
                }
                $assoc['__line'] = (int) $prepare['current_line'];

                if ((int) $prepare['rows_total'] === 0) {
                    $schema_error = $this->legacy->validate_schema_for_v2($headers, array($assoc));
                    if ($schema_error !== '') {
                        fclose($handle);
                        throw new Exception(esc_html($schema_error));
                    }
                }

                $row_type = isset($assoc['row_type']) ? sanitize_key((string) $assoc['row_type']) : '';
                $pass = $this->map_row_type_to_pass($row_type);
                if ($pass === '') {
                    continue;
                }
                $this->append_row_to_pass_file($prepare['pass_files'], $pass, $assoc);

                $prepare['rows_total'] = (int) $prepare['rows_total'] + 1;
                $max_import_rows = $this->get_max_import_rows();
                if ($max_import_rows > 0 && $prepare['rows_total'] > $max_import_rows) {
                    fclose($handle);
                    throw new Exception(sprintf('Import row cap exceeded (%s rows).', esc_html(number_format_i18n($max_import_rows))));
                }
                $prepare['rows_by_pass'][$pass] = isset($prepare['rows_by_pass'][$pass]) ? ((int) $prepare['rows_by_pass'][$pass] + 1) : 1;
                $checkpoint['rows_since_checkpoint'] = isset($checkpoint['rows_since_checkpoint']) ? ((int) $checkpoint['rows_since_checkpoint'] + 1) : 1;
                if ($this->should_checkpoint($checkpoint)) {
                    $this->persist_import_checkpoint($job_id, $checkpoint, $job);
                }
            }

            fclose($handle);
            $prepare['part_index'] = (int) $prepare['part_index'] + 1;
            $prepare['part_offset'] = 0;
            $prepare['current_line'] = 0;
        }

        $checkpoint['rows_total'] = (int) $prepare['rows_total'];
        $this->update_job($job_id, array(
            'rows_total' => (int) $prepare['rows_total'],
            'progress_percent' => 5,
        ));

        return true;
    }

    private function process_import_pass_slices($job, &$checkpoint, $deadline)
    {
        $job_id = $job['job_id'];
        $prepare = isset($checkpoint['prepare']) && is_array($checkpoint['prepare']) ? $checkpoint['prepare'] : array();
        $pass_files = isset($prepare['pass_files']) && is_array($prepare['pass_files']) ? $prepare['pass_files'] : array();

        $pipeline = $this->load_pipeline_context($job_id, $checkpoint);
        if (!$pipeline) {
            $options = isset($checkpoint['options']) && is_array($checkpoint['options']) ? $checkpoint['options'] : $this->decode_json($job['options_json'], array());
            if ($job['phase'] === self::PHASE_DRY_RUN) {
                $options['mode'] = 'dry_run';
            }
            $pipeline = $this->legacy->init_incremental_import_pipeline_for_v2($options, isset($prepare['rows_total']) ? (int) $prepare['rows_total'] : 0);
            $this->persist_pipeline_context($job_id, $checkpoint, $pipeline);
        }

        if (!isset($checkpoint['process']) || !is_array($checkpoint['process'])) {
            $checkpoint['process'] = array(
                'pass_index' => 0,
                'byte_offset' => 0,
                'log_cursor' => 0,
            );
        }
        $process = &$checkpoint['process'];
        $pass_order = $this->legacy->get_import_pass_order_for_v2();

        while ((int) $process['pass_index'] < count($pass_order)) {
            if (microtime(true) >= $deadline || $this->memory_guard_reached()) {
                $this->persist_pipeline_context($job_id, $checkpoint, $pipeline);
                return false;
            }

            $pass = $pass_order[(int) $process['pass_index']];
            $pass_file = isset($pass_files[$pass]) ? $pass_files[$pass] : '';
            $rows = array();
            $eof = true;
            $byte_offset = isset($process['byte_offset']) ? (int) $process['byte_offset'] : 0;
            $next_offset = $byte_offset;

            if ($pass_file !== '' && file_exists($pass_file)) {
                $handle = fopen($pass_file, 'rb');
                if ($handle) {
                    if ($byte_offset > 0) {
                        @fseek($handle, $byte_offset, SEEK_SET);
                    }
                    $chunk_rows = 0;
                    while ($chunk_rows < $this->get_checkpoint_rows() && microtime(true) < $deadline && !$this->memory_guard_reached()) {
                        $line = fgets($handle);
                        if ($line === false) {
                            break;
                        }
                        $next_offset = (int) ftell($handle);
                        $line = trim((string) $line);
                        if ($line === '') {
                            continue;
                        }
                        $row = json_decode($line, true);
                        if (is_array($row)) {
                            $rows[] = $row;
                            $chunk_rows++;
                        }
                    }
                    $eof = feof($handle);
                    fclose($handle);
                }
            }

            $is_final_chunk = $eof;
            if ($pass === 'terms_locations' && $eof && empty($rows)) {
                $is_final_chunk = true;
            }

            if (!empty($rows) || ($pass === 'terms_locations' && $is_final_chunk)) {
                $this->legacy->process_incremental_import_pass_for_v2($pass, $rows, $pipeline['runtime'], $pipeline['state'], $is_final_chunk);
                $this->emit_new_pipeline_logs($job_id, $checkpoint, $pipeline['state']);
                $this->persist_pipeline_context($job_id, $checkpoint, $pipeline);
            }

            $summary = isset($pipeline['state']['summary']) && is_array($pipeline['state']['summary']) ? $pipeline['state']['summary'] : array();
            $rows_total = isset($summary['rows_total']) ? (int) $summary['rows_total'] : (isset($prepare['rows_total']) ? (int) $prepare['rows_total'] : 0);
            $rows_processed = isset($summary['rows_processed']) ? (int) $summary['rows_processed'] : 0;
            $rows_failed = isset($summary['rows_failed']) ? (int) $summary['rows_failed'] : 0;
            $progress = $rows_total > 0 ? min(99, round(($rows_processed / $rows_total) * 100, 2)) : 0;
            if ((int) $process['pass_index'] >= (count($pass_order) - 1) && $eof) {
                $progress = max($progress, 98);
            }

            $this->update_job($job_id, array(
                'rows_total' => $rows_total,
                'rows_processed' => $rows_processed,
                'rows_failed' => $rows_failed,
                'progress_percent' => $progress,
            ));

            if ($eof) {
                $this->append_event($job_id, 'info', 'pass_complete', sprintf('Import pass completed: %s', $pass));
                $process['pass_index'] = (int) $process['pass_index'] + 1;
                $process['byte_offset'] = 0;
            } else {
                $process['byte_offset'] = $next_offset;
            }

            $checkpoint['rows_since_checkpoint'] = isset($checkpoint['rows_since_checkpoint']) ? ((int) $checkpoint['rows_since_checkpoint'] + max(1, count($rows))) : max(1, count($rows));
            if ($this->should_checkpoint($checkpoint)) {
                $this->persist_import_checkpoint($job_id, $checkpoint, $job);
            }

            if (microtime(true) >= $deadline || $this->memory_guard_reached()) {
                $this->persist_pipeline_context($job_id, $checkpoint, $pipeline);
                return false;
            }
        }

        $this->persist_pipeline_context($job_id, $checkpoint, $pipeline);
        return true;
    }

    private function emit_new_pipeline_logs($job_id, &$checkpoint, $state)
    {
        if (!isset($checkpoint['process']) || !is_array($checkpoint['process'])) {
            $checkpoint['process'] = array();
        }
        if (!isset($checkpoint['process']['log_cursor'])) {
            $checkpoint['process']['log_cursor'] = 0;
        }

        $logs = isset($state['logs']) && is_array($state['logs']) ? $state['logs'] : array();
        $cursor = (int) $checkpoint['process']['log_cursor'];
        $total = count($logs);
        if ($cursor < 0) {
            $cursor = 0;
        }

        for ($i = $cursor; $i < $total; $i++) {
            $entry = $logs[$i];
            if (!is_array($entry)) {
                continue;
            }
            $level = isset($entry['level']) ? (string) $entry['level'] : 'info';
            $message = isset($entry['message']) ? (string) $entry['message'] : '';
            if ($message === '') {
                continue;
            }
            $code = 'import_log';
            if ($level === 'error') {
                $code = 'import_error';
            } elseif ($level === 'warning') {
                $code = 'import_warning';
            } elseif ($level === 'success') {
                $code = 'import_success';
            }
            $this->append_event($job_id, $level, $code, $message);
        }

        $checkpoint['process']['log_cursor'] = $total;
    }

    private function initialize_pass_files($job_id, $truncate = true)
    {
        $order = $this->legacy->get_import_pass_order_for_v2();
        $pass_dir = $this->get_job_dir($job_id) . DIRECTORY_SEPARATOR . 'work' . DIRECTORY_SEPARATOR . 'passes';
        $this->ensure_directory($pass_dir);
        $files = array();
        foreach ($order as $pass) {
            $path = $pass_dir . DIRECTORY_SEPARATOR . $pass . '.jsonl';
            unset($this->pass_file_buffers[$path]);
            if ($truncate) {
                $this->atomic_write($path, '');
            } elseif (!file_exists($path)) {
                $this->atomic_write($path, '');
            }
            $files[$pass] = $path;
        }
        return $files;
    }

    private function append_row_to_pass_file($pass_files, $pass, $row)
    {
        if (!isset($pass_files[$pass])) {
            return;
        }
        $path = (string) $pass_files[$pass];
        $encoded = $this->encode_pass_row_json($row);
        if (!is_string($encoded) || $encoded === '') {
            $row_key = is_array($row) && isset($row['row_key']) ? (string) $row['row_key'] : '';
            $line = is_array($row) && isset($row['__line']) ? (int) $row['__line'] : 0;
            $context = array();
            if ($line > 0) {
                $context[] = 'line ' . $line;
            }
            if ($row_key !== '') {
                $context[] = 'row_key ' . $row_key;
            }
            $suffix = !empty($context) ? (' (' . implode(', ', $context) . ')') : '';
            throw new Exception('Failed to encode import row for pass file' . esc_html($suffix) . '.');
        }
        if (!isset($this->pass_file_buffers[$path])) {
            $this->pass_file_buffers[$path] = '';
        }

        $this->pass_file_buffers[$path] .= $encoded . "\n";
        if (strlen($this->pass_file_buffers[$path]) >= self::PASS_FILE_BUFFER_BYTES) {
            $this->flush_pass_file_buffer($path);
        }
    }

    private function flush_pass_file_buffer($path)
    {
        $path = (string) $path;
        if ($path === '' || !isset($this->pass_file_buffers[$path])) {
            return;
        }
        $payload = (string) $this->pass_file_buffers[$path];
        if ($payload === '') {
            return;
        }

        $written = @file_put_contents($path, $payload, FILE_APPEND);
        if ($written === false) {
            throw new Exception('Failed writing import pass buffer to disk.');
        }
        $this->pass_file_buffers[$path] = '';
    }

    private function flush_pass_file_buffers()
    {
        if (empty($this->pass_file_buffers) || !is_array($this->pass_file_buffers)) {
            return;
        }
        foreach (array_keys($this->pass_file_buffers) as $path) {
            $this->flush_pass_file_buffer($path);
        }
    }

    private function encode_pass_row_json($row)
    {
        $json_flags = JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $json_flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $encoded = wp_json_encode($row, $json_flags);
        if (is_string($encoded) && $encoded !== '') {
            return $encoded;
        }

        $sanitized = $this->sanitize_value_for_json($row);
        $encoded = wp_json_encode($sanitized, $json_flags);
        if (is_string($encoded) && $encoded !== '') {
            return $encoded;
        }

        return false;
    }

    private function sanitize_value_for_json($value)
    {
        if (is_array($value)) {
            $result = array();
            foreach ($value as $key => $item) {
                $result[$key] = $this->sanitize_value_for_json($item);
            }
            return $result;
        }

        if (is_string($value)) {
            if (function_exists('wp_check_invalid_utf8')) {
                return wp_check_invalid_utf8($value, true);
            }
            if (function_exists('iconv')) {
                $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
                if (is_string($converted)) {
                    return $converted;
                }
            }
        }

        return $value;
    }

    private function load_import_package_manifest($job, &$checkpoint)
    {
        if (isset($checkpoint['package']) && is_array($checkpoint['package']) && !empty($checkpoint['package']['manifest'])) {
            return $checkpoint['package']['manifest'];
        }

        $zip_path = (string) $job['input_path'];
        if (!file_exists($zip_path)) {
            throw new Exception('Input package file does not exist.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            throw new Exception('Unable to open import package ZIP.');
        }
        $manifest_raw = $zip->getFromName('manifest.json');
        $zip->close();
        if (!is_string($manifest_raw) || trim($manifest_raw) === '') {
            throw new Exception('manifest.json is missing in import package.');
        }

        $manifest = json_decode($manifest_raw, true);
        if (!is_array($manifest)) {
            throw new Exception('manifest.json is invalid JSON.');
        }
        $schema_version = isset($manifest['package_schema_version']) ? (int) $manifest['package_schema_version'] : 0;
        if ($schema_version !== self::PACKAGE_SCHEMA_VERSION) {
            throw new Exception('Unsupported package schema version: ' . $schema_version);
        }
        $parts = isset($manifest['parts']) && is_array($manifest['parts']) ? $manifest['parts'] : array();
        if (empty($parts)) {
            throw new Exception('Import package does not contain any parts.');
        }

        $checkpoint['package'] = array(
            'manifest' => $manifest,
        );
        return $manifest;
    }

    private function extract_package_part($job_id, $zip_path, $part_name)
    {
        $target_dir = $this->get_job_dir($job_id) . DIRECTORY_SEPARATOR . 'work' . DIRECTORY_SEPARATOR . 'parts';
        $this->ensure_directory($target_dir);
        $target_path = $target_dir . DIRECTORY_SEPARATOR . basename($part_name);
        if (file_exists($target_path)) {
            return $target_path;
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            throw new Exception('Unable to open package archive for extraction.');
        }

        $internal_name = 'parts/' . basename($part_name);
        $stream = $zip->getStream($internal_name);
        if (!$stream) {
            $zip->close();
            throw new Exception('Missing part in package ZIP: ' . esc_html(basename($part_name)));
        }
        $out = fopen($target_path, 'wb');
        if (!$out) {
            fclose($stream);
            $zip->close();
            throw new Exception('Failed to create extracted part file.');
        }
        while (!feof($stream)) {
            $chunk = fread($stream, 1048576);
            if ($chunk === false) {
                break;
            }
            fwrite($out, $chunk);
        }
        fclose($out);
        fclose($stream);
        $zip->close();

        return $target_path;
    }

    private function write_export_rows_block($job_id, $headers, $rows, &$checkpoint)
    {
        if (empty($rows)) {
            return;
        }

        $line_payloads = array();
        $bytes = 0;
        foreach ($rows as $row) {
            $line = $this->build_csv_line($headers, $row);
            $line_payloads[] = $line;
            $bytes += strlen($line);
        }

        if ($this->should_rotate_export_part($checkpoint, count($line_payloads), $bytes)) {
            $this->finalize_current_export_part($job_id, $checkpoint);
        }

        $handle = $this->open_current_export_part($job_id, $checkpoint, $headers);
        if (!$handle) {
            throw new Exception('Failed to open export part writer.');
        }

        foreach ($line_payloads as $index => $line) {
            $row = $rows[$index];
            if (gzwrite($handle, $line) === false) {
                gzclose($handle);
                throw new Exception('Failed writing export part line.');
            }
            $checkpoint['current_part']['row_count'] = (int) $checkpoint['current_part']['row_count'] + 1;
            $checkpoint['current_part']['uncompressed_size'] = (int) $checkpoint['current_part']['uncompressed_size'] + strlen($line);
            $row_type = isset($row['row_type']) ? sanitize_key((string) $row['row_type']) : '';
            if ($row_type !== '') {
                $checkpoint['current_part']['row_types'][$row_type] = 1;
            }

            $checkpoint['rows_written'] = (int) $checkpoint['rows_written'] + 1;
            $checkpoint['rows_since_checkpoint'] = isset($checkpoint['rows_since_checkpoint']) ? ((int) $checkpoint['rows_since_checkpoint'] + 1) : 1;
        }

        gzclose($handle);
    }

    private function open_current_export_part($job_id, &$checkpoint, $headers)
    {
        if (!isset($checkpoint['current_part']) || !is_array($checkpoint['current_part']) || empty($checkpoint['current_part'])) {
            $index = (count(isset($checkpoint['parts']) && is_array($checkpoint['parts']) ? $checkpoint['parts'] : array()) + 1);
            $part_name = sprintf('part-%05d.csv.gz', $index);
            $part_dir = $this->get_job_dir($job_id) . DIRECTORY_SEPARATOR . 'parts';
            $this->ensure_directory($part_dir);
            $part_path = $part_dir . DIRECTORY_SEPARATOR . $part_name;
            $checkpoint['current_part'] = array(
                'index' => $index,
                'name' => $part_name,
                'path' => $part_path,
                'row_count' => 0,
                'uncompressed_size' => 0,
                'header_written' => false,
                'row_types' => array(),
            );
        }

        $path = $checkpoint['current_part']['path'];
        $mode = file_exists($path) ? 'ab9' : 'wb9';
        $handle = gzopen($path, $mode);
        if (!$handle) {
            return false;
        }
        if (empty($checkpoint['current_part']['header_written'])) {
            $header_line = $this->build_csv_line($headers, array_combine($headers, $headers));
            if (gzwrite($handle, $header_line) === false) {
                gzclose($handle);
                return false;
            }
            $checkpoint['current_part']['header_written'] = true;
            $checkpoint['current_part']['uncompressed_size'] = (int) $checkpoint['current_part']['uncompressed_size'] + strlen($header_line);
        }

        return $handle;
    }

    private function finalize_current_export_part($job_id, &$checkpoint)
    {
        if (!isset($checkpoint['current_part']) || !is_array($checkpoint['current_part']) || empty($checkpoint['current_part'])) {
            return;
        }
        $part = $checkpoint['current_part'];
        $path = isset($part['path']) ? (string) $part['path'] : '';
        if ($path === '' || !file_exists($path)) {
            $checkpoint['current_part'] = null;
            return;
        }
        if ((int) $part['row_count'] <= 0) {
            @unlink($path);
            $checkpoint['current_part'] = null;
            return;
        }

        $row_type = 'mixed';
        if (isset($part['row_types']) && is_array($part['row_types']) && count($part['row_types']) === 1) {
            $keys = array_keys($part['row_types']);
            $row_type = (string) $keys[0];
        }

        $checkpoint['parts'][] = array(
            'name' => isset($part['name']) ? (string) $part['name'] : basename($path),
            'row_type' => $row_type,
            'row_count' => (int) $part['row_count'],
            'sha256' => hash_file('sha256', $path),
            'compressed_size' => (int) filesize($path),
            'uncompressed_size' => (int) $part['uncompressed_size'],
        );
        $checkpoint['current_part'] = null;
    }

    private function should_rotate_export_part($checkpoint, $incoming_rows, $incoming_bytes)
    {
        if (!isset($checkpoint['current_part']) || !is_array($checkpoint['current_part']) || empty($checkpoint['current_part'])) {
            return false;
        }
        $current_rows = isset($checkpoint['current_part']['row_count']) ? (int) $checkpoint['current_part']['row_count'] : 0;
        $current_bytes = isset($checkpoint['current_part']['uncompressed_size']) ? (int) $checkpoint['current_part']['uncompressed_size'] : 0;
        if ($current_rows <= 0) {
            return false;
        }
        if (($current_rows + (int) $incoming_rows) > self::EXPORT_PART_MAX_ROWS) {
            return true;
        }
        if (($current_bytes + (int) $incoming_bytes) > self::EXPORT_PART_MAX_UNCOMPRESSED_BYTES) {
            return true;
        }
        return false;
    }

    private function persist_export_checkpoint($job_id, &$checkpoint, $force)
    {
        if (!$force && !$this->should_checkpoint($checkpoint)) {
            return;
        }

        $estimated = isset($checkpoint['estimated_products']) ? max(1, (int) $checkpoint['estimated_products']) : 1;
        $processed_products = isset($checkpoint['processed_products']) ? (int) $checkpoint['processed_products'] : 0;
        $progress = (float) min(99, round(($processed_products / $estimated) * 100, 2));
        if (isset($checkpoint['stage']) && $checkpoint['stage'] === 'done') {
            $progress = 100;
        }

        $this->update_job($job_id, array(
            'checkpoint_json' => $this->encode_json($checkpoint),
            'summary_json' => $this->encode_json(isset($checkpoint['summary']) ? $checkpoint['summary'] : array()),
            'rows_total' => $estimated,
            'rows_processed' => isset($checkpoint['rows_written']) ? (int) $checkpoint['rows_written'] : 0,
            'rows_failed' => 0,
            'progress_percent' => $progress,
        ));
        $checkpoint['rows_since_checkpoint'] = 0;
        $checkpoint['last_checkpoint_ts'] = time();
    }

    private function persist_import_checkpoint($job_id, &$checkpoint, $job, $force = false)
    {
        if (!$force && !$this->should_checkpoint($checkpoint)) {
            return;
        }
        $this->flush_pass_file_buffers();
        $progress = isset($job['progress_percent']) ? (float) $job['progress_percent'] : 0;
        if (isset($checkpoint['stage']) && $checkpoint['stage'] === 'prepare') {
            $rows_total = isset($checkpoint['prepare']['rows_total']) ? (int) $checkpoint['prepare']['rows_total'] : 0;
            if ($rows_total > 0) {
                $progress = max($progress, 5);
            }
        }
        $this->update_job($job_id, array(
            'checkpoint_json' => $this->encode_json($checkpoint),
            'progress_percent' => $progress,
        ));
        $checkpoint['rows_since_checkpoint'] = 0;
        $checkpoint['last_checkpoint_ts'] = time();
    }

    private function should_checkpoint($checkpoint)
    {
        $rows = isset($checkpoint['rows_since_checkpoint']) ? (int) $checkpoint['rows_since_checkpoint'] : 0;
        $last = isset($checkpoint['last_checkpoint_ts']) ? (int) $checkpoint['last_checkpoint_ts'] : 0;
        if ($rows >= $this->get_checkpoint_rows()) {
            return true;
        }
        if ($last <= 0) {
            return true;
        }
        return (time() - $last) >= $this->get_checkpoint_seconds();
    }

    private function persist_pipeline_context($job_id, &$checkpoint, $pipeline)
    {
        if (!is_array($pipeline) || !isset($pipeline['runtime']) || !isset($pipeline['state'])) {
            return;
        }
        $state = $pipeline['state'];
        $runtime = $pipeline['runtime'];

        $state_dir = $this->get_job_dir($job_id) . DIRECTORY_SEPARATOR . 'work' . DIRECTORY_SEPARATOR . 'state';
        $this->ensure_directory($state_dir);

        $map_keys = array('row_map', 'sku_map', 'variation_sku_map', 'term_map', 'location_map', 'media_map');
        $map_refs = array();
        foreach ($map_keys as $map_key) {
            $map_path = $state_dir . DIRECTORY_SEPARATOR . $map_key . '.json';
            $map_payload = isset($state[$map_key]) && is_array($state[$map_key]) ? $state[$map_key] : array();
            $this->atomic_write($map_path, wp_json_encode($map_payload, JSON_UNESCAPED_SLASHES));
            $map_refs[$map_key] = $map_path;
            unset($state[$map_key]);
        }

        $state_path = $state_dir . DIRECTORY_SEPARATOR . 'state.json';
        $runtime_path = $state_dir . DIRECTORY_SEPARATOR . 'runtime.json';
        $state['map_refs'] = $map_refs;

        $this->atomic_write($state_path, wp_json_encode($state, JSON_UNESCAPED_SLASHES));
        $this->atomic_write($runtime_path, wp_json_encode($runtime, JSON_UNESCAPED_SLASHES));

        $checkpoint['pipeline_ref'] = array(
            'state_path' => $state_path,
            'runtime_path' => $runtime_path,
            'map_refs' => $map_refs,
        );
    }

    private function load_pipeline_context($job_id, $checkpoint)
    {
        if (!isset($checkpoint['pipeline_ref']) || !is_array($checkpoint['pipeline_ref'])) {
            return null;
        }
        $ref = $checkpoint['pipeline_ref'];
        $state_path = isset($ref['state_path']) ? (string) $ref['state_path'] : '';
        $runtime_path = isset($ref['runtime_path']) ? (string) $ref['runtime_path'] : '';
        if ($state_path === '' || $runtime_path === '' || !file_exists($state_path) || !file_exists($runtime_path)) {
            return null;
        }

        $state = json_decode((string) file_get_contents($state_path), true);
        $runtime = json_decode((string) file_get_contents($runtime_path), true);
        if (!is_array($state) || !is_array($runtime)) {
            return null;
        }

        $map_refs = isset($state['map_refs']) && is_array($state['map_refs']) ? $state['map_refs'] : array();
        unset($state['map_refs']);
        foreach (array('row_map', 'sku_map', 'variation_sku_map', 'term_map', 'location_map', 'media_map') as $map_key) {
            $map_path = isset($map_refs[$map_key]) ? (string) $map_refs[$map_key] : '';
            if ($map_path !== '' && file_exists($map_path)) {
                $decoded = json_decode((string) file_get_contents($map_path), true);
                $state[$map_key] = is_array($decoded) ? $decoded : array();
            } else {
                $state[$map_key] = array();
            }
        }

        return array(
            'runtime' => $runtime,
            'state' => $state,
        );
    }

    private function map_row_type_to_pass($row_type)
    {
        $row_type = sanitize_key((string) $row_type);
        if ($row_type === 'taxonomy_term' || $row_type === 'location') {
            return 'terms_locations';
        }
        if ($row_type === 'media_ref') {
            return 'media_refs';
        }
        if ($row_type === 'product') {
            return 'products';
        }
        if ($row_type === 'variation') {
            return 'variations';
        }
        if ($row_type === 'relationship') {
            return 'relationships';
        }
        if ($row_type === 'location_inventory') {
            return 'location_inventory';
        }
        return '';
    }

    private function fetch_export_batch_ids($last_product_id, $limit)
    {
        $last_product_id = max(0, (int) $last_product_id);
        $limit = max(1, (int) $limit);
        $placeholders = implode(',', array_fill(0, 5, '%s'));
        $sql = $this->wpdb->prepare(
            "SELECT ID
             FROM {$this->wpdb->posts}
             WHERE post_type = 'product'
               AND post_status IN ({$placeholders})
               AND ID > %d
             ORDER BY ID ASC
             LIMIT %d",
            'publish',
            'private',
            'draft',
            'pending',
            'future',
            $last_product_id,
            $limit
        );
        return array_map('intval', (array) $this->wpdb->get_col($sql));
    }

    private function count_products_for_export()
    {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(1) FROM {$this->wpdb->posts}
             WHERE post_type = %s
               AND post_status IN (%s,%s,%s,%s,%s)",
            'product',
            'publish',
            'private',
            'draft',
            'pending',
            'future'
        );
        return (int) $this->wpdb->get_var($sql);
    }

    private function build_package_from_single_csv($job_id, $csv_path, $filename)
    {
        if (!file_exists($csv_path)) {
            throw new Exception('CSV source file does not exist for package conversion.');
        }
        $job_dir = $this->get_job_dir($job_id);
        $parts_dir = $job_dir . DIRECTORY_SEPARATOR . 'parts';
        $this->ensure_directory($parts_dir);

        $part_name = 'part-00001.csv.gz';
        $part_path = $parts_dir . DIRECTORY_SEPARATOR . $part_name;
        $in = fopen($csv_path, 'rb');
        if (!$in) {
            throw new Exception('Unable to open CSV source for package conversion.');
        }
        $gz = gzopen($part_path, 'wb9');
        if (!$gz) {
            fclose($in);
            throw new Exception('Unable to create compressed CSV part.');
        }

        $row_count = 0;
        $uncompressed_size = 0;
        while (($line = fgets($in)) !== false) {
            if (gzwrite($gz, $line) === false) {
                fclose($in);
                gzclose($gz);
                throw new Exception('Failed writing compressed CSV part.');
            }
            $uncompressed_size += strlen($line);
            $row_count++;
        }
        fclose($in);
        gzclose($gz);

        $manifest = array(
            'package_schema_version' => self::PACKAGE_SCHEMA_VERSION,
            'canonical_schema_version' => $this->legacy->get_schema_version(),
            'source_site' => home_url(),
            'exported_at' => gmdate('c'),
            'parts' => array(
                array(
                    'name' => $part_name,
                    'row_type' => 'mixed',
                    'row_count' => max(0, $row_count - 1),
                    'sha256' => hash_file('sha256', $part_path),
                    'compressed_size' => (int) filesize($part_path),
                    'uncompressed_size' => $uncompressed_size,
                ),
            ),
            'summary' => array(
                'source_filename' => $filename,
            ),
            'options' => array(),
        );

        $manifest_path = $job_dir . DIRECTORY_SEPARATOR . 'manifest.json';
        $this->atomic_write($manifest_path, wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $zip_path = $job_dir . DIRECTORY_SEPARATOR . self::PACKAGE_FILENAME;
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Failed to create package ZIP from CSV.');
        }
        $zip->addFile($manifest_path, 'manifest.json');
        $zip->addFile($part_path, 'parts/' . $part_name);
        $zip->close();

        return $zip_path;
    }

    private function run_job_inline($job_id, $max_seconds)
    {
        $deadline = microtime(true) + max(1, (int) $max_seconds);
        while (microtime(true) < $deadline) {
            $job = $this->get_job($job_id);
            if (!$job) {
                return;
            }
            if (in_array($job['status'], array(self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED, self::STATUS_AWAITING_CONFIRMATION), true)) {
                return;
            }
            $this->process_job_action($job_id);
            $job = $this->get_job($job_id);
            if (!$job || in_array($job['status'], array(self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED, self::STATUS_AWAITING_CONFIRMATION), true)) {
                return;
            }
        }
    }

    private function can_start_new_job($type, $limit)
    {
        $type = sanitize_key((string) $type);
        $limit = max(1, (int) $limit);

        $active_statuses = $this->get_active_statuses();
        $placeholders = implode(',', array_fill(0, count($active_statuses), '%s'));
        $params = array_merge(array($type), $active_statuses);

        $sql = $this->wpdb->prepare(
            "SELECT COUNT(1)
             FROM {$this->tables['jobs']}
             WHERE type = %s
               AND status IN ({$placeholders})",
            $params
        );
        $active = (int) $this->wpdb->get_var($sql);
        return $active < $limit;
    }

    private function get_active_jobs($type = '', $limit = 10)
    {
        $active_statuses = $this->get_active_statuses();
        $placeholders = implode(',', array_fill(0, count($active_statuses), '%s'));
        $limit = max(1, min(20, (int) $limit));
        $type = sanitize_key((string) $type);

        if ($type !== '') {
            $params = array_merge(array($type), $active_statuses, array($limit));
            $sql = $this->wpdb->prepare(
                "SELECT *
                 FROM {$this->tables['jobs']}
                 WHERE type = %s
                   AND status IN ({$placeholders})
                 ORDER BY updated_at_gmt DESC
                 LIMIT %d",
                $params
            );
        } else {
            $params = array_merge($active_statuses, array($limit));
            $sql = $this->wpdb->prepare(
                "SELECT *
                 FROM {$this->tables['jobs']}
                 WHERE status IN ({$placeholders})
                 ORDER BY updated_at_gmt DESC
                 LIMIT %d",
                $params
            );
        }

        return (array) $this->wpdb->get_results($sql, ARRAY_A);
    }

    private function get_latest_active_job($type)
    {
        $jobs = $this->get_active_jobs($type, 1);
        if (!empty($jobs)) {
            return $jobs[0];
        }
        return null;
    }

    private function get_active_statuses()
    {
        return array(
            self::STATUS_QUEUED,
            self::STATUS_RUNNING,
            self::STATUS_UPLOADING,
            self::STATUS_UPLOADED,
            self::STATUS_AWAITING_CONFIRMATION,
            self::STATUS_PAUSED,
        );
    }

    private function create_job($type, $phase, $status, $user_id, $options)
    {
        $job_id = wp_generate_uuid4();
        $now = $this->now_gmt();
        $expires = gmdate('Y-m-d H:i:s', time() + (DAY_IN_SECONDS * $this->get_job_retention_days()));

        $this->wpdb->insert(
            $this->tables['jobs'],
            array(
                'job_id' => $job_id,
                'type' => sanitize_key((string) $type),
                'phase' => sanitize_key((string) $phase),
                'status' => sanitize_key((string) $status),
                'user_id' => (int) $user_id,
                'options_json' => $this->encode_json(is_array($options) ? $options : array()),
                'summary_json' => $this->encode_json(array()),
                'checkpoint_json' => $this->encode_json(array()),
                'error_json' => $this->encode_json(array()),
                'input_path' => '',
                'output_path' => '',
                'file_sha256' => '',
                'progress_percent' => 0,
                'rows_total' => 0,
                'rows_processed' => 0,
                'rows_failed' => 0,
                'last_event_seq' => 0,
                'created_at_gmt' => $now,
                'started_at_gmt' => null,
                'updated_at_gmt' => $now,
                'finished_at_gmt' => null,
                'expires_at_gmt' => $expires,
            ),
            array(
                '%s', '%s', '%s', '%s', '%d',
                '%s', '%s', '%s', '%s',
                '%s', '%s', '%s',
                '%f', '%d', '%d', '%d', '%d',
                '%s', '%s', '%s', '%s', '%s',
            )
        );

        return $this->get_job($job_id);
    }

    private function create_upload($job_id, $target_sha256)
    {
        $upload_id = wp_generate_uuid4();
        $now = $this->now_gmt();
        $this->wpdb->insert(
            $this->tables['uploads'],
            array(
                'upload_id' => $upload_id,
                'job_id' => $job_id,
                'chunk_count' => 0,
                'received_chunks_json' => $this->encode_json(array()),
                'target_sha256' => strtolower((string) $target_sha256),
                'assembled_path' => '',
                'size_bytes' => 0,
                'status' => 'uploading',
                'created_at_gmt' => $now,
                'updated_at_gmt' => $now,
            ),
            array('%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        return $this->get_upload($upload_id, $job_id);
    }

    private function get_upload($upload_id, $job_id)
    {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['uploads']} WHERE upload_id = %s AND job_id = %s LIMIT 1",
                $upload_id,
                $job_id
            ),
            ARRAY_A
        );
    }

    private function update_upload($upload_id, $data)
    {
        if (!is_array($data) || empty($data)) {
            return;
        }
        $data['updated_at_gmt'] = $this->now_gmt();
        $this->wpdb->update($this->tables['uploads'], $data, array('upload_id' => $upload_id));
    }

    private function get_job($job_id)
    {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['jobs']} WHERE job_id = %s LIMIT 1",
                $job_id
            ),
            ARRAY_A
        );
    }

    private function update_job($job_id, $data)
    {
        if (!is_array($data) || empty($data)) {
            return;
        }
        if (!isset($data['updated_at_gmt'])) {
            $data['updated_at_gmt'] = $this->now_gmt();
        }
        $this->wpdb->update($this->tables['jobs'], $data, array('job_id' => $job_id));
    }

    private function append_event($job_id, $level, $code, $message, $context = array())
    {
        $job_id = sanitize_text_field((string) $job_id);
        if ($job_id === '') {
            return;
        }

        if (!isset($this->event_seq_cache[$job_id])) {
            $last_seq_raw = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT last_event_seq FROM {$this->tables['jobs']} WHERE job_id = %s LIMIT 1",
                    $job_id
                )
            );
            if ($last_seq_raw === null) {
                return;
            }
            $this->event_seq_cache[$job_id] = (int) $last_seq_raw;
        }

        $last_seq = (int) $this->event_seq_cache[$job_id];
        if ($last_seq >= self::EVENT_LOG_CAP) {
            return;
        }
        $seq = $last_seq + 1;
        if ($seq === self::EVENT_LOG_CAP) {
            $level = 'warning';
            $code = 'log_cap_reached';
            $message = 'Event log cap reached at 20,000 lines. Further logs are suppressed.';
            $context = array();
        }

        $level = strtolower((string) $level);
        if (!in_array($level, array('info', 'success', 'warning', 'error'), true)) {
            $level = 'info';
        }
        $code = sanitize_key((string) $code);
        $message = trim(wp_strip_all_tags((string) $message));
        if ($message === '') {
            return;
        }

        $this->wpdb->insert(
            $this->tables['events'],
            array(
                'job_id' => $job_id,
                'seq' => $seq,
                'level' => $level,
                'code' => $code,
                'message' => $message,
                'context_json' => $this->encode_json(is_array($context) ? $context : array()),
                'created_at_gmt' => $this->now_gmt(),
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        $this->event_seq_cache[$job_id] = $seq;
        $this->wpdb->update(
            $this->tables['jobs'],
            array('last_event_seq' => $seq),
            array('job_id' => $job_id),
            array('%d'),
            array('%s')
        );
    }

    private function add_artifact($job_id, $kind, $path, $sha256, $size_bytes)
    {
        $expires = gmdate('Y-m-d H:i:s', time() + (DAY_IN_SECONDS * $this->get_artifact_retention_days()));
        $this->wpdb->insert(
            $this->tables['artifacts'],
            array(
                'job_id' => $job_id,
                'kind' => sanitize_key((string) $kind),
                'path' => (string) $path,
                'sha256' => strtolower((string) $sha256),
                'size_bytes' => max(0, (int) $size_bytes),
                'created_at_gmt' => $this->now_gmt(),
                'expires_at_gmt' => $expires,
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
    }

    private function get_job_or_error_from_request()
    {
        $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
        if ($job_id === '') {
            wp_send_json_error(array('message' => __('Job ID is required.', 'multi-location-product-and-inventory-management-pro')));
            return null;
        }
        $job = $this->get_job($job_id);
        if (!$job) {
            wp_send_json_error(array('message' => __('Job not found.', 'multi-location-product-and-inventory-management-pro')));
            return null;
        }
        if (!$this->can_access_job($job)) {
            wp_send_json_error(array('message' => __('You do not have permission to access this job.', 'multi-location-product-and-inventory-management-pro')));
            return null;
        }
        return $job;
    }

    private function format_job_status($job)
    {
        $checkpoint = $this->decode_json(isset($job['checkpoint_json']) ? $job['checkpoint_json'] : '', array());
        $summary = $this->decode_json(isset($job['summary_json']) ? $job['summary_json'] : '', array());

        $artifacts_rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['artifacts']} WHERE job_id = %s ORDER BY id ASC",
                $job['job_id']
            ),
            ARRAY_A
        );
        $artifacts = array();
        $download_url = '';
        foreach ((array) $artifacts_rows as $artifact) {
            $artifact_id = isset($artifact['id']) ? (int) $artifact['id'] : 0;
            if ($artifact_id <= 0) {
                continue;
            }
            $expires = time() + (15 * MINUTE_IN_SECONDS);
            $token = $this->build_download_token($job['job_id'], $artifact_id, get_current_user_id(), $expires);
            $url = admin_url('admin-ajax.php?action=mulopimfwc_ie_download_artifact&job_id=' . rawurlencode($job['job_id']) . '&artifact_id=' . $artifact_id . '&expires=' . $expires . '&token=' . rawurlencode($token));
            $item = array(
                'artifact_id' => $artifact_id,
                'kind' => isset($artifact['kind']) ? (string) $artifact['kind'] : '',
                'sha256' => isset($artifact['sha256']) ? (string) $artifact['sha256'] : '',
                'size_bytes' => isset($artifact['size_bytes']) ? (int) $artifact['size_bytes'] : 0,
                'created_at' => $this->gmt_to_iso(isset($artifact['created_at_gmt']) ? $artifact['created_at_gmt'] : ''),
                'expires_at' => $this->gmt_to_iso(isset($artifact['expires_at_gmt']) ? $artifact['expires_at_gmt'] : ''),
                'download_url' => $url,
            );
            if ($download_url === '' && $item['kind'] === 'package') {
                $download_url = $url;
            }
            $artifacts[] = $item;
        }

        $rows_total = isset($job['rows_total']) ? (int) $job['rows_total'] : 0;
        $rows_processed = isset($job['rows_processed']) ? (int) $job['rows_processed'] : 0;
        $eta_seconds = null;
        $started = isset($job['started_at_gmt']) ? (string) $job['started_at_gmt'] : '';
        if ($rows_total > 0 && $rows_processed > 0 && $rows_processed < $rows_total && $started !== '') {
            $elapsed = time() - strtotime($started . ' UTC');
            if ($elapsed > 0) {
                $throughput = $rows_processed / $elapsed;
                if ($throughput > 0) {
                    $eta_seconds = (int) ceil(($rows_total - $rows_processed) / $throughput);
                }
            }
        }

        $current_pass = '';
        if (is_array($checkpoint) && isset($checkpoint['process']['pass_index'])) {
            $order = $this->legacy->get_import_pass_order_for_v2();
            $index = (int) $checkpoint['process']['pass_index'];
            if (isset($order[$index])) {
                $current_pass = (string) $order[$index];
            }
        }

        $payload = array(
            'job_id' => (string) $job['job_id'],
            'type' => (string) $job['type'],
            'phase' => (string) $job['phase'],
            'status' => (string) $job['status'],
            'progress_percent' => round((float) (isset($job['progress_percent']) ? $job['progress_percent'] : 0), 2),
            'current_pass' => $current_pass,
            'rows_processed' => $rows_processed,
            'rows_total' => $rows_total,
            'rows_failed' => isset($job['rows_failed']) ? (int) $job['rows_failed'] : 0,
            'started_at' => $this->gmt_to_iso($started),
            'updated_at' => $this->gmt_to_iso(isset($job['updated_at_gmt']) ? $job['updated_at_gmt'] : ''),
            'eta_seconds' => $eta_seconds,
            'summary' => is_array($summary) ? $summary : array(),
            'artifacts' => $artifacts,
            'status_url' => $this->build_status_url($job['job_id']),
            'events_url' => $this->build_events_url($job['job_id']),
        );
        if ($download_url !== '') {
            $payload['download_url'] = $download_url;
        }
        return $payload;
    }

    private function build_status_url($job_id)
    {
        return admin_url('admin-ajax.php?action=mulopimfwc_ie_get_job_status&job_id=' . rawurlencode($job_id) . '&nonce=' . rawurlencode(wp_create_nonce('mulopimfwc_import_export_nonce')));
    }

    private function build_events_url($job_id)
    {
        return admin_url('admin-ajax.php?action=mulopimfwc_ie_get_job_events&job_id=' . rawurlencode($job_id) . '&cursor=0&nonce=' . rawurlencode(wp_create_nonce('mulopimfwc_import_export_nonce')));
    }

    private function schedule_worker($job_id, $delay_seconds = 0)
    {
        $delay_seconds = max(0, (int) $delay_seconds);
        if (function_exists('as_get_scheduled_actions') && function_exists('as_schedule_single_action')) {
            $pending = as_get_scheduled_actions(array(
                'hook' => self::ACTION_HOOK_PROCESS_JOB,
                'args' => array('job_id' => $job_id),
                'group' => self::ACTION_GROUP,
                'status' => 'pending',
                'per_page' => 1,
                'orderby' => 'date',
                'order' => 'ASC',
            ), 'ids');
            if (empty($pending)) {
                as_schedule_single_action(time() + $delay_seconds, self::ACTION_HOOK_PROCESS_JOB, array('job_id' => $job_id), self::ACTION_GROUP);
            }
            return;
        }
        if (function_exists('as_next_scheduled_action') && function_exists('as_schedule_single_action')) {
            $next = as_next_scheduled_action(self::ACTION_HOOK_PROCESS_JOB, array('job_id' => $job_id), self::ACTION_GROUP);
            if ($next === false) {
                as_schedule_single_action(time() + $delay_seconds, self::ACTION_HOOK_PROCESS_JOB, array('job_id' => $job_id), self::ACTION_GROUP);
            }
            return;
        }
        if (!wp_next_scheduled(self::ACTION_HOOK_PROCESS_JOB, array($job_id))) {
            wp_schedule_single_event(time() + $delay_seconds, self::ACTION_HOOK_PROCESS_JOB, array($job_id));
        }
    }

    private function assert_ajax_permission($capability)
    {
        check_ajax_referer('mulopimfwc_import_export_nonce', 'nonce');
        $capability = sanitize_key((string) $capability);
        if (!$this->current_user_can($capability)) {
            wp_send_json_error(array(
                'message' => __('You do not have permission for this action.', 'multi-location-product-and-inventory-management-pro'),
            ));
        }
    }

    private function current_user_can($capability)
    {
        if (current_user_can($capability)) {
            return true;
        }
        if (current_user_can('manage_woocommerce')) {
            return true;
        }
        return false;
    }

    private function can_access_job($job)
    {
        if (!is_array($job)) {
            return false;
        }
        if (current_user_can('manage_woocommerce')) {
            return true;
        }
        $user_id = get_current_user_id();
        return $user_id > 0 && isset($job['user_id']) && (int) $job['user_id'] === $user_id;
    }

    private function get_request_options()
    {
        $options = array();
        if (isset($_POST['options'])) {
            $raw = wp_unslash($_POST['options']);
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $options = $decoded;
                }
            } elseif (is_array($raw)) {
                $options = $raw;
            }
        }
        return is_array($options) ? $options : array();
    }

    private function memory_guard_reached()
    {
        $limit = ini_get('memory_limit');
        if ($limit === false || $limit === '' || $limit === '-1') {
            return false;
        }
        $limit_bytes = wp_convert_hr_to_bytes($limit);
        if ($limit_bytes <= 0) {
            return false;
        }
        $usage = memory_get_usage(true);
        return $usage >= ((float) $limit_bytes * self::MEMORY_GUARD_RATIO);
    }

    private function get_storage_root()
    {
        $uploads = wp_upload_dir(null, false);
        $base_dir = isset($uploads['basedir']) ? (string) $uploads['basedir'] : WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'uploads';
        return $base_dir . DIRECTORY_SEPARATOR . 'mulopimfwc-ie';
    }

    private function ensure_storage_root()
    {
        $root = $this->get_storage_root();
        $this->ensure_directory($root);
        $index_path = $root . DIRECTORY_SEPARATOR . 'index.html';
        if (!file_exists($index_path)) {
            $this->atomic_write($index_path, '');
        }
        $htaccess = $root . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccess)) {
            $this->atomic_write($htaccess, "Options -Indexes\n<Files *>\nDeny from all\n</Files>\n");
        }
    }

    private function get_job_dir($job_id)
    {
        $safe_job_id = preg_replace('/[^a-zA-Z0-9\-]/', '', (string) $job_id);
        $dir = $this->get_storage_root() . DIRECTORY_SEPARATOR . 'jobs' . DIRECTORY_SEPARATOR . $safe_job_id;
        $this->ensure_directory($dir);
        return $dir;
    }

    private function get_upload_chunks_dir($job_id, $upload_id)
    {
        $safe_upload_id = preg_replace('/[^a-zA-Z0-9\-]/', '', (string) $upload_id);
        $dir = $this->get_job_dir($job_id) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $safe_upload_id . DIRECTORY_SEPARATOR . 'chunks';
        return $dir;
    }

    private function ensure_directory($path)
    {
        if ($path === '') {
            return;
        }
        if (!is_dir($path)) {
            wp_mkdir_p($path);
        }
    }

    private function build_download_token($job_id, $artifact_id, $user_id, $expires)
    {
        $payload = implode('|', array(
            (string) $job_id,
            (int) $artifact_id,
            (int) $user_id,
            (int) $expires,
        ));
        return hash_hmac('sha256', $payload, wp_salt('auth'));
    }

    private function stream_file_with_range($path, $download_name)
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        $size = (int) filesize($path);
        $start = 0;
        $end = max(0, $size - 1);
        $length = $size;
        $status_code = 200;

        if (isset($_SERVER['HTTP_RANGE'])) {
            $range = trim((string) $_SERVER['HTTP_RANGE']);
            if (preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
                $range_start = $matches[1] !== '' ? (int) $matches[1] : null;
                $range_end = $matches[2] !== '' ? (int) $matches[2] : null;
                if ($range_start !== null) {
                    $start = max(0, $range_start);
                }
                if ($range_end !== null) {
                    $end = min($end, $range_end);
                }
                if ($start <= $end) {
                    $length = $end - $start + 1;
                    $status_code = 206;
                }
            }
        }

        status_header($status_code);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($download_name) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . $length);
        if ($status_code === 206) {
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        }
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $fp = fopen($path, 'rb');
        if (!$fp) {
            wp_die(esc_html__('Unable to open artifact file.', 'multi-location-product-and-inventory-management-pro'), 500);
        }
        fseek($fp, $start);
        $remaining = $length;
        while (!feof($fp) && $remaining > 0) {
            $chunk = fread($fp, min(1048576, $remaining));
            if ($chunk === false) {
                break;
            }
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw binary file streaming must not be escaped.
            echo $chunk;
            $remaining -= strlen($chunk);
            if (function_exists('fastcgi_finish_request')) {
                @flush();
            } else {
                @ob_flush();
                @flush();
            }
        }
        fclose($fp);
    }

    private function build_csv_line($headers, $row)
    {
        if (!is_resource($this->csv_line_stream)) {
            $this->csv_line_stream = fopen('php://temp', 'r+');
        }
        $stream = $this->csv_line_stream;
        ftruncate($stream, 0);
        rewind($stream);

        $line = array();
        foreach ((array) $headers as $header) {
            $line[] = isset($row[$header]) ? $row[$header] : '';
        }
        fputcsv($stream, $line);
        rewind($stream);
        return (string) stream_get_contents($stream);
    }

    private function atomic_write($path, $contents)
    {
        $dir = dirname($path);
        $this->ensure_directory($dir);
        $tmp = $path . '.tmp-' . wp_generate_password(8, false);
        file_put_contents($tmp, (string) $contents, LOCK_EX);
        @rename($tmp, $path);
    }

    private function decode_json($value, $default)
    {
        if (!is_string($value) || trim($value) === '') {
            return is_array($default) ? $default : array();
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : (is_array($default) ? $default : array());
    }

    private function encode_json($value)
    {
        $encoded = wp_json_encode($value);
        return is_string($encoded) ? $encoded : '[]';
    }

    private function get_job_retention_days()
    {
        $days = (int) apply_filters('mulopimfwc_ie_job_retention_days', self::JOB_RETENTION_DAYS);
        return max(1, $days);
    }

    private function get_artifact_retention_days()
    {
        $days = (int) apply_filters('mulopimfwc_ie_artifact_retention_days', self::ARTIFACT_RETENTION_DAYS);
        return max(1, $days);
    }

    private function get_log_retention_days()
    {
        $days = (int) apply_filters('mulopimfwc_ie_log_retention_days', self::LOG_RETENTION_DAYS);
        return max(1, $days);
    }

    private function get_worker_budget_seconds()
    {
        $seconds = (int) apply_filters('mulopimfwc_ie_worker_budget_seconds', self::WORKER_BUDGET_SECONDS);
        return max(5, $seconds);
    }

    private function get_export_batch_size()
    {
        $size = (int) apply_filters('mulopimfwc_ie_export_batch_size', self::EXPORT_BATCH_SIZE);
        return max(100, $size);
    }

    private function get_checkpoint_rows()
    {
        $rows = (int) apply_filters('mulopimfwc_ie_checkpoint_rows', self::CHECKPOINT_ROWS);
        return max(250, $rows);
    }

    private function get_checkpoint_seconds()
    {
        $seconds = (int) apply_filters('mulopimfwc_ie_checkpoint_seconds', self::CHECKPOINT_SECONDS);
        return max(1, $seconds);
    }

    private function get_max_import_rows()
    {
        $rows = (int) apply_filters('mulopimfwc_ie_max_import_rows', self::MAX_IMPORT_ROWS);
        return max(0, $rows);
    }

    private function now_gmt()
    {
        return gmdate('Y-m-d H:i:s');
    }

    private function gmt_to_iso($gmt)
    {
        $gmt = trim((string) $gmt);
        if ($gmt === '') {
            return '';
        }
        $ts = strtotime($gmt . ' UTC');
        if (!$ts) {
            return '';
        }
        return gmdate('c', $ts);
    }
}
