<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241214112049 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE item ADD COLUMN youtube_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__item AS SELECT id, project_id, attributes, filename, transcript, code, relative_path, title, description, duration, status, local_code, section_title, order_idx, stop_id, end_row, start_row, source_filename, visibility, short_code, asset_relative_path, views, audio, video, image, label, size, year, image_urls, audio_url FROM item');
        $this->addSql('DROP TABLE item');
        $this->addSql('CREATE TABLE item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, project_id INTEGER NOT NULL, attributes CLOB DEFAULT NULL --(DC2Type:json)
        , filename VARCHAR(255) DEFAULT NULL, transcript CLOB DEFAULT NULL, code VARCHAR(64) NOT NULL, relative_path VARCHAR(255) DEFAULT NULL, title VARCHAR(255) DEFAULT NULL, description CLOB DEFAULT NULL, duration INTEGER DEFAULT NULL, status VARCHAR(32) DEFAULT NULL, local_code VARCHAR(4) NOT NULL, section_title VARCHAR(255) DEFAULT NULL, order_idx INTEGER DEFAULT NULL, stop_id VARCHAR(32) DEFAULT NULL, end_row INTEGER DEFAULT NULL, start_row INTEGER DEFAULT NULL, source_filename VARCHAR(255) DEFAULT NULL, visibility VARCHAR(9) DEFAULT NULL, short_code VARCHAR(16) NOT NULL, asset_relative_path VARCHAR(255) DEFAULT NULL, views INTEGER DEFAULT NULL, audio VARCHAR(255) DEFAULT NULL, video VARCHAR(255) DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, label VARCHAR(255) NOT NULL, size VARCHAR(255) DEFAULT NULL, year INTEGER DEFAULT NULL, image_urls CLOB DEFAULT NULL --(DC2Type:json)
        , audio_url VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_1F1B251E166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO item (id, project_id, attributes, filename, transcript, code, relative_path, title, description, duration, status, local_code, section_title, order_idx, stop_id, end_row, start_row, source_filename, visibility, short_code, asset_relative_path, views, audio, video, image, label, size, year, image_urls, audio_url) SELECT id, project_id, attributes, filename, transcript, code, relative_path, title, description, duration, status, local_code, section_title, order_idx, stop_id, end_row, start_row, source_filename, visibility, short_code, asset_relative_path, views, audio, video, image, label, size, year, image_urls, audio_url FROM __temp__item');
        $this->addSql('DROP TABLE __temp__item');
        $this->addSql('CREATE INDEX IDX_1F1B251E166D1F9C ON item (project_id)');
    }
}
