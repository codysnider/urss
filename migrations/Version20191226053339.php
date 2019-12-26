<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191226053339 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE `counters_cache` (owner_uid INT DEFAULT NULL, `feed_id` INT NOT NULL, `value` INT NOT NULL, `updated` DATETIME NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), INDEX ttrss_counters_cache_value_idx (value), INDEX ttrss_counters_cache_feed_id_idx (feed_id), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `enclosure` (post_id INT DEFAULT NULL, `content_url` TEXT NOT NULL, `content_type` VARCHAR(250) NOT NULL, `title` TEXT NOT NULL, `duration` TEXT NOT NULL, `width` INT NOT NULL, `height` INT NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX post_id (post_id), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `feed` (cat_id INT DEFAULT NULL, parent_feed INT DEFAULT NULL, owner_uid INT DEFAULT NULL, `title` VARCHAR(200) NOT NULL, `feed_url` VARCHAR(255) NOT NULL, `icon_url` VARCHAR(250) NOT NULL, `update_interval` INT NOT NULL, `purge_interval` INT NOT NULL, `last_updated` DATETIME DEFAULT NULL, `last_unconditional` DATETIME DEFAULT NULL, `last_error` VARCHAR(250) NOT NULL, `last_modified` VARCHAR(250) NOT NULL, `favicon_avg_color` VARCHAR(11) DEFAULT NULL, `site_url` VARCHAR(250) NOT NULL, `auth_login` VARCHAR(250) NOT NULL, `auth_pass` VARCHAR(250) NOT NULL, `private` TINYINT(1) NOT NULL, `rtl_content` TINYINT(1) NOT NULL, `hidden` TINYINT(1) NOT NULL, `include_in_digest` TINYINT(1) NOT NULL, `cache_images` TINYINT(1) NOT NULL, `hide_images` TINYINT(1) NOT NULL, `cache_content` TINYINT(1) NOT NULL, `auth_pass_encrypted` TINYINT(1) NOT NULL, `last_viewed` DATETIME DEFAULT NULL, `last_update_started` DATETIME DEFAULT NULL, `always_display_enclosures` TINYINT(1) NOT NULL, `update_method` INT NOT NULL, `order_id` INT NOT NULL, `mark_unread_on_update` TINYINT(1) NOT NULL, `update_on_checksum_change` TINYINT(1) NOT NULL, `strip_images` TINYINT(1) NOT NULL, `view_settings` VARCHAR(250) NOT NULL, `pubsub_state` INT NOT NULL, `favicon_last_checked` DATETIME DEFAULT NULL, `feed_language` VARCHAR(100) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), INDEX parent_feed (parent_feed), INDEX cat_id (cat_id), UNIQUE INDEX feed_url (feed_url, owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `feedbrowser_cache` (`feed_url` TEXT NOT NULL, `site_url` TEXT NOT NULL, `title` TEXT NOT NULL, `subscribers` INT NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `preference_section` (`order_id` INT NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `version` (`schema_version` INT NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `preference` (type_id INT DEFAULT NULL, section_id INT DEFAULT NULL, `pref_name` VARCHAR(250) NOT NULL, `access_level` INT NOT NULL, `def_value` TEXT NOT NULL, INDEX type_id (type_id), INDEX section_id (section_id), PRIMARY KEY(`pref_name`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `label` (owner_uid INT DEFAULT NULL, `caption` VARCHAR(250) NOT NULL, `fg_color` VARCHAR(15) NOT NULL, `bg_color` VARCHAR(15) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `error_log` (owner_uid INT DEFAULT NULL, `errno` INT NOT NULL, `errstr` TEXT NOT NULL, `filename` TEXT NOT NULL, `lineno` INT NOT NULL, `context` TEXT NOT NULL, `created_at` DATETIME NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `filter_type` (`name` VARCHAR(120) NOT NULL, `description` VARCHAR(250) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, UNIQUE INDEX name (name), UNIQUE INDEX description (description), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `archived_feed` (owner_uid INT DEFAULT NULL, `created` DATETIME NOT NULL, `title` VARCHAR(200) NOT NULL, `feed_url` TEXT NOT NULL, `site_url` VARCHAR(250) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `entry_comment` (ref_id INT DEFAULT NULL, owner_uid INT DEFAULT NULL, `private` TINYINT(1) NOT NULL, `date_entered` DATETIME NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX ref_id (ref_id), INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `advanced_filter_action` (filter_id INT DEFAULT NULL, action_id INT DEFAULT NULL, `action_param` VARCHAR(250) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX filter_id (filter_id), INDEX action_id (action_id), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `tag` (post_id INT DEFAULT NULL, owner_uid INT DEFAULT NULL, `tag_name` VARCHAR(250) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX post_id (post_id), INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `feed_category` (parent_cat INT DEFAULT NULL, owner_uid INT DEFAULT NULL, `title` VARCHAR(200) NOT NULL, `collapsed` TINYINT(1) NOT NULL, `order_id` INT NOT NULL, `view_settings` VARCHAR(250) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX parent_cat (parent_cat), INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `user` (`login` VARCHAR(120) NOT NULL, `pwd_hash` VARCHAR(250) NOT NULL, `last_login` DATETIME DEFAULT NULL, `access_level` INT NOT NULL, `email` VARCHAR(250) NOT NULL, `full_name` VARCHAR(250) NOT NULL, `email_digest` TINYINT(1) NOT NULL, `last_digest_sent` DATETIME DEFAULT NULL, `salt` VARCHAR(250) NOT NULL, `created` DATETIME DEFAULT NULL, `twitter_oauth` LONGTEXT DEFAULT NULL, `otp_enabled` TINYINT(1) NOT NULL, `resetpass_token` VARCHAR(250) DEFAULT NULL, `id` INT AUTO_INCREMENT NOT NULL, UNIQUE INDEX login (login), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `user_label` (label_id INT DEFAULT NULL, article_id INT DEFAULT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX label_id (label_id), INDEX article_id (article_id), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `plugin_storage` (owner_uid INT DEFAULT NULL, `name` VARCHAR(100) NOT NULL, `content` LONGTEXT NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `linked_instance` (`last_connected` DATETIME NOT NULL, `last_status_in` INT NOT NULL, `last_status_out` INT NOT NULL, `access_key` VARCHAR(250) NOT NULL, `access_url` TEXT NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, UNIQUE INDEX access_key (access_key), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `app_password` (owner_uid INT DEFAULT NULL, `title` VARCHAR(250) NOT NULL, `pwd_hash` TEXT NOT NULL, `service` VARCHAR(100) NOT NULL, `created` DATETIME NOT NULL, `last_used` DATETIME DEFAULT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX IDX_3D422678FC50184C (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `cat_counters_cache` (owner_uid INT DEFAULT NULL, `feed_id` INT NOT NULL, `value` INT NOT NULL, `updated` DATETIME NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `filter` (owner_uid INT DEFAULT NULL, `match_any_rule` TINYINT(1) NOT NULL, `enabled` TINYINT(1) NOT NULL, `inverse` TINYINT(1) NOT NULL, `title` VARCHAR(250) NOT NULL, `order_id` INT NOT NULL, `last_triggered` DATETIME DEFAULT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `user_preference` (profile INT DEFAULT NULL, pref_name VARCHAR(250) DEFAULT NULL, owner_uid INT DEFAULT NULL, `value` LONGTEXT NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX profile (profile), INDEX pref_name (pref_name), INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `entry` (`title` TEXT NOT NULL, `guid` VARCHAR(255) NOT NULL, `link` TEXT NOT NULL, `updated` DATETIME NOT NULL, `content` LONGTEXT NOT NULL, `content_hash` VARCHAR(250) NOT NULL, `cached_content` LONGTEXT DEFAULT NULL, `no_orig_date` TINYINT(1) NOT NULL, `date_entered` DATETIME NOT NULL, `date_updated` DATETIME NOT NULL, `num_comments` INT NOT NULL, `plugin_data` LONGTEXT DEFAULT NULL, `lang` VARCHAR(2) DEFAULT NULL, `comments` VARCHAR(250) NOT NULL, `author` VARCHAR(250) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX ttrss_entries_updated_idx (updated), INDEX ttrss_entries_date_entered_index (date_entered), UNIQUE INDEX guid (guid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `access_key` (owner_uid INT DEFAULT NULL, `access_key` VARCHAR(250) NOT NULL, `feed_id` VARCHAR(250) NOT NULL, `is_cat` TINYINT(1) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `linked_feed` (instance_id INT DEFAULT NULL, `feed_url` TEXT NOT NULL, `site_url` TEXT NOT NULL, `title` TEXT NOT NULL, `created` DATETIME NOT NULL, `updated` DATETIME NOT NULL, `subscribers` INT NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX instance_id (instance_id), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `setting_profile` (owner_uid INT DEFAULT NULL, `title` VARCHAR(250) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `preference_type` (`type_name` VARCHAR(100) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `user_entry` (ref_id INT DEFAULT NULL, feed_id INT DEFAULT NULL, orig_feed_id INT DEFAULT NULL, owner_uid INT DEFAULT NULL, `uuid` VARCHAR(200) NOT NULL, `marked` TINYINT(1) NOT NULL, `published` TINYINT(1) NOT NULL, `tag_cache` TEXT NOT NULL, `label_cache` TEXT NOT NULL, `last_read` DATETIME DEFAULT NULL, `score` INT NOT NULL, `note` LONGTEXT DEFAULT NULL, `last_marked` DATETIME DEFAULT NULL, `last_published` DATETIME DEFAULT NULL, `unread` TINYINT(1) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX ref_id (ref_id), INDEX orig_feed_id (orig_feed_id), INDEX ttrss_user_entries_unread_idx (unread), INDEX feed_id (feed_id), INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `advanced_filter_rule` (filter_id INT DEFAULT NULL, filter_type INT DEFAULT NULL, feed_id INT DEFAULT NULL, cat_id INT DEFAULT NULL, `reg_exp` TEXT NOT NULL, `inverse` TINYINT(1) NOT NULL, `cat_filter` TINYINT(1) NOT NULL, `match_on` TEXT DEFAULT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX filter_id (filter_id), INDEX feed_id (feed_id), INDEX filter_type (filter_type), INDEX cat_id (cat_id), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `session` (`id` VARCHAR(250) NOT NULL, `data` TEXT DEFAULT NULL, `expire` INT NOT NULL, INDEX expire (expire), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `filter_action` (`name` VARCHAR(120) NOT NULL, `description` VARCHAR(250) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, UNIQUE INDEX name (name), UNIQUE INDEX description (description), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE `counters_cache` ADD CONSTRAINT FK_16D10C0EFC50184C FOREIGN KEY (owner_uid) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `enclosure` ADD CONSTRAINT FK_E0F730634B89032C FOREIGN KEY (post_id) REFERENCES `entry` (id)');
        $this->addSql('ALTER TABLE `feed` ADD CONSTRAINT FK_234044ABE6ADA943 FOREIGN KEY (cat_id) REFERENCES `feed_category` (id)');
        $this->addSql('ALTER TABLE `feed` ADD CONSTRAINT FK_234044AB1EA3721F FOREIGN KEY (parent_feed) REFERENCES `feed` (id)');
        $this->addSql('ALTER TABLE `feed` ADD CONSTRAINT FK_234044ABFC50184C FOREIGN KEY (owner_uid) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `preference` ADD CONSTRAINT FK_5D69B053C54C8C93 FOREIGN KEY (type_id) REFERENCES `preference_type` (id)');
        $this->addSql('ALTER TABLE `preference` ADD CONSTRAINT FK_5D69B053D823E37A FOREIGN KEY (section_id) REFERENCES `preference_section` (id)');
        $this->addSql('ALTER TABLE `label` ADD CONSTRAINT FK_EA750E8FC50184C FOREIGN KEY (owner_uid) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `error_log` ADD CONSTRAINT FK_FCDF27A9FC50184C FOREIGN KEY (owner_uid) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `archived_feed` ADD CONSTRAINT FK_7735ACC4FC50184C FOREIGN KEY (owner_uid) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `entry_comment` ADD CONSTRAINT FK_B892FDFB21B741A9 FOREIGN KEY (ref_id) REFERENCES `entry` (id)');
        $this->addSql('ALTER TABLE `entry_comment` ADD CONSTRAINT FK_B892FDFBFC50184C FOREIGN KEY (owner_uid) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `advanced_filter_action` ADD CONSTRAINT FK_3A409978D395B25E FOREIGN KEY (filter_id) REFERENCES `filter` (id)');
        $this->addSql('ALTER TABLE `advanced_filter_action` ADD CONSTRAINT FK_3A4099789D32F035 FOREIGN KEY (action_id) REFERENCES `filter_action` (id)');
        $this->addSql('ALTER TABLE `tag` ADD CONSTRAINT FK_389B7834B89032C FOREIGN KEY (post_id) REFERENCES `user_entry` (id)');
        $this->addSql('ALTER TABLE `tag` ADD CONSTRAINT FK_389B783FC50184C FOREIGN KEY (owner_uid) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `feed_category` ADD CONSTRAINT FK_26998E66A5FD20CD FOREIGN KEY (parent_cat) REFERENCES `feed_category` (id)');
        $this->addSql('ALTER TABLE `feed_category` ADD CONSTRAINT FK_26998E66FC50184C FOREIGN KEY (owner_uid) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `user_label` ADD CONSTRAINT FK_EC65ABB033B92F39 FOREIGN KEY (label_id) REFERENCES `label` (id)');
        $this->addSql('ALTER TABLE `user_label` ADD CONSTRAINT FK_EC65ABB07294869C FOREIGN KEY (article_id) REFERENCES `entry` (id)');
        $this->addSql('ALTER TABLE `plugin_storage` ADD CONSTRAINT FK_96D88CD4FC50184C FOREIGN KEY (owner_uid) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `app_password` ADD CONSTRAINT FK_3D422678FC50184C FOREIGN KEY (owner_uid) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `cat_counters_cache` ADD CONSTRAINT FK_8839EBCAFC50184C FOREIGN KEY (owner_uid) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `filter` ADD CONSTRAINT FK_7FC45F1DFC50184C FOREIGN KEY (owner_uid) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `user_preference` ADD CONSTRAINT FK_FA0E76BF8157AA0F FOREIGN KEY (profile) REFERENCES `setting_profile` (id)');
        $this->addSql('ALTER TABLE `user_preference` ADD CONSTRAINT FK_FA0E76BF3AD83793 FOREIGN KEY (pref_name) REFERENCES `preference` (pref_name)');
        $this->addSql('ALTER TABLE `user_preference` ADD CONSTRAINT FK_FA0E76BFFC50184C FOREIGN KEY (owner_uid) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `access_key` ADD CONSTRAINT FK_EAD0F67CFC50184C FOREIGN KEY (owner_uid) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `linked_feed` ADD CONSTRAINT FK_F73791D13A51721D FOREIGN KEY (instance_id) REFERENCES `linked_instance` (id)');
        $this->addSql('ALTER TABLE `setting_profile` ADD CONSTRAINT FK_D9051AE7FC50184C FOREIGN KEY (owner_uid) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `user_entry` ADD CONSTRAINT FK_C9E3662821B741A9 FOREIGN KEY (ref_id) REFERENCES `entry` (id)');
        $this->addSql('ALTER TABLE `user_entry` ADD CONSTRAINT FK_C9E3662851A5BC03 FOREIGN KEY (feed_id) REFERENCES `feed` (id)');
        $this->addSql('ALTER TABLE `user_entry` ADD CONSTRAINT FK_C9E36628D2629FE4 FOREIGN KEY (orig_feed_id) REFERENCES `archived_feed` (id)');
        $this->addSql('ALTER TABLE `user_entry` ADD CONSTRAINT FK_C9E36628FC50184C FOREIGN KEY (owner_uid) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `advanced_filter_rule` ADD CONSTRAINT FK_733BC996D395B25E FOREIGN KEY (filter_id) REFERENCES `filter` (id)');
        $this->addSql('ALTER TABLE `advanced_filter_rule` ADD CONSTRAINT FK_733BC996E4E43050 FOREIGN KEY (filter_type) REFERENCES `filter_type` (id)');
        $this->addSql('ALTER TABLE `advanced_filter_rule` ADD CONSTRAINT FK_733BC99651A5BC03 FOREIGN KEY (feed_id) REFERENCES `feed` (id)');
        $this->addSql('ALTER TABLE `advanced_filter_rule` ADD CONSTRAINT FK_733BC996E6ADA943 FOREIGN KEY (cat_id) REFERENCES `feed_category` (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `feed` DROP FOREIGN KEY FK_234044AB1EA3721F');
        $this->addSql('ALTER TABLE `user_entry` DROP FOREIGN KEY FK_C9E3662851A5BC03');
        $this->addSql('ALTER TABLE `advanced_filter_rule` DROP FOREIGN KEY FK_733BC99651A5BC03');
        $this->addSql('ALTER TABLE `preference` DROP FOREIGN KEY FK_5D69B053D823E37A');
        $this->addSql('ALTER TABLE `user_preference` DROP FOREIGN KEY FK_FA0E76BF3AD83793');
        $this->addSql('ALTER TABLE `user_label` DROP FOREIGN KEY FK_EC65ABB033B92F39');
        $this->addSql('ALTER TABLE `advanced_filter_rule` DROP FOREIGN KEY FK_733BC996E4E43050');
        $this->addSql('ALTER TABLE `user_entry` DROP FOREIGN KEY FK_C9E36628D2629FE4');
        $this->addSql('ALTER TABLE `feed` DROP FOREIGN KEY FK_234044ABE6ADA943');
        $this->addSql('ALTER TABLE `feed_category` DROP FOREIGN KEY FK_26998E66A5FD20CD');
        $this->addSql('ALTER TABLE `advanced_filter_rule` DROP FOREIGN KEY FK_733BC996E6ADA943');
        $this->addSql('ALTER TABLE `counters_cache` DROP FOREIGN KEY FK_16D10C0EFC50184C');
        $this->addSql('ALTER TABLE `feed` DROP FOREIGN KEY FK_234044ABFC50184C');
        $this->addSql('ALTER TABLE `label` DROP FOREIGN KEY FK_EA750E8FC50184C');
        $this->addSql('ALTER TABLE `error_log` DROP FOREIGN KEY FK_FCDF27A9FC50184C');
        $this->addSql('ALTER TABLE `archived_feed` DROP FOREIGN KEY FK_7735ACC4FC50184C');
        $this->addSql('ALTER TABLE `entry_comment` DROP FOREIGN KEY FK_B892FDFBFC50184C');
        $this->addSql('ALTER TABLE `tag` DROP FOREIGN KEY FK_389B783FC50184C');
        $this->addSql('ALTER TABLE `feed_category` DROP FOREIGN KEY FK_26998E66FC50184C');
        $this->addSql('ALTER TABLE `plugin_storage` DROP FOREIGN KEY FK_96D88CD4FC50184C');
        $this->addSql('ALTER TABLE `app_password` DROP FOREIGN KEY FK_3D422678FC50184C');
        $this->addSql('ALTER TABLE `cat_counters_cache` DROP FOREIGN KEY FK_8839EBCAFC50184C');
        $this->addSql('ALTER TABLE `filter` DROP FOREIGN KEY FK_7FC45F1DFC50184C');
        $this->addSql('ALTER TABLE `user_preference` DROP FOREIGN KEY FK_FA0E76BFFC50184C');
        $this->addSql('ALTER TABLE `access_key` DROP FOREIGN KEY FK_EAD0F67CFC50184C');
        $this->addSql('ALTER TABLE `setting_profile` DROP FOREIGN KEY FK_D9051AE7FC50184C');
        $this->addSql('ALTER TABLE `user_entry` DROP FOREIGN KEY FK_C9E36628FC50184C');
        $this->addSql('ALTER TABLE `linked_feed` DROP FOREIGN KEY FK_F73791D13A51721D');
        $this->addSql('ALTER TABLE `advanced_filter_action` DROP FOREIGN KEY FK_3A409978D395B25E');
        $this->addSql('ALTER TABLE `advanced_filter_rule` DROP FOREIGN KEY FK_733BC996D395B25E');
        $this->addSql('ALTER TABLE `enclosure` DROP FOREIGN KEY FK_E0F730634B89032C');
        $this->addSql('ALTER TABLE `entry_comment` DROP FOREIGN KEY FK_B892FDFB21B741A9');
        $this->addSql('ALTER TABLE `user_label` DROP FOREIGN KEY FK_EC65ABB07294869C');
        $this->addSql('ALTER TABLE `user_entry` DROP FOREIGN KEY FK_C9E3662821B741A9');
        $this->addSql('ALTER TABLE `user_preference` DROP FOREIGN KEY FK_FA0E76BF8157AA0F');
        $this->addSql('ALTER TABLE `preference` DROP FOREIGN KEY FK_5D69B053C54C8C93');
        $this->addSql('ALTER TABLE `tag` DROP FOREIGN KEY FK_389B7834B89032C');
        $this->addSql('ALTER TABLE `advanced_filter_action` DROP FOREIGN KEY FK_3A4099789D32F035');
        $this->addSql('DROP TABLE `counters_cache`');
        $this->addSql('DROP TABLE `enclosure`');
        $this->addSql('DROP TABLE `feed`');
        $this->addSql('DROP TABLE `feedbrowser_cache`');
        $this->addSql('DROP TABLE `preference_section`');
        $this->addSql('DROP TABLE `version`');
        $this->addSql('DROP TABLE `preference`');
        $this->addSql('DROP TABLE `label`');
        $this->addSql('DROP TABLE `error_log`');
        $this->addSql('DROP TABLE `filter_type`');
        $this->addSql('DROP TABLE `archived_feed`');
        $this->addSql('DROP TABLE `entry_comment`');
        $this->addSql('DROP TABLE `advanced_filter_action`');
        $this->addSql('DROP TABLE `tag`');
        $this->addSql('DROP TABLE `feed_category`');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE `user_label`');
        $this->addSql('DROP TABLE `plugin_storage`');
        $this->addSql('DROP TABLE `linked_instance`');
        $this->addSql('DROP TABLE `app_password`');
        $this->addSql('DROP TABLE `cat_counters_cache`');
        $this->addSql('DROP TABLE `filter`');
        $this->addSql('DROP TABLE `user_preference`');
        $this->addSql('DROP TABLE `entry`');
        $this->addSql('DROP TABLE `access_key`');
        $this->addSql('DROP TABLE `linked_feed`');
        $this->addSql('DROP TABLE `setting_profile`');
        $this->addSql('DROP TABLE `preference_type`');
        $this->addSql('DROP TABLE `user_entry`');
        $this->addSql('DROP TABLE `advanced_filter_rule`');
        $this->addSql('DROP TABLE `session`');
        $this->addSql('DROP TABLE `filter_action`');
    }
}
