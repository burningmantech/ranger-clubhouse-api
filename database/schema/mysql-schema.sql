/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `access_document`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `access_document` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `person_id` int(11) NOT NULL,
  `type` enum('gift_ticket','lsd_ticket','special_price_ticket','staff_credential','vehicle_pass','vehicle_pass_gift','vehicle_pass_lsd','work_access_pass','work_access_pass_so') NOT NULL DEFAULT 'special_price_ticket',
  `status` enum('qualified','claimed','banked','used','cancelled','expired','submitted','turned_down') NOT NULL DEFAULT 'qualified',
  `source_year` int(4) DEFAULT NULL,
  `access_date` datetime DEFAULT NULL,
  `access_any_time` tinyint(1) DEFAULT 0,
  `name` text DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `create_date` datetime NOT NULL,
  `modified_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `delivery_method` enum('none','postal','will_call','email') NOT NULL DEFAULT 'none',
  `street1` varchar(191) NOT NULL DEFAULT '',
  `street2` varchar(191) NOT NULL DEFAULT '',
  `city` varchar(191) NOT NULL DEFAULT '',
  `state` varchar(191) NOT NULL DEFAULT '',
  `postal_code` varchar(191) NOT NULL DEFAULT '',
  `country` varchar(191) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `person_id` (`person_id`,`type`),
  KEY `access_document_type_status_index` (`type`,`status`),
  KEY `access_document_person_id_status_index` (`person_id`,`status`),
  KEY `access_document_person_id_source_year_index` (`person_id`,`source_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `access_document_changes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `access_document_changes` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `table_name` varchar(32) NOT NULL,
  `record_id` int(11) NOT NULL,
  `operation` enum('create','modify','delete') NOT NULL,
  `changes` text DEFAULT NULL,
  `changer_person_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `access_document_delivery`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `access_document_delivery` (
  `person_id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `method` enum('will_call','mail','none') NOT NULL DEFAULT 'none',
  `street` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(2) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `country` enum('United States','Canada') DEFAULT NULL,
  `modified_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`person_id`,`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `action_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `action_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(10) unsigned DEFAULT NULL,
  `target_person_id` int(10) unsigned DEFAULT NULL,
  `event` varchar(191) NOT NULL,
  `message` text NOT NULL,
  `data` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip` varchar(191) DEFAULT NULL,
  `user_agent` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `action_logs_person_id_created_at_index` (`person_id`,`created_at`),
  KEY `action_logs_target_person_id_created_at_index` (`target_person_id`,`created_at`),
  KEY `action_logs_person_id_event_created_at_index` (`person_id`,`event`,`created_at`),
  KEY `action_logs_event` (`event`),
  KEY `created_at_id_idx` (`created_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `alert`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alert` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `on_playa` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `alert_person`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alert_person` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `person_id` bigint(20) NOT NULL,
  `alert_id` bigint(20) NOT NULL,
  `use_sms` tinyint(1) NOT NULL DEFAULT 1,
  `use_email` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `person_id` (`person_id`,`alert_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `type` varchar(255) DEFAULT NULL,
  `barcode` varchar(25) NOT NULL,
  `description` varchar(25) DEFAULT NULL COMMENT 'placed by staff',
  `perm_assign` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'assigned "permanently" to a person?',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `category` varchar(25) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `year` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `asset_type_year_barcode_index` (`type`,`year`,`barcode`),
  KEY `asset_year_barcode_index` (`year`,`barcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_attachment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_attachment` (
  `id` bigint(20) unsigned NOT NULL,
  `parent_type` enum('Radio','Vehicle') NOT NULL,
  `description` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `asset_person`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_person` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint(20) unsigned NOT NULL,
  `asset_id` bigint(20) unsigned NOT NULL,
  `checked_out` datetime NOT NULL,
  `checked_in` datetime DEFAULT NULL,
  `attachment_id` bigint(20) unsigned DEFAULT NULL,
  `check_out_person_id` int(11) DEFAULT NULL,
  `check_in_person_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_id` (`person_id`),
  KEY `asset_id` (`asset_id`),
  KEY `asset_person_person_id_checked_out_index` (`person_id`,`checked_out`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `award`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `award` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `icon` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bmid`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bmid` (
  `person_id` int(11) NOT NULL,
  `status` enum('in_prep','do_not_print','ready_to_print','ready_to_reprint_lost','ready_to_reprint_changed','issues','submitted') DEFAULT NULL,
  `year` int(4) NOT NULL DEFAULT 0,
  `title1` varchar(64) DEFAULT NULL,
  `title2` varchar(64) DEFAULT NULL,
  `title3` varchar(64) DEFAULT NULL,
  `showers` tinyint(1) DEFAULT 0,
  `org_vehicle_insurance` tinyint(1) DEFAULT 0,
  `meals` enum('all','pre','post','event','pre+event','event+post','pre+post') DEFAULT NULL,
  `batch` varchar(128) DEFAULT NULL,
  `team` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `create_datetime` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_datetime` timestamp NOT NULL DEFAULT current_timestamp(),
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  UNIQUE KEY `person_id` (`person_id`,`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bmid_export`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bmid_export` (
  `filename` varchar(191) NOT NULL,
  `batch_info` varchar(191) NOT NULL,
  `person_ids` text NOT NULL,
  `person_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `broadcast`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `broadcast` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `sender_id` bigint(20) NOT NULL,
  `alert_id` bigint(2) NOT NULL,
  `sms_message` varchar(1600) DEFAULT NULL,
  `sender_address` varchar(255) DEFAULT NULL,
  `email_message` mediumtext DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `sent_sms` tinyint(1) NOT NULL DEFAULT 0,
  `sent_email` tinyint(1) NOT NULL DEFAULT 0,
  `sent_clubhouse` tinyint(1) NOT NULL DEFAULT 0,
  `recipient_count` int(11) NOT NULL DEFAULT 0,
  `sms_failed` int(11) NOT NULL DEFAULT 0,
  `email_failed` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `retry_at` datetime DEFAULT NULL,
  `retry_person_id` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `broadcast_message`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `broadcast_message` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `person_id` bigint(20) DEFAULT NULL,
  `broadcast_id` bigint(20) DEFAULT NULL,
  `direction` enum('inbound','outbound') DEFAULT 'outbound',
  `status` varchar(32) NOT NULL,
  `address_type` varchar(32) DEFAULT NULL,
  `address` varchar(255) NOT NULL,
  `message` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `person_id` (`person_id`),
  KEY `broadcast_id` (`broadcast_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(191) NOT NULL,
  `value` text NOT NULL,
  `expiration` int(11) NOT NULL,
  UNIQUE KEY `cache_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(191) NOT NULL,
  `owner` varchar(191) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `certification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certification` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `sl_title` varchar(255) DEFAULT NULL,
  `on_sl_report` tinyint(1) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_lifetime_certification` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `certification_on_sl_report_index` (`on_sl_report`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contact_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `sender_person_id` bigint(20) NOT NULL,
  `recipient_person_id` bigint(20) NOT NULL,
  `action` varchar(255) NOT NULL,
  `sent_at` datetime DEFAULT current_timestamp(),
  `recipient_address` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sender_person_id` (`sender_person_id`,`sent_at`),
  KEY `recipient_person_id` (`recipient_person_id`,`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tag` varchar(191) NOT NULL,
  `description` varchar(191) NOT NULL,
  `body` longtext NOT NULL,
  `person_create_id` int(11) NOT NULL,
  `person_update_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_tag_unique` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(11) NOT NULL,
  `source_person_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_history_person_id_index` (`person_id`),
  KEY `email_history_source_person_id_index` (`source_person_id`),
  KEY `email_history_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `error_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `error_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `error_type` varchar(191) NOT NULL,
  `url` text DEFAULT NULL,
  `person_id` int(10) unsigned DEFAULT NULL,
  `ip` varchar(191) DEFAULT NULL,
  `user_agent` varchar(191) DEFAULT NULL,
  `data` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `error_logs_person_id_created_at_index` (`person_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_dates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_dates` (
  `event_start` datetime DEFAULT NULL,
  `event_end` datetime DEFAULT NULL,
  `pre_event_start` datetime DEFAULT NULL,
  `post_event_end` datetime DEFAULT NULL,
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pre_event_slot_start` datetime DEFAULT NULL,
  `pre_event_slot_end` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `handle_reservation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `handle_reservation` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `handle` varchar(255) NOT NULL,
  `reservation_type` enum('brc_term','deceased_person','dismissed_person','radio_jargon','ranger_term','slur','twii_person','uncategorized') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `help`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `help` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(128) NOT NULL,
  `title` varchar(128) NOT NULL,
  `body` text NOT NULL,
  `tags` varchar(255) DEFAULT '',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `help_hit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `help_hit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `help_id` int(10) unsigned NOT NULL,
  `person_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `help_hit_help_id_created_at_index` (`help_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lambase_photo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lambase_photo` (
  `person_id` bigint(20) NOT NULL,
  `status` enum('approved','rejected','submitted','missing') NOT NULL DEFAULT 'missing',
  `lambase_image` varchar(191) NOT NULL DEFAULT '',
  `lambase_date` datetime DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mail_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mail_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(11) DEFAULT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `from_email` varchar(255) NOT NULL,
  `to_email` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message_id` varchar(255) NOT NULL,
  `did_bounce` tinyint(1) NOT NULL DEFAULT 0,
  `was_sent` tinyint(1) NOT NULL DEFAULT 0,
  `broadcast_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `body` longblob DEFAULT NULL,
  `did_complain` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `mail_log_person_id_created_at_index` (`person_id`,`created_at`),
  KEY `mail_log_sender_id_created_at_index` (`sender_id`,`created_at`),
  KEY `mail_log_message_id_index` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `manual_review`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `manual_review` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `person_id` int(11) NOT NULL,
  `passdate` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mentee_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mentee_status` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mentor_year` int(4) unsigned NOT NULL,
  `person_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `rank` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mentor_year` (`mentor_year`,`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `motd`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `motd` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `message` text NOT NULL,
  `person_id` bigint(20) unsigned NOT NULL,
  `is_alert` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `for_rangers` tinyint(1) NOT NULL DEFAULT 0,
  `for_pnvs` tinyint(1) NOT NULL DEFAULT 0,
  `for_auditors` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `subject` varchar(191) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `motd_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(25) NOT NULL,
  `mi` varchar(10) NOT NULL DEFAULT '',
  `last_name` varchar(25) NOT NULL,
  `callsign` varchar(64) NOT NULL,
  `street1` varchar(128) NOT NULL DEFAULT '',
  `street2` varchar(128) NOT NULL DEFAULT '',
  `apt` varchar(10) NOT NULL DEFAULT '',
  `city` varchar(50) NOT NULL DEFAULT '',
  `state` varchar(128) NOT NULL DEFAULT '',
  `zip` varchar(10) NOT NULL DEFAULT '',
  `country` varchar(25) NOT NULL DEFAULT '',
  `home_phone` varchar(25) NOT NULL DEFAULT '',
  `alt_phone` varchar(25) NOT NULL DEFAULT '',
  `email` varchar(50) DEFAULT NULL,
  `camp_location` varchar(200) NOT NULL DEFAULT '',
  `on_site` tinyint(1) NOT NULL DEFAULT 0,
  `vehicle_blacklisted` tinyint(1) NOT NULL DEFAULT 0,
  `date_verified` date DEFAULT NULL,
  `password` varchar(64) DEFAULT NULL,
  `create_date` datetime DEFAULT current_timestamp(),
  `has_note_on_file` tinyint(1) NOT NULL DEFAULT 0,
  `callsign_approved` tinyint(1) NOT NULL DEFAULT 0,
  `shirt_size` varchar(10) DEFAULT NULL,
  `status` enum('prospective','prospective waitlist','past prospective','alpha','bonked','active','inactive','inactive extension','retired','uberbonked','dismissed','resigned','deceased','auditor','non ranger','suspended') NOT NULL DEFAULT 'prospective',
  `status_date` date DEFAULT NULL,
  `tpassword` varchar(64) DEFAULT NULL,
  `tpassword_expire` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Most recent modification time.',
  `shirt_style` varchar(32) DEFAULT NULL,
  `gender_custom` varchar(32) DEFAULT NULL,
  `bpguid` varchar(64) DEFAULT NULL,
  `sfuid` varchar(64) DEFAULT NULL,
  `emergency_contact` mediumtext DEFAULT NULL,
  `formerly_known_as` varchar(200) DEFAULT NULL,
  `active_next_event` tinyint(1) NOT NULL DEFAULT 0,
  `vintage` tinyint(1) DEFAULT 0,
  `sms_off_playa` varchar(255) DEFAULT NULL,
  `sms_on_playa` varchar(255) DEFAULT NULL,
  `sms_off_playa_stopped` tinyint(1) NOT NULL DEFAULT 0,
  `sms_on_playa_stopped` tinyint(1) NOT NULL DEFAULT 0,
  `sms_off_playa_verified` tinyint(1) NOT NULL DEFAULT 0,
  `sms_on_playa_verified` tinyint(1) NOT NULL DEFAULT 0,
  `sms_off_playa_code` varchar(16) DEFAULT NULL,
  `sms_on_playa_code` varchar(16) DEFAULT NULL,
  `timesheet_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `timesheet_confirmed_at` datetime DEFAULT NULL,
  `callsign_normalized` varchar(128) NOT NULL,
  `callsign_soundex` varchar(128) NOT NULL,
  `message` mediumtext DEFAULT NULL,
  `message_updated_at` datetime DEFAULT NULL,
  `behavioral_agreement` tinyint(1) NOT NULL DEFAULT 0,
  `callsign_pronounce` varchar(200) DEFAULT NULL,
  `logged_in_at` timestamp NULL DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `person_photo_id` bigint(20) DEFAULT NULL,
  `known_rangers` text DEFAULT NULL,
  `known_pnvs` text DEFAULT NULL,
  `lms_id` varchar(191) DEFAULT NULL,
  `reviewed_pi_at` datetime DEFAULT NULL,
  `pronouns` enum('','female','male','neutral','custom') NOT NULL DEFAULT '',
  `pronouns_custom` varchar(191) NOT NULL,
  `pi_reviewed_for_dashboard_at` datetime DEFAULT NULL,
  `lms_username` varchar(191) DEFAULT NULL,
  `is_bouncing` tinyint(1) NOT NULL DEFAULT 0,
  `tshirt_swag_id` int(11) DEFAULT NULL,
  `tshirt_secondary_swag_id` int(11) DEFAULT NULL,
  `long_sleeve_swag_ig` int(11) DEFAULT NULL,
  `vanity_changed_at` datetime DEFAULT NULL,
  `used_vanity_change` tinyint(1) NOT NULL DEFAULT 0,
  `employee_id` varchar(255) DEFAULT NULL,
  `gender_identity` enum('cis-female','cis-male','custom','female','fluid','male','','non-binary','queer','trans-female','trans-male','two-spirit') NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `callsign` (`callsign`),
  UNIQUE KEY `email` (`email`),
  KEY `person_callsign_normalized_index` (`callsign_normalized`),
  KEY `person_callsign_soundex_index` (`callsign_soundex`),
  KEY `person_lms_id_index` (`lms_id`),
  KEY `person_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_award`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_award` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(11) NOT NULL,
  `award_id` int(11) NOT NULL,
  `notes` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_award_person_id_award_id_index` (`person_id`,`award_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_certification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_certification` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(11) NOT NULL,
  `recorder_id` int(11) DEFAULT NULL,
  `certification_id` int(11) NOT NULL,
  `issued_on` date DEFAULT NULL,
  `trained_on` date DEFAULT NULL,
  `card_number` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_certification_person_id_index` (`person_id`),
  KEY `person_certification_certification_id_index` (`certification_id`),
  KEY `person_certification_person_id_certification_id_index` (`person_id`,`certification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_event` (
  `person_id` bigint(20) NOT NULL,
  `year` int(11) NOT NULL,
  `may_request_stickers` tinyint(1) NOT NULL DEFAULT 1,
  `org_vehicle_insurance` tinyint(1) NOT NULL DEFAULT 0,
  `signed_motorpool_agreement` tinyint(1) NOT NULL DEFAULT 0,
  `signed_personal_vehicle_agreement` tinyint(1) NOT NULL DEFAULT 0,
  `asset_authorized` tinyint(1) NOT NULL DEFAULT 0,
  `timesheet_confirmed_at` datetime DEFAULT NULL,
  `timesheet_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `sandman_affidavit` tinyint(1) NOT NULL DEFAULT 0,
  `signed_nda` tinyint(1) NOT NULL DEFAULT 0,
  `ticketing_started_at` datetime DEFAULT NULL,
  `ticketing_last_visited_at` datetime DEFAULT NULL,
  `ticketing_finished_at` datetime DEFAULT NULL,
  `pii_started_at` datetime DEFAULT NULL,
  `pii_finished_at` datetime DEFAULT NULL,
  `lms_course_id` varchar(255) DEFAULT NULL,
  `lms_enrolled_at` datetime DEFAULT NULL,
  UNIQUE KEY `person_event_year_person_id_unique` (`year`,`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_intake`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_intake` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint(20) NOT NULL,
  `year` int(11) NOT NULL,
  `mentor_rank` int(11) DEFAULT NULL,
  `rrn_rank` int(11) DEFAULT NULL,
  `vc_rank` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `personnel_rank` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `person_intake_person_id_year_unique` (`person_id`,`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_intake_note`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_intake_note` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint(20) NOT NULL,
  `person_source_id` bigint(20) NOT NULL,
  `year` int(11) NOT NULL,
  `is_log` tinyint(1) NOT NULL DEFAULT 0,
  `type` enum('mentor','rrn','personnel','vc') NOT NULL,
  `note` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_intake_note_person_id_year_index` (`person_id`,`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_language`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_language` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `person_id` int(11) NOT NULL,
  `language_name` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `person_id` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_mentor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_mentor` (
  `person_id` bigint(20) unsigned NOT NULL,
  `mentor_id` bigint(20) unsigned NOT NULL,
  `mentor_year` int(4) unsigned NOT NULL,
  `status` enum('pass','bonk','self-bonk','pending') DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  UNIQUE KEY `person_id` (`person_id`,`mentor_id`,`mentor_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_message`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_message` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `person_id` int(11) NOT NULL,
  `creator_person_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `subject` varchar(255) NOT NULL DEFAULT '',
  `body` mediumtext NOT NULL,
  `delivered` tinyint(1) NOT NULL DEFAULT 0,
  `message_from` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_message_2012`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_message_2012` (
  `id` int(11) NOT NULL DEFAULT 0,
  `person_id` int(11) NOT NULL,
  `creator_person_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `subject` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `body` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `delivered` tinyint(1) NOT NULL DEFAULT 0,
  `message_from` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_message_2013`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_message_2013` (
  `id` int(11) NOT NULL DEFAULT 0,
  `person_id` int(11) NOT NULL,
  `creator_person_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `subject` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `body` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `delivered` tinyint(1) NOT NULL DEFAULT 0,
  `message_from` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_message_2014`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_message_2014` (
  `id` int(11) NOT NULL DEFAULT 0,
  `person_id` int(11) NOT NULL,
  `creator_person_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `subject` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `body` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `delivered` tinyint(1) NOT NULL DEFAULT 0,
  `message_from` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_message_2015`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_message_2015` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `person_id` int(11) NOT NULL,
  `creator_person_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `subject` varchar(255) NOT NULL DEFAULT '',
  `body` text NOT NULL,
  `delivered` tinyint(1) NOT NULL DEFAULT 0,
  `message_from` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_message_2016`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_message_2016` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `person_id` int(11) NOT NULL,
  `creator_person_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `subject` varchar(255) NOT NULL DEFAULT '',
  `body` text NOT NULL,
  `delivered` tinyint(1) NOT NULL DEFAULT 0,
  `message_from` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_message_2017`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_message_2017` (
  `id` int(11) NOT NULL DEFAULT 0,
  `person_id` int(11) NOT NULL,
  `creator_person_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `subject` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `body` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `delivered` tinyint(1) NOT NULL DEFAULT 0,
  `message_from` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_message_2018`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_message_2018` (
  `id` int(11) NOT NULL DEFAULT 0,
  `person_id` int(11) NOT NULL,
  `creator_person_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `subject` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `body` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `delivered` tinyint(1) NOT NULL DEFAULT 0,
  `message_from` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_message_2019`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_message_2019` (
  `id` int(11) NOT NULL DEFAULT 0,
  `person_id` int(11) NOT NULL,
  `creator_person_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `body` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `delivered` tinyint(1) NOT NULL DEFAULT 0,
  `message_from` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_message_2022`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_message_2022` (
  `id` int(11) NOT NULL DEFAULT 0,
  `person_id` int(11) NOT NULL,
  `creator_person_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `body` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `delivered` tinyint(1) NOT NULL DEFAULT 0,
  `message_from` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_motd`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_motd` (
  `person_id` bigint(20) NOT NULL,
  `motd_id` bigint(20) NOT NULL,
  `read_at` datetime NOT NULL,
  KEY `person_motd_person_id_motd_id_index` (`person_id`,`motd_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_online_training`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_online_training` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint(20) NOT NULL,
  `completed_at` datetime NOT NULL,
  `type` varchar(191) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `person_online_training_person_id_completed_at_index` (`person_id`,`completed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_photo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_photo` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `person_id` bigint(20) NOT NULL,
  `status` enum('approved','rejected','submitted') NOT NULL DEFAULT 'submitted',
  `image_filename` varchar(191) NOT NULL,
  `width` int(11) NOT NULL DEFAULT 0,
  `height` int(11) NOT NULL DEFAULT 0,
  `orig_filename` varchar(191) NOT NULL,
  `orig_width` int(11) NOT NULL DEFAULT 0,
  `orig_height` int(11) NOT NULL DEFAULT 0,
  `reject_reasons` text DEFAULT NULL,
  `reject_message` longtext DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_person_id` bigint(20) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT NULL,
  `upload_person_id` bigint(20) DEFAULT NULL,
  `edited_at` datetime DEFAULT NULL,
  `edit_person_id` bigint(20) DEFAULT NULL,
  `analysis_info` longtext DEFAULT NULL,
  `analysis_status` enum('success','failed','none') NOT NULL DEFAULT 'none',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `profile_filename` varchar(255) DEFAULT NULL,
  `profile_width` int(11) NOT NULL DEFAULT 0,
  `profile_height` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `person_photo_person_id_index` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_pod`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_pod` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `pod_id` int(11) NOT NULL,
  `person_id` int(11) NOT NULL,
  `is_lead` tinyint(1) NOT NULL DEFAULT 0,
  `joined_at` datetime NOT NULL,
  `left_at` datetime DEFAULT NULL,
  `removed_at` datetime DEFAULT NULL,
  `sort_index` int(11) NOT NULL DEFAULT 0,
  `timesheet_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_pod_pod_id_person_id_index` (`pod_id`,`person_id`),
  KEY `person_pod_timesheet_id_index` (`timesheet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_pog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_pog` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `person_id` int(11) NOT NULL,
  `issued_by_id` int(11) NOT NULL,
  `timesheet_id` int(11) DEFAULT NULL,
  `pog` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `notes` text NOT NULL,
  `issued_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `person_pog_person_id_pog_created_at_index` (`person_id`,`pog`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_position`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_position` (
  `person_id` bigint(20) unsigned NOT NULL,
  `position_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`person_id`,`position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='links person to position';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_position_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_position_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `position_id` int(11) NOT NULL,
  `person_id` int(11) NOT NULL,
  `joined_on` date NOT NULL,
  `left_on` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_role` (
  `person_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  UNIQUE KEY `person_id_and_role_id` (`person_id`,`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='links person to role';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_slot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_slot` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint(20) unsigned NOT NULL,
  `slot_id` bigint(20) unsigned NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `person_slot_slot_id_person_id` (`slot_id`,`person_id`),
  KEY `slot_id` (`slot_id`),
  KEY `person_id` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_status` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint(20) NOT NULL,
  `person_source_id` bigint(20) DEFAULT NULL,
  `new_status` varchar(191) NOT NULL,
  `old_status` varchar(191) NOT NULL,
  `reason` varchar(191) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `person_status_person_id_index` (`person_id`),
  KEY `person_status_person_source_id_index` (`person_source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_swag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_swag` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(11) NOT NULL,
  `swag_id` int(11) NOT NULL,
  `year_issued` int(11) DEFAULT NULL,
  `notes` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_swag_person_id_swag_id_index` (`person_id`,`swag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_team`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_team` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `is_manager` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `person_team_person_id_team_id_unique` (`person_id`,`team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_team_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `person_team_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `person_id` int(11) NOT NULL,
  `joined_on` date NOT NULL,
  `left_on` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pod`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pod` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type` enum('alpha','mentor','mitten','shift') NOT NULL,
  `formed_at` datetime NOT NULL,
  `disbanded_at` datetime DEFAULT NULL,
  `slot_id` int(11) DEFAULT NULL,
  `mentor_pod_id` int(11) DEFAULT NULL,
  `person_count` int(11) NOT NULL DEFAULT 0,
  `sort_index` int(11) NOT NULL DEFAULT 0,
  `location` varchar(255) DEFAULT NULL,
  `transport` enum('foot','bicycle','vehicle') NOT NULL DEFAULT 'foot',
  PRIMARY KEY (`id`),
  KEY `pod_slot_id_index` (`slot_id`),
  KEY `pod_slot_id_formed_at_index` (`slot_id`,`formed_at`),
  KEY `pod_formed_at_index` (`formed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `position`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `position` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(40) NOT NULL,
  `new_user_eligible` tinyint(1) NOT NULL DEFAULT 0,
  `all_rangers` tinyint(1) NOT NULL DEFAULT 0,
  `count_hours` tinyint(1) NOT NULL DEFAULT 1,
  `min` int(10) NOT NULL DEFAULT 1 COMMENT 'Min suggested Rangers per slot',
  `max` int(10) DEFAULT NULL COMMENT 'Max suggested Rangers per slot',
  `on_sl_report` tinyint(1) DEFAULT NULL,
  `on_trainer_report` tinyint(1) NOT NULL DEFAULT 0,
  `short_title` varchar(6) DEFAULT NULL,
  `type` varchar(32) DEFAULT NULL,
  `training_position_id` bigint(20) unsigned DEFAULT NULL,
  `contact_email` varchar(200) DEFAULT NULL,
  `prevent_multiple_enrollments` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `alert_when_empty` tinyint(1) NOT NULL DEFAULT 0,
  `team_id` int(11) DEFAULT NULL,
  `require_training_for_roles` tinyint(1) NOT NULL DEFAULT 0,
  `team_category` enum('public','all_members','optional') NOT NULL DEFAULT 'public',
  `alert_when_becomes_empty` tinyint(1) NOT NULL DEFAULT 0,
  `alert_when_no_trainers` tinyint(1) NOT NULL DEFAULT 0,
  `paycode` varchar(255) DEFAULT NULL,
  `resource_tag` varchar(255) DEFAULT NULL,
  `deselect_on_team_join` tinyint(1) NOT NULL DEFAULT 0,
  `no_payroll_hours_adjustment` tinyint(1) NOT NULL DEFAULT 0,
  `no_training_required` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `position_credit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `position_credit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `position_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `credits_per_hour` decimal(4,2) NOT NULL,
  `description` varchar(25) NOT NULL,
  `start_year` int(11) GENERATED ALWAYS AS (year(`start_time`)) STORED,
  `end_year` int(11) GENERATED ALWAYS AS (year(`end_time`)) STORED,
  PRIMARY KEY (`id`),
  KEY `position_credit_position_id_start_time_index` (`position_id`,`start_time`),
  KEY `position_credit_start_year_index` (`start_year`),
  KEY `position_credit_start_year_end_year_index` (`start_year`,`end_year`),
  KEY `position_credit_start_year_position_id_index` (`start_year`,`position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `position_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `position_role` (
  `position_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  UNIQUE KEY `position_role_position_id_role_id_unique` (`position_id`,`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `provision`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `provision` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(11) NOT NULL,
  `source_year` int(11) NOT NULL,
  `type` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'available',
  `expires_on` date NOT NULL,
  `comments` longtext DEFAULT NULL,
  `is_allocated` tinyint(1) NOT NULL DEFAULT 0,
  `item_count` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `consumed_year` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `radio_eligible`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `radio_eligible` (
  `person_id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `max_radios` int(4) NOT NULL,
  PRIMARY KEY (`person_id`,`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(25) NOT NULL,
  `new_user_eligible` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci COMMENT='authorization roles';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `setting` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `slot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `slot` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `begins` datetime NOT NULL,
  `ends` datetime NOT NULL,
  `position_id` bigint(20) unsigned NOT NULL,
  `description` varchar(40) DEFAULT NULL COMMENT '("day", "night", etc.)',
  `signed_up` bigint(20) unsigned NOT NULL DEFAULT 0,
  `max` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'if trainee slot, this is * slot[trainer_slot_id].signed_up',
  `url` varchar(512) DEFAULT NULL,
  `trainer_slot_id` bigint(20) unsigned DEFAULT NULL COMMENT 'if trainee slot, slot.id for corresponding trainer slot, else null',
  `trainee_slot_id` bigint(20) unsigned DEFAULT NULL COMMENT 'if trainer slot, slot.id for corresponding trainee slot, else null',
  `training_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `min` int(10) NOT NULL DEFAULT 1,
  `active` tinyint(1) NOT NULL DEFAULT 0,
  `timezone` varchar(255) NOT NULL DEFAULT 'America/Los_Angeles',
  `timezone_abbr` varchar(255) NOT NULL,
  `begins_year` int(11) NOT NULL DEFAULT 0,
  `duration` int(11) NOT NULL DEFAULT 0,
  `begins_time` int(11) NOT NULL DEFAULT 0,
  `ends_time` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `begins` (`begins`),
  KEY `position_id` (`position_id`),
  KEY `slot_begins_year_position_id_index` (`begins_year`,`position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='schedule slot';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `survey`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `survey` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `year` int(11) NOT NULL,
  `type` enum('trainer','training','slot') NOT NULL DEFAULT 'trainer',
  `position_id` int(11) DEFAULT NULL,
  `title` varchar(191) NOT NULL,
  `prologue` text NOT NULL,
  `epilogue` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `survey_answer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `survey_answer` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint(20) NOT NULL,
  `callsign` varchar(191) DEFAULT NULL,
  `survey_id` int(11) NOT NULL,
  `survey_question_id` int(11) NOT NULL,
  `survey_group_id` int(11) NOT NULL,
  `response` text NOT NULL,
  `slot_id` bigint(20) DEFAULT NULL,
  `trainer_id` bigint(20) DEFAULT NULL,
  `can_share_name` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `survey_answer_trainer_id_index` (`trainer_id`),
  KEY `survey_answer_slot_id_index` (`slot_id`),
  KEY `survey_answer_person_id_index` (`person_id`),
  KEY `survey_answer_survey_id_index` (`survey_id`),
  KEY `survey_answer_survey_question_id_index` (`survey_question_id`),
  KEY `survey_answer_survey_group_id_index` (`survey_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `survey_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `survey_group` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `survey_id` int(11) NOT NULL,
  `sort_index` int(11) NOT NULL DEFAULT 1,
  `title` varchar(191) NOT NULL,
  `description` text NOT NULL,
  `is_trainer_group` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type` varchar(191) NOT NULL DEFAULT 'normal',
  `report_title` varchar(191) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `survey_group_survey_id_sort_index_index` (`survey_id`,`sort_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `survey_question`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `survey_question` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `survey_id` int(11) NOT NULL,
  `survey_group_id` int(11) NOT NULL,
  `sort_index` int(11) NOT NULL DEFAULT 1,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `options` text NOT NULL,
  `description` text NOT NULL,
  `type` enum('rating','options','text') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `summarize_rating` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `survey_question_survey_id_index` (`survey_id`),
  KEY `survey_question_survey_group_id_sort_index_index` (`survey_group_id`,`sort_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `swag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `swag` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL,
  `shirt_type` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `team` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'team',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team_manager`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `team_manager` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `team_role` (
  `team_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  UNIQUE KEY `team_role_team_id_role_id_unique` (`team_id`,`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `timesheet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timesheet` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `position_id` bigint(20) unsigned NOT NULL,
  `person_id` bigint(20) unsigned NOT NULL,
  `on_duty` datetime NOT NULL COMMENT 'date/time that person signed onto duty',
  `off_duty` datetime DEFAULT NULL COMMENT 'date/time that person signed off of duty',
  `verified_at` datetime DEFAULT NULL,
  `verified_person_id` bigint(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `review_status` enum('approved','rejected','pending','unverified','verified') DEFAULT 'unverified',
  `reviewer_person_id` bigint(20) DEFAULT NULL,
  `reviewer_notes` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `slot_id` bigint(20) DEFAULT NULL,
  `is_non_ranger` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `position_id` (`position_id`),
  KEY `person_id` (`person_id`),
  KEY `verified` (`review_status`),
  KEY `timesheet_slot_id` (`slot_id`),
  KEY `timesheet_position_id_on_duty_index` (`position_id`,`on_duty`),
  KEY `timesheet_person_id_on_duty_index` (`person_id`,`on_duty`),
  KEY `timesheet_person_id_position_id_index` (`person_id`,`position_id`),
  KEY `timesheet_on_duty_index` (`on_duty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `timesheet_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timesheet_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `person_id` bigint(20) NOT NULL,
  `create_person_id` bigint(20) NOT NULL,
  `timesheet_id` bigint(20) DEFAULT NULL,
  `action` enum('unconfirmed','confirmed','signon','signoff','created','update','delete','review','verify','unverified') NOT NULL,
  `message` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `year` int(11) NOT NULL DEFAULT 0,
  `data` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_id` (`person_id`,`action`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `timesheet_missing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timesheet_missing` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `person_id` bigint(20) NOT NULL,
  `create_person_id` bigint(20) NOT NULL,
  `position_id` bigint(20) NOT NULL,
  `on_duty` datetime NOT NULL,
  `off_duty` datetime NOT NULL,
  `partner` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `notes` text DEFAULT NULL,
  `review_status` enum('approved','rejected','pending') DEFAULT 'pending',
  `reviewer_person_id` bigint(20) DEFAULT NULL,
  `reviewer_notes` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `person_id` (`person_id`,`review_status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `trainee_note`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trainee_note` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int(11) NOT NULL,
  `person_source_id` int(11) DEFAULT NULL,
  `slot_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `is_log` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `trainee_note_person_id_slot_id_index` (`person_id`,`slot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `trainee_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trainee_status` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slot_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `person_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `passed` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `rank` tinyint(1) unsigned DEFAULT NULL,
  `feedback_delivered` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `person_id` (`person_id`),
  KEY `slot_id` (`slot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `trainer_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trainer_status` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint(20) unsigned NOT NULL,
  `trainer_slot_id` bigint(20) unsigned NOT NULL,
  `slot_id` bigint(20) unsigned NOT NULL,
  `status` enum('attended','no-show','pending') NOT NULL DEFAULT 'attended',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_lead` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `person_id_and_slot_id` (`person_id`,`slot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vehicle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `person_id` bigint(20) DEFAULT NULL,
  `type` enum('personal','fleet') NOT NULL,
  `event_year` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `vehicle_year` varchar(191) NOT NULL DEFAULT '',
  `vehicle_class` varchar(191) NOT NULL DEFAULT '',
  `vehicle_make` varchar(191) NOT NULL DEFAULT '',
  `vehicle_model` varchar(191) NOT NULL DEFAULT '',
  `vehicle_color` varchar(191) NOT NULL DEFAULT '',
  `vehicle_type` varchar(191) NOT NULL DEFAULT '',
  `license_state` varchar(191) NOT NULL DEFAULT '',
  `license_number` varchar(191) NOT NULL DEFAULT '',
  `rental_number` varchar(191) NOT NULL DEFAULT '',
  `driving_sticker` enum('none','prepost','staff','other') NOT NULL DEFAULT 'none',
  `sticker_number` varchar(191) NOT NULL DEFAULT '',
  `fuel_chit` enum('event','single-use','none') NOT NULL DEFAULT 'none',
  `ranger_logo` enum('permanent-new','permanent-existing','event','none') NOT NULL DEFAULT 'none',
  `amber_light` enum('department','already-has','none') NOT NULL DEFAULT 'none',
  `team_assignment` text NOT NULL,
  `notes` text NOT NULL,
  `response` text NOT NULL,
  `request_comment` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vehicle_event_year_index` (`event_year`),
  KEY `vehicle_person_id_index` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2018_01_01_154333_create_access_document_changes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2018_01_01_154333_create_access_document_delivery_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2018_01_01_154333_create_access_document_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2018_01_01_154333_create_alert_person_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2018_01_01_154333_create_alert_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2018_01_01_154333_create_asset_attachment_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2018_01_01_154333_create_asset_person_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2018_01_01_154333_create_asset_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2018_01_01_154333_create_bmid_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2018_01_01_154333_create_broadcast_message_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2018_01_01_154333_create_broadcast_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2018_01_01_154333_create_contact_log_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2018_01_01_154333_create_early_arrival_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2018_01_01_154333_create_event_dates_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2018_01_01_154333_create_feedback_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2018_01_01_154333_create_log_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2018_01_01_154333_create_manual_review_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2018_01_01_154333_create_mentee_status_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2018_01_01_154333_create_person_language_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2018_01_01_154333_create_person_mentor_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2018_01_01_154333_create_person_message_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2018_01_01_154333_create_person_position_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2018_01_01_154333_create_person_role_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2018_01_01_154333_create_person_slot_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2018_01_01_154333_create_person_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2018_01_01_154333_create_person_xfield_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2018_01_01_154333_create_position_credit_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2018_01_01_154333_create_position_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2018_01_01_154333_create_radio_eligible_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2018_01_01_154333_create_role_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2018_01_01_154333_create_sessions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2018_01_01_154333_create_slot_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2018_01_01_154333_create_ticket_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2018_01_01_154333_create_timesheet_log_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2018_01_01_154333_create_timesheet_missing_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2018_01_01_154333_create_timesheet_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2018_01_01_154333_create_trainee_status_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2018_01_01_154333_create_training_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2018_01_01_154333_create_xfield_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2018_01_01_154333_create_xgroup_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2018_01_01_154333_create_xoption_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2019_01_01_102126_create_action_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2019_01_19_092840_create_error_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2019_02_28_123922_create_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2019_03_08_100500_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2019_03_08_102057_create_failed_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2019_03_09_172301_increase_state_size',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2019_03_09_172301_increase_state_size',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2019_03_10_160311_normalize_country_state',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2019_03_10_160311_normalize_country_state',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2019_03_10_160311_normalize_country_state',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2019_03_29_140904_add_pre_event_slot_dates_to_event_dates',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2019_04_04_100406_increase_slot_url_size',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2019_04_08_195447_increase_slot_description_and_position_title',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2019_04_08_195447_increase_slot_description_and_position_title',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2019_04_10_222214_add_callsign_normalized_to_person',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2019_04_01_170636_fix_bmid_schema_migration',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2019_04_02_102747_add_indexes_to_access_document_migration',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2019_04_02_103034_add_indexes_to_trainee_status',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2019_04_13_172044_create_motd',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2019_04_13_190543_add_osha_to_person',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2019_04_13_191303_add_message_to_person',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2019_04_19_122739_add_behavior_flag_to_person',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2019_04_24_111128_create_help_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2019_05_16_211819_create_person_photo',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2019_05_20_163914_add_id_to_person_mentor',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2019_06_16_153827_add_sandman_affidavit',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2019_06_24_220101_switch_soundex_to_metaphone',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2019_06_24_220101_switch_soundex_to_metaphone',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2019_06_24_220101_switch_soundex_to_metaphone',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2019_06_24_220101_switch_soundex_to_metaphone',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2019_07_31_174227_create_help_hit_table_migration',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2019_08_06_113056_allow_emojis_migration',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2019_08_06_113056_allow_emojis_migration',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2019_08_06_175703_fixcollation',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2019_10_27_142743_cleanup_legacy_schema_migration',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2019_10_30_114058_remove_setting_columns',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2019_11_10_202334_create_trainer_status_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2019_11_18_104922_add_slot_id_to_timesheet',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2019_12_12_090508_add_pronounce_to_person',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2019_12_15_102855_add_fields_to_position',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2019_12_20_160318_add_milestones_to_person',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2020_01_11_162151_clubhouse_photo',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2020_02_02_091228_create_person_status_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2020_02_09_165557_add_types_to_motd',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2020_02_10_150001_create_person_intake',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2020_02_11_111332_add_intake_role',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2020_02_20_165528_create_trainee_note',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2020_03_07_143821_create_cache_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2020_03_07_165445_create_task_log_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2020_03_03_165913_create_person_ot',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2020_03_04_202505_add_lms_id_to_person',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2020_03_11_082010_add_personnel_to_notes',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2020_03_13_134847_add_lms_course_expiry_at_to_person',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2020_03_19_161147_rename_slot_full_email',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2020_03_31_090614_create_survey_tables',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2020_05_10_092109_create_cache_locks',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2020_05_10_093554_drop_task_log',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2020_05_11_123730_drop_user_authorized_column',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2020_05_19_153417_drop_mentor_columns',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2020_05_15_121654_create_vehicle',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2020_06_05_215405_create_person_event_table',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2020_07_24_155418_enhance_motd',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2020_07_24_155438_create_person_motd_table',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2020_07_29_134241_convert_ts_confirmation_to_person_event',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2020_08_16_101204_add_reviewed_pi_at_to_person',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2020_09_12_134959_create_document_table',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2020_09_15_154301_drop_auto_signout',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2020_09_17_143217_add_active_flag_to_position',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2020_10_26_150235_convert_timesheets',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2020_11_27_215720_add_pronouns_to_person',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2020_12_28_162128_add_ip_and_user_agent_to_action_logs',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2020_12_29_122848_add_lm_on_playa_to_roles',46);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2021_01_28_153101_add_moodle_to_person_online_training',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2021_01_30_184959_add_person_status_to_timesheet',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2021_02_20_173303_adjust_access_documents',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2021_03_14_163615_remetaphone_numeric_callsigns',50);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2021_03_16_124400_add_subreport_to_survey_group',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2021_03_26_214244_adjust_access_document_types',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2021_04_02_110550_add_bmid_export_table',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2021_05_06_100933_add_address_to_access_document',53);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2021_08_05_133147_add_indexes_to_tables',54);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2021_12_08_180207_add_trainer_seasonal_role',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2022_03_14_081537_add_pi_dashboard_review_to_person',56);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2022_03_21_114028_add_lms_username_to_person',57);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2022_04_16_200144_add_profile_image_to_person_photo',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2022_04_21_072909_track_bounces',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2022_04_21_073355_create_mail_log',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2022_04_24_094422_change_person_id_on_mail_log',60);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2022_04_27_165438_add_alert_when_empty_to_position',61);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2022_04_28_173246_add_is_job_provision_to_access_document',62);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2022_05_03_111156_add_did_complain_to_mail_log',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2022_05_12_111452_create_certifications',64);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2022_05_28_192502_rename_is_job_provision',65);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2022_06_08_132121_add_more_meals_to_access_documents',66);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2022_09_20_235413_add_timezone_to_slot',67);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2022_09_28_172100_create_person_provision_table',68);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2022_10_17_144511_create_award_table',69);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2021_05_16_110807_add_teams_to_positions',70);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2021_05_16_111000_create_team',70);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2021_11_15_123753_create_person_position_log',70);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2022_09_16_134724_add_roles_to_positions',70);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2022_11_11_065414_add_on_trainer_report_to_position',70);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2022_12_01_163428_cleanup_schema',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2022_12_03_141101_add_nda_to_person_event',72);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2022_12_07_165355_add_ticket_info_to_person_event',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2022_12_11_115854_create_swag',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2022_12_16_120136_create_person_pog',75);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2022_12_17_152231_add_pii_to_person_event',76);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2022_12_30_171846_create_handle_reservation_table',77);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2023_01_08_164233_add_lms_course_id_to_person_event',78);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2023_01_08_175055_drop_old_person_columns',78);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2023_01_16_205430_add_require_training_for_roles_to_position',79);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2023_01_20_165433_create_email_history',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2023_01_13_001226_add_special_types_to_access_document',81);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2023_02_01_010852_create_team_manager',82);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2023_02_08_160749_add_is_lead_to_trainer_status',83);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2023_02_09_154311_change_teams_categories',84);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2023_02_09_173103_change_position_alerts',84);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2023_02_26_173705_add_people_to_asset_person',85);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2023_02_27_095240_add_consumed_year_to_provision',85);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2023_03_03_210107_add_vanity_tracking_to_person',86);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2023_03_04_200659_add_asset_and_swag_to_roles',87);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2023_03_25_151941_add_issued_at_to_person_pog',88);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (165,'2023_03_25_173152_add_shift_force_roll',88);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (166,'2023_03_27_121527_add_paycode_to_position',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (167,'2023_04_07_235540_add_employee_code_to_person',90);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (168,'2023_04_09_210435_add_new_roles',90);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (169,'2023_04_11_214636_add_vehicle_pass_type_to_access_document',91);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (170,'2023_04_14_111417_add_resource_tag_to_position',92);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (171,'2023_04_17_173707_add_lms_enrolled_at_to_person_event',93);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (172,'2023_05_03_223354_add_start_year_to_position_credit',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (173,'2023_05_04_123634_add_timezone_abbr_to_slot',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (174,'2023_05_04_155212_add_virutal_columns_to_slot',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (175,'2023_05_26_103152_create_pods',95);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (176,'2023_06_04_081908_add_deselect_on_join_to_position',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (177,'2023_06_10_161617_add_no_payroll_meal_adjustment_to_position',97);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (178,'2023_06_23_152037_add_info_to_pod',98);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (179,'2023_07_24_163001_add_timecard_year_round_to_role',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (180,'2023_07_26_145320_add_no_training_requirement_to_position',100);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (181,'2023_09_17_115650_replace_rpt_with_spt',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (182,'2023_09_18_091018_add_salesforce_import_permission',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (183,'2023_09_22_144331_add_message_management_role',103);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (184,'2023_09_23_190121_asset_cleanup',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (185,'2023_09_26_175056_cleanup_gender',105);
