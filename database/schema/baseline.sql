CREATE TABLE "activity_daily_rollups" ("id" integer primary key autoincrement not null, "day" date not null, "log_name" varchar not null default '', "event" varchar not null default '', "count" integer not null default '0', "created_at" datetime, "updated_at" datetime);

CREATE TABLE "sharkweb_restore_runs" ("id" integer primary key autoincrement not null, "backup_path" varchar not null, "sql_file" varchar, "status" varchar not null, "total_statements" integer not null default '0', "processed_statements" integer not null default '0', "processed_bytes" integer not null default '0', "pending" text, "in_string" tinyint(1) not null default '0', "safety_backup" varchar, "error" text, "started_at" datetime, "finished_at" datetime, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "activity_log" ("id" integer primary key autoincrement not null, "log_name" varchar, "description" text not null, "subject_type" varchar, "subject_id" integer, "causer_type" varchar, "causer_id" integer, "properties" text, "created_at" datetime, "updated_at" datetime, "event" varchar, "batch_uuid" varchar);

CREATE TABLE "cache" ("key" varchar not null, "value" text not null, "expiration" integer not null, primary key ("key"));

CREATE TABLE "cache_locks" ("key" varchar not null, "owner" varchar not null, "expiration" integer not null, primary key ("key"));

CREATE TABLE "failed_jobs" ("id" integer primary key autoincrement not null, "uuid" varchar not null, "connection" text not null, "queue" text not null, "payload" text not null, "exception" text not null, "failed_at" datetime not null default CURRENT_TIMESTAMP);

CREATE TABLE "job_batches" ("id" varchar not null, "name" varchar not null, "total_jobs" integer not null, "pending_jobs" integer not null, "failed_jobs" integer not null, "failed_job_ids" text not null, "options" text, "cancelled_at" integer, "created_at" integer not null, "finished_at" integer, primary key ("id"));

CREATE TABLE "jobs" ("id" integer primary key autoincrement not null, "queue" varchar not null, "payload" text not null, "attempts" integer not null, "reserved_at" integer, "available_at" integer not null, "created_at" integer not null);

CREATE TABLE "leads" ("id" integer primary key autoincrement not null, "name" varchar not null, "email" varchar not null, "phone" varchar, "goal" varchar, "message" text, "ip_address" varchar, "source_page" varchar, "referrer_url" varchar, "utm_source" varchar, "utm_medium" varchar, "utm_campaign" varchar, "utm_term" varchar, "utm_content" varchar, "status" varchar check ("status" in ('new', 'contacted', 'qualified', 'closed', 'spam')) not null default 'new', "lead_score" varchar check ("lead_score" in ('hot', 'warm', 'cold')) not null default 'warm', "score_reasons" text, "internal_notes" text, "event_id" varchar, "created_at" datetime, "updated_at" datetime, "deleted_at" datetime);

CREATE TABLE "media" ("id" integer primary key autoincrement not null, "model_type" varchar not null, "model_id" integer not null, "uuid" varchar, "collection_name" varchar not null, "name" varchar not null, "file_name" varchar not null, "mime_type" varchar, "disk" varchar not null, "conversions_disk" varchar, "size" integer not null, "manipulations" text not null, "custom_properties" text not null, "generated_conversions" text not null, "responsive_images" text not null, "order_column" integer, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "newsletter_subscribers" ("id" integer primary key autoincrement not null, "email" varchar not null, "source_page" varchar, "is_verified" tinyint(1) not null default '0', "brevo_synced" tinyint(1) not null default '0', "subscribed_at" datetime, "unsubscribed_at" datetime, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "password_reset_tokens" ("email" varchar not null, "token" varchar not null, "created_at" datetime, primary key ("email"));

CREATE TABLE "sessions" ("id" varchar not null, "user_id" integer, "ip_address" varchar, "user_agent" text, "payload" text not null, "last_activity" integer not null, primary key ("id"));

CREATE TABLE "settings" ("id" integer primary key autoincrement not null, "group" varchar not null, "name" varchar not null, "locked" tinyint(1) not null default '0', "payload" text not null, "created_at" datetime, "updated_at" datetime);

CREATE TABLE "users" ("id" integer primary key autoincrement not null, "name" varchar not null, "email" varchar not null, "email_verified_at" datetime, "password" varchar not null, "remember_token" varchar, "created_at" datetime, "updated_at" datetime, "role" varchar not null default 'staff');

CREATE TABLE "webhook_logs" ("id" integer primary key autoincrement not null, "event" varchar not null, "payload" text, "response_status" integer, "response_body" text, "duration_ms" integer, "attempts" integer not null default '1', "created_at" datetime);

CREATE INDEX "sharkweb_restore_runs_status_index" on "sharkweb_restore_runs" ("status");

CREATE INDEX "activity_log_log_name_index" on "activity_log" ("log_name");

CREATE INDEX "cache_expiration_index" on "cache" ("expiration");

CREATE INDEX "cache_locks_expiration_index" on "cache_locks" ("expiration");

CREATE INDEX "causer" on "activity_log" ("causer_type", "causer_id");

CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs" ("uuid");

CREATE INDEX "jobs_queue_index" on "jobs" ("queue");

CREATE INDEX "leads_email_index" on "leads" ("email");

CREATE INDEX "leads_event_id_index" on "leads" ("event_id");

CREATE INDEX "leads_lead_score_created_at_index" on "leads" ("lead_score", "created_at");

CREATE INDEX "leads_status_created_at_index" on "leads" ("status", "created_at");

CREATE INDEX "leads_utm_source_index" on "leads" ("utm_source");

CREATE INDEX "media_model_type_model_id_index" on "media" ("model_type", "model_id");

CREATE INDEX "media_order_column_index" on "media" ("order_column");

CREATE UNIQUE INDEX "media_uuid_unique" on "media" ("uuid");

CREATE INDEX "newsletter_subscribers_brevo_synced_index" on "newsletter_subscribers" ("brevo_synced");

CREATE UNIQUE INDEX "newsletter_subscribers_email_unique" on "newsletter_subscribers" ("email");

CREATE INDEX "newsletter_subscribers_is_verified_index" on "newsletter_subscribers" ("is_verified");

CREATE INDEX "sessions_last_activity_index" on "sessions" ("last_activity");

CREATE INDEX "sessions_user_id_index" on "sessions" ("user_id");

CREATE UNIQUE INDEX "settings_group_name_unique" on "settings" ("group", "name");

CREATE INDEX "subject" on "activity_log" ("subject_type", "subject_id");

CREATE UNIQUE INDEX "users_email_unique" on "users" ("email");

CREATE INDEX "webhook_logs_event_created_at_index" on "webhook_logs" ("event", "created_at");

CREATE INDEX "webhook_logs_response_status_index" on "webhook_logs" ("response_status");

CREATE INDEX "users_role_index" on "users" ("role");
