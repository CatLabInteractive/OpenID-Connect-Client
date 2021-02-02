ALTER TABLE neuron_users
    ADD COLUMN `last_ping_at` timestamp NULL DEFAULT NULL,
    ADD COLUMN `created_at` timestamp NULL DEFAULT NULL,
    ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
