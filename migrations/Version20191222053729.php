<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191222053729 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE `EntryComment` (ref_id INT DEFAULT NULL, owner_uid INT DEFAULT NULL, `private` TINYINT(1) NOT NULL, `date_entered` DATETIME NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX ref_id (ref_id), INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `PreferenceSection` (`order_id` INT NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `FeedCategory` (parent_cat INT DEFAULT NULL, owner_uid INT DEFAULT NULL, `title` VARCHAR(200) NOT NULL, `collapsed` TINYINT(1) NOT NULL, `order_id` INT NOT NULL, `view_settings` VARCHAR(250) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX parent_cat (parent_cat), INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `Feed` (cat_id INT DEFAULT NULL, parent_feed INT DEFAULT NULL, owner_uid INT DEFAULT NULL, `title` VARCHAR(200) NOT NULL, `feed_url` VARCHAR(255) NOT NULL, `icon_url` VARCHAR(250) NOT NULL, `update_interval` INT NOT NULL, `purge_interval` INT NOT NULL, `last_updated` DATETIME DEFAULT NULL, `last_unconditional` DATETIME DEFAULT NULL, `last_error` VARCHAR(250) NOT NULL, `last_modified` VARCHAR(250) NOT NULL, `favicon_avg_color` VARCHAR(11) DEFAULT NULL, `site_url` VARCHAR(250) NOT NULL, `auth_login` VARCHAR(250) NOT NULL, `auth_pass` VARCHAR(250) NOT NULL, `private` TINYINT(1) NOT NULL, `rtl_content` TINYINT(1) NOT NULL, `hidden` TINYINT(1) NOT NULL, `include_in_digest` TINYINT(1) NOT NULL, `cache_images` TINYINT(1) NOT NULL, `hide_images` TINYINT(1) NOT NULL, `cache_content` TINYINT(1) NOT NULL, `auth_pass_encrypted` TINYINT(1) NOT NULL, `last_viewed` DATETIME DEFAULT NULL, `last_update_started` DATETIME DEFAULT NULL, `always_display_enclosures` TINYINT(1) NOT NULL, `update_method` INT NOT NULL, `order_id` INT NOT NULL, `mark_unread_on_update` TINYINT(1) NOT NULL, `update_on_checksum_change` TINYINT(1) NOT NULL, `strip_images` TINYINT(1) NOT NULL, `view_settings` VARCHAR(250) NOT NULL, `pubsub_state` INT NOT NULL, `favicon_last_checked` DATETIME DEFAULT NULL, `feed_language` VARCHAR(100) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), INDEX parent_feed (parent_feed), INDEX cat_id (cat_id), UNIQUE INDEX feed_url (feed_url, owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `FilterAction` (`name` VARCHAR(120) NOT NULL, `description` VARCHAR(250) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, UNIQUE INDEX name (name), UNIQUE INDEX description (description), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `PreferenceType` (`type_name` VARCHAR(100) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `CountersCache` (owner_uid INT DEFAULT NULL, `feed_id` INT NOT NULL, `value` INT NOT NULL, `updated` DATETIME NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), INDEX ttrss_counters_cache_value_idx (value), INDEX ttrss_counters_cache_feed_id_idx (feed_id), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `LinkedFeed` (instance_id INT DEFAULT NULL, `feed_url` TEXT NOT NULL, `site_url` TEXT NOT NULL, `title` TEXT NOT NULL, `created` DATETIME NOT NULL, `updated` DATETIME NOT NULL, `subscribers` INT NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX instance_id (instance_id), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `User` (`login` VARCHAR(120) NOT NULL, `pwd_hash` VARCHAR(250) NOT NULL, `last_login` DATETIME DEFAULT NULL, `access_level` INT NOT NULL, `email` VARCHAR(250) NOT NULL, `full_name` VARCHAR(250) NOT NULL, `email_digest` TINYINT(1) NOT NULL, `last_digest_sent` DATETIME DEFAULT NULL, `salt` VARCHAR(250) NOT NULL, `created` DATETIME DEFAULT NULL, `twitter_oauth` LONGTEXT DEFAULT NULL, `otp_enabled` TINYINT(1) NOT NULL, `resetpass_token` VARCHAR(250) DEFAULT NULL, `id` INT AUTO_INCREMENT NOT NULL, UNIQUE INDEX login (login), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `Tag` (post_id INT DEFAULT NULL, owner_uid INT DEFAULT NULL, `tag_name` VARCHAR(250) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX post_id (post_id), INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `ArchivedFeed` (owner_uid INT DEFAULT NULL, `created` DATETIME NOT NULL, `title` VARCHAR(200) NOT NULL, `feed_url` TEXT NOT NULL, `site_url` VARCHAR(250) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `LinkedInstance` (`last_connected` DATETIME NOT NULL, `last_status_in` INT NOT NULL, `last_status_out` INT NOT NULL, `access_key` VARCHAR(250) NOT NULL, `access_url` TEXT NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, UNIQUE INDEX access_key (access_key), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `FeedbrowserCache` (`feed_url` TEXT NOT NULL, `site_url` TEXT NOT NULL, `title` TEXT NOT NULL, `subscribers` INT NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `Preference` (type_id INT DEFAULT NULL, section_id INT DEFAULT NULL, `pref_name` VARCHAR(250) NOT NULL, `access_level` INT NOT NULL, `def_value` TEXT NOT NULL, INDEX type_id (type_id), INDEX section_id (section_id), PRIMARY KEY(`pref_name`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `CatCountersCache` (owner_uid INT DEFAULT NULL, `feed_id` INT NOT NULL, `value` INT NOT NULL, `updated` DATETIME NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `AdvancedFilterAction` (filter_id INT DEFAULT NULL, action_id INT DEFAULT NULL, `action_param` VARCHAR(250) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX filter_id (filter_id), INDEX action_id (action_id), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `UserEntry` (ref_id INT DEFAULT NULL, feed_id INT DEFAULT NULL, orig_feed_id INT DEFAULT NULL, owner_uid INT DEFAULT NULL, `uuid` VARCHAR(200) NOT NULL, `marked` TINYINT(1) NOT NULL, `published` TINYINT(1) NOT NULL, `tag_cache` TEXT NOT NULL, `label_cache` TEXT NOT NULL, `last_read` DATETIME DEFAULT NULL, `score` INT NOT NULL, `note` LONGTEXT DEFAULT NULL, `last_marked` DATETIME DEFAULT NULL, `last_published` DATETIME DEFAULT NULL, `unread` TINYINT(1) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX ref_id (ref_id), INDEX orig_feed_id (orig_feed_id), INDEX ttrss_user_entries_unread_idx (unread), INDEX feed_id (feed_id), INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `AdvancedFilterRule` (filter_id INT DEFAULT NULL, filter_type INT DEFAULT NULL, feed_id INT DEFAULT NULL, cat_id INT DEFAULT NULL, `reg_exp` TEXT NOT NULL, `inverse` TINYINT(1) NOT NULL, `cat_filter` TINYINT(1) NOT NULL, `match_on` TEXT DEFAULT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX filter_id (filter_id), INDEX feed_id (feed_id), INDEX filter_type (filter_type), INDEX cat_id (cat_id), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `UserLabel` (label_id INT DEFAULT NULL, article_id INT DEFAULT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX label_id (label_id), INDEX article_id (article_id), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `Session` (`id` VARCHAR(250) NOT NULL, `data` TEXT DEFAULT NULL, `expire` INT NOT NULL, INDEX expire (expire), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `Version` (`schema_version` INT NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `Filter` (owner_uid INT DEFAULT NULL, `match_any_rule` TINYINT(1) NOT NULL, `enabled` TINYINT(1) NOT NULL, `inverse` TINYINT(1) NOT NULL, `title` VARCHAR(250) NOT NULL, `order_id` INT NOT NULL, `last_triggered` DATETIME DEFAULT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `Label` (owner_uid INT DEFAULT NULL, `caption` VARCHAR(250) NOT NULL, `fg_color` VARCHAR(15) NOT NULL, `bg_color` VARCHAR(15) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `FilterType` (`name` VARCHAR(120) NOT NULL, `description` VARCHAR(250) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, UNIQUE INDEX name (name), UNIQUE INDEX description (description), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `SettingProfile` (owner_uid INT DEFAULT NULL, `title` VARCHAR(250) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `AppPassword` (owner_uid INT DEFAULT NULL, `title` VARCHAR(250) NOT NULL, `pwd_hash` TEXT NOT NULL, `service` VARCHAR(100) NOT NULL, `created` DATETIME NOT NULL, `last_used` DATETIME DEFAULT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX IDX_D33DCA64FC50184C (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `enclosure` (post_id INT DEFAULT NULL, `content_url` TEXT NOT NULL, `content_type` VARCHAR(250) NOT NULL, `title` TEXT NOT NULL, `duration` TEXT NOT NULL, `width` INT NOT NULL, `height` INT NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX post_id (post_id), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `ErrorLog` (owner_uid INT DEFAULT NULL, `errno` INT NOT NULL, `errstr` TEXT NOT NULL, `filename` TEXT NOT NULL, `lineno` INT NOT NULL, `context` TEXT NOT NULL, `created_at` DATETIME NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `Entry` (`title` TEXT NOT NULL, `guid` VARCHAR(255) NOT NULL, `link` TEXT NOT NULL, `updated` DATETIME NOT NULL, `content` LONGTEXT NOT NULL, `content_hash` VARCHAR(250) NOT NULL, `cached_content` LONGTEXT DEFAULT NULL, `no_orig_date` TINYINT(1) NOT NULL, `date_entered` DATETIME NOT NULL, `date_updated` DATETIME NOT NULL, `num_comments` INT NOT NULL, `plugin_data` LONGTEXT DEFAULT NULL, `lang` VARCHAR(2) DEFAULT NULL, `comments` VARCHAR(250) NOT NULL, `author` VARCHAR(250) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX ttrss_entries_updated_idx (updated), INDEX ttrss_entries_date_entered_index (date_entered), UNIQUE INDEX guid (guid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `UserPreference` (profile INT DEFAULT NULL, pref_name VARCHAR(250) DEFAULT NULL, owner_uid INT DEFAULT NULL, `value` LONGTEXT NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX profile (profile), INDEX pref_name (pref_name), INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `PluginStorage` (owner_uid INT DEFAULT NULL, `name` VARCHAR(100) NOT NULL, `content` LONGTEXT NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `AccessKey` (owner_uid INT DEFAULT NULL, `access_key` VARCHAR(250) NOT NULL, `feed_id` VARCHAR(250) NOT NULL, `is_cat` TINYINT(1) NOT NULL, `id` INT AUTO_INCREMENT NOT NULL, INDEX owner_uid (owner_uid), PRIMARY KEY(`id`)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE `EntryComment` ADD CONSTRAINT FK_85FA4B3521B741A9 FOREIGN KEY (ref_id) REFERENCES `Entry` (id)');
        $this->addSql('ALTER TABLE `EntryComment` ADD CONSTRAINT FK_85FA4B35FC50184C FOREIGN KEY (owner_uid) REFERENCES `User` (id)');
        $this->addSql('ALTER TABLE `FeedCategory` ADD CONSTRAINT FK_29515567A5FD20CD FOREIGN KEY (parent_cat) REFERENCES `FeedCategory` (id)');
        $this->addSql('ALTER TABLE `FeedCategory` ADD CONSTRAINT FK_29515567FC50184C FOREIGN KEY (owner_uid) REFERENCES `User` (id)');
        $this->addSql('ALTER TABLE `Feed` ADD CONSTRAINT FK_8372EB95E6ADA943 FOREIGN KEY (cat_id) REFERENCES `FeedCategory` (id)');
        $this->addSql('ALTER TABLE `Feed` ADD CONSTRAINT FK_8372EB951EA3721F FOREIGN KEY (parent_feed) REFERENCES `Feed` (id)');
        $this->addSql('ALTER TABLE `Feed` ADD CONSTRAINT FK_8372EB95FC50184C FOREIGN KEY (owner_uid) REFERENCES `User` (id)');
        $this->addSql('ALTER TABLE `CountersCache` ADD CONSTRAINT FK_900FB379FC50184C FOREIGN KEY (owner_uid) REFERENCES `User` (id)');
        $this->addSql('ALTER TABLE `LinkedFeed` ADD CONSTRAINT FK_4DA169DC3A51721D FOREIGN KEY (instance_id) REFERENCES `LinkedInstance` (id)');
        $this->addSql('ALTER TABLE `Tag` ADD CONSTRAINT FK_3BC4F1634B89032C FOREIGN KEY (post_id) REFERENCES `UserEntry` (id)');
        $this->addSql('ALTER TABLE `Tag` ADD CONSTRAINT FK_3BC4F163FC50184C FOREIGN KEY (owner_uid) REFERENCES `User` (id)');
        $this->addSql('ALTER TABLE `ArchivedFeed` ADD CONSTRAINT FK_7D976F61FC50184C FOREIGN KEY (owner_uid) REFERENCES `User` (id)');
        $this->addSql('ALTER TABLE `Preference` ADD CONSTRAINT FK_1234B383C54C8C93 FOREIGN KEY (type_id) REFERENCES `PreferenceType` (id)');
        $this->addSql('ALTER TABLE `Preference` ADD CONSTRAINT FK_1234B383D823E37A FOREIGN KEY (section_id) REFERENCES `PreferenceSection` (id)');
        $this->addSql('ALTER TABLE `CatCountersCache` ADD CONSTRAINT FK_7C705EF3FC50184C FOREIGN KEY (owner_uid) REFERENCES `User` (id)');
        $this->addSql('ALTER TABLE `AdvancedFilterAction` ADD CONSTRAINT FK_E240ADE9D395B25E FOREIGN KEY (filter_id) REFERENCES `Filter` (id)');
        $this->addSql('ALTER TABLE `AdvancedFilterAction` ADD CONSTRAINT FK_E240ADE99D32F035 FOREIGN KEY (action_id) REFERENCES `FilterAction` (id)');
        $this->addSql('ALTER TABLE `UserEntry` ADD CONSTRAINT FK_4FB50C0521B741A9 FOREIGN KEY (ref_id) REFERENCES `Entry` (id)');
        $this->addSql('ALTER TABLE `UserEntry` ADD CONSTRAINT FK_4FB50C0551A5BC03 FOREIGN KEY (feed_id) REFERENCES `Feed` (id)');
        $this->addSql('ALTER TABLE `UserEntry` ADD CONSTRAINT FK_4FB50C05D2629FE4 FOREIGN KEY (orig_feed_id) REFERENCES `ArchivedFeed` (id)');
        $this->addSql('ALTER TABLE `UserEntry` ADD CONSTRAINT FK_4FB50C05FC50184C FOREIGN KEY (owner_uid) REFERENCES `User` (id)');
        $this->addSql('ALTER TABLE `AdvancedFilterRule` ADD CONSTRAINT FK_11A5F1CD395B25E FOREIGN KEY (filter_id) REFERENCES `Filter` (id)');
        $this->addSql('ALTER TABLE `AdvancedFilterRule` ADD CONSTRAINT FK_11A5F1CE4E43050 FOREIGN KEY (filter_type) REFERENCES `FilterType` (id)');
        $this->addSql('ALTER TABLE `AdvancedFilterRule` ADD CONSTRAINT FK_11A5F1C51A5BC03 FOREIGN KEY (feed_id) REFERENCES `Feed` (id)');
        $this->addSql('ALTER TABLE `AdvancedFilterRule` ADD CONSTRAINT FK_11A5F1CE6ADA943 FOREIGN KEY (cat_id) REFERENCES `FeedCategory` (id)');
        $this->addSql('ALTER TABLE `UserLabel` ADD CONSTRAINT FK_6A33C19D33B92F39 FOREIGN KEY (label_id) REFERENCES `Label` (id)');
        $this->addSql('ALTER TABLE `UserLabel` ADD CONSTRAINT FK_6A33C19D7294869C FOREIGN KEY (article_id) REFERENCES `Entry` (id)');
        $this->addSql('ALTER TABLE `Filter` ADD CONSTRAINT FK_78685A2BFC50184C FOREIGN KEY (owner_uid) REFERENCES `User` (id)');
        $this->addSql('ALTER TABLE `Label` ADD CONSTRAINT FK_CF667FECFC50184C FOREIGN KEY (owner_uid) REFERENCES `User` (id)');
        $this->addSql('ALTER TABLE `SettingProfile` ADD CONSTRAINT FK_4041F5F5FC50184C FOREIGN KEY (owner_uid) REFERENCES `User` (id)');
        $this->addSql('ALTER TABLE `AppPassword` ADD CONSTRAINT FK_D33DCA64FC50184C FOREIGN KEY (owner_uid) REFERENCES `User` (id)');
        $this->addSql('ALTER TABLE `enclosure` ADD CONSTRAINT FK_E0F730634B89032C FOREIGN KEY (post_id) REFERENCES `Entry` (id)');
        $this->addSql('ALTER TABLE `ErrorLog` ADD CONSTRAINT FK_AB250194FC50184C FOREIGN KEY (owner_uid) REFERENCES `User` (id)');
        $this->addSql('ALTER TABLE `UserPreference` ADD CONSTRAINT FK_922CE7A28157AA0F FOREIGN KEY (profile) REFERENCES `SettingProfile` (id)');
        $this->addSql('ALTER TABLE `UserPreference` ADD CONSTRAINT FK_922CE7A23AD83793 FOREIGN KEY (pref_name) REFERENCES `Preference` (pref_name)');
        $this->addSql('ALTER TABLE `UserPreference` ADD CONSTRAINT FK_922CE7A2FC50184C FOREIGN KEY (owner_uid) REFERENCES `User` (id)');
        $this->addSql('ALTER TABLE `PluginStorage` ADD CONSTRAINT FK_A4E2C962FC50184C FOREIGN KEY (owner_uid) REFERENCES `User` (id)');
        $this->addSql('ALTER TABLE `AccessKey` ADD CONSTRAINT FK_1987CD15FC50184C FOREIGN KEY (owner_uid) REFERENCES `User` (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `Preference` DROP FOREIGN KEY FK_1234B383D823E37A');
        $this->addSql('ALTER TABLE `FeedCategory` DROP FOREIGN KEY FK_29515567A5FD20CD');
        $this->addSql('ALTER TABLE `Feed` DROP FOREIGN KEY FK_8372EB95E6ADA943');
        $this->addSql('ALTER TABLE `AdvancedFilterRule` DROP FOREIGN KEY FK_11A5F1CE6ADA943');
        $this->addSql('ALTER TABLE `Feed` DROP FOREIGN KEY FK_8372EB951EA3721F');
        $this->addSql('ALTER TABLE `UserEntry` DROP FOREIGN KEY FK_4FB50C0551A5BC03');
        $this->addSql('ALTER TABLE `AdvancedFilterRule` DROP FOREIGN KEY FK_11A5F1C51A5BC03');
        $this->addSql('ALTER TABLE `AdvancedFilterAction` DROP FOREIGN KEY FK_E240ADE99D32F035');
        $this->addSql('ALTER TABLE `Preference` DROP FOREIGN KEY FK_1234B383C54C8C93');
        $this->addSql('ALTER TABLE `EntryComment` DROP FOREIGN KEY FK_85FA4B35FC50184C');
        $this->addSql('ALTER TABLE `FeedCategory` DROP FOREIGN KEY FK_29515567FC50184C');
        $this->addSql('ALTER TABLE `Feed` DROP FOREIGN KEY FK_8372EB95FC50184C');
        $this->addSql('ALTER TABLE `CountersCache` DROP FOREIGN KEY FK_900FB379FC50184C');
        $this->addSql('ALTER TABLE `Tag` DROP FOREIGN KEY FK_3BC4F163FC50184C');
        $this->addSql('ALTER TABLE `ArchivedFeed` DROP FOREIGN KEY FK_7D976F61FC50184C');
        $this->addSql('ALTER TABLE `CatCountersCache` DROP FOREIGN KEY FK_7C705EF3FC50184C');
        $this->addSql('ALTER TABLE `UserEntry` DROP FOREIGN KEY FK_4FB50C05FC50184C');
        $this->addSql('ALTER TABLE `Filter` DROP FOREIGN KEY FK_78685A2BFC50184C');
        $this->addSql('ALTER TABLE `Label` DROP FOREIGN KEY FK_CF667FECFC50184C');
        $this->addSql('ALTER TABLE `SettingProfile` DROP FOREIGN KEY FK_4041F5F5FC50184C');
        $this->addSql('ALTER TABLE `AppPassword` DROP FOREIGN KEY FK_D33DCA64FC50184C');
        $this->addSql('ALTER TABLE `ErrorLog` DROP FOREIGN KEY FK_AB250194FC50184C');
        $this->addSql('ALTER TABLE `UserPreference` DROP FOREIGN KEY FK_922CE7A2FC50184C');
        $this->addSql('ALTER TABLE `PluginStorage` DROP FOREIGN KEY FK_A4E2C962FC50184C');
        $this->addSql('ALTER TABLE `AccessKey` DROP FOREIGN KEY FK_1987CD15FC50184C');
        $this->addSql('ALTER TABLE `UserEntry` DROP FOREIGN KEY FK_4FB50C05D2629FE4');
        $this->addSql('ALTER TABLE `LinkedFeed` DROP FOREIGN KEY FK_4DA169DC3A51721D');
        $this->addSql('ALTER TABLE `UserPreference` DROP FOREIGN KEY FK_922CE7A23AD83793');
        $this->addSql('ALTER TABLE `Tag` DROP FOREIGN KEY FK_3BC4F1634B89032C');
        $this->addSql('ALTER TABLE `AdvancedFilterAction` DROP FOREIGN KEY FK_E240ADE9D395B25E');
        $this->addSql('ALTER TABLE `AdvancedFilterRule` DROP FOREIGN KEY FK_11A5F1CD395B25E');
        $this->addSql('ALTER TABLE `UserLabel` DROP FOREIGN KEY FK_6A33C19D33B92F39');
        $this->addSql('ALTER TABLE `AdvancedFilterRule` DROP FOREIGN KEY FK_11A5F1CE4E43050');
        $this->addSql('ALTER TABLE `UserPreference` DROP FOREIGN KEY FK_922CE7A28157AA0F');
        $this->addSql('ALTER TABLE `EntryComment` DROP FOREIGN KEY FK_85FA4B3521B741A9');
        $this->addSql('ALTER TABLE `UserEntry` DROP FOREIGN KEY FK_4FB50C0521B741A9');
        $this->addSql('ALTER TABLE `UserLabel` DROP FOREIGN KEY FK_6A33C19D7294869C');
        $this->addSql('ALTER TABLE `enclosure` DROP FOREIGN KEY FK_E0F730634B89032C');
        $this->addSql('DROP TABLE `EntryComment`');
        $this->addSql('DROP TABLE `PreferenceSection`');
        $this->addSql('DROP TABLE `FeedCategory`');
        $this->addSql('DROP TABLE `Feed`');
        $this->addSql('DROP TABLE `FilterAction`');
        $this->addSql('DROP TABLE `PreferenceType`');
        $this->addSql('DROP TABLE `CountersCache`');
        $this->addSql('DROP TABLE `LinkedFeed`');
        $this->addSql('DROP TABLE `User`');
        $this->addSql('DROP TABLE `Tag`');
        $this->addSql('DROP TABLE `ArchivedFeed`');
        $this->addSql('DROP TABLE `LinkedInstance`');
        $this->addSql('DROP TABLE `FeedbrowserCache`');
        $this->addSql('DROP TABLE `Preference`');
        $this->addSql('DROP TABLE `CatCountersCache`');
        $this->addSql('DROP TABLE `AdvancedFilterAction`');
        $this->addSql('DROP TABLE `UserEntry`');
        $this->addSql('DROP TABLE `AdvancedFilterRule`');
        $this->addSql('DROP TABLE `UserLabel`');
        $this->addSql('DROP TABLE `Session`');
        $this->addSql('DROP TABLE `Version`');
        $this->addSql('DROP TABLE `Filter`');
        $this->addSql('DROP TABLE `Label`');
        $this->addSql('DROP TABLE `FilterType`');
        $this->addSql('DROP TABLE `SettingProfile`');
        $this->addSql('DROP TABLE `AppPassword`');
        $this->addSql('DROP TABLE `enclosure`');
        $this->addSql('DROP TABLE `ErrorLog`');
        $this->addSql('DROP TABLE `Entry`');
        $this->addSql('DROP TABLE `UserPreference`');
        $this->addSql('DROP TABLE `PluginStorage`');
        $this->addSql('DROP TABLE `AccessKey`');
    }
}
