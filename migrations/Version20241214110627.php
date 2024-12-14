<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241214110627 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project ADD COLUMN label_footer CLOB DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__project AS SELECT id, code, updated_at, image_size, image_name, name, description, visibility, city, address, local_import_root_dir, room_count, form_data, source_url, google_sheets_id, sheet_db_id, views, flickr_album_id, flickr_username, label, locale FROM project');
        $this->addSql('DROP TABLE project');
        $this->addSql('CREATE TABLE project (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, code VARCHAR(32) NOT NULL, updated_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , image_size INTEGER DEFAULT NULL, image_name VARCHAR(255) DEFAULT NULL, name VARCHAR(64) NOT NULL, description CLOB DEFAULT NULL, visibility VARCHAR(16) NOT NULL, city VARCHAR(255) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, local_import_root_dir VARCHAR(255) DEFAULT NULL, room_count INTEGER DEFAULT NULL, form_data CLOB DEFAULT NULL --(DC2Type:json)
        , source_url VARCHAR(255) DEFAULT NULL, google_sheets_id VARCHAR(255) DEFAULT NULL, sheet_db_id VARCHAR(32) DEFAULT NULL, views INTEGER DEFAULT NULL, flickr_album_id VARCHAR(255) DEFAULT NULL, flickr_username VARCHAR(255) DEFAULT NULL, label VARCHAR(255) NOT NULL, locale VARCHAR(255) DEFAULT NULL)');
        $this->addSql('INSERT INTO project (id, code, updated_at, image_size, image_name, name, description, visibility, city, address, local_import_root_dir, room_count, form_data, source_url, google_sheets_id, sheet_db_id, views, flickr_album_id, flickr_username, label, locale) SELECT id, code, updated_at, image_size, image_name, name, description, visibility, city, address, local_import_root_dir, room_count, form_data, source_url, google_sheets_id, sheet_db_id, views, flickr_album_id, flickr_username, label, locale FROM __temp__project');
        $this->addSql('DROP TABLE __temp__project');
    }
}
