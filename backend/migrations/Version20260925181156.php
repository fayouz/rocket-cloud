<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925181156 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Files: folders, stored files and share links';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE folder (id UUID NOT NULL, name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, parent_id UUID DEFAULT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_folder_name ON folder (owner_id, parent_id, name)');
        $this->addSql('CREATE INDEX IDX_ECA209CD727ACA70 ON folder (parent_id)');
        $this->addSql('CREATE INDEX IDX_ECA209CD7E3C61F9 ON folder (owner_id)');
        $this->addSql('CREATE TABLE share_link (id UUID NOT NULL, token VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, password_hash VARCHAR(255) DEFAULT NULL, max_downloads INT DEFAULT NULL, download_count INT NOT NULL, recipients JSON DEFAULT \'[]\' NOT NULL, message VARCHAR(2000) DEFAULT NULL, notification_status VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_download_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, file_id UUID DEFAULT NULL, folder_id UUID DEFAULT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8B6B94685F37A13B ON share_link (token)');
        $this->addSql('CREATE INDEX IDX_8B6B946893CB796C ON share_link (file_id)');
        $this->addSql('CREATE INDEX IDX_8B6B9468162CB942 ON share_link (folder_id)');
        $this->addSql('CREATE INDEX IDX_8B6B94687E3C61F9 ON share_link (owner_id)');
        $this->addSql('CREATE TABLE stored_file (id UUID NOT NULL, name VARCHAR(255) NOT NULL, size BIGINT NOT NULL, mime_type VARCHAR(127) NOT NULL, sha256 VARCHAR(64) NOT NULL, storage_key VARCHAR(80) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, folder_id UUID DEFAULT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C339E77C111795A5 ON stored_file (storage_key)');
        $this->addSql('CREATE INDEX idx_stored_file_owner_folder ON stored_file (owner_id, folder_id)');
        $this->addSql('CREATE INDEX IDX_C339E77C162CB942 ON stored_file (folder_id)');
        $this->addSql('CREATE INDEX IDX_C339E77C7E3C61F9 ON stored_file (owner_id)');
        $this->addSql('ALTER TABLE folder ADD CONSTRAINT FK_ECA209CD727ACA70 FOREIGN KEY (parent_id) REFERENCES folder (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE folder ADD CONSTRAINT FK_ECA209CD7E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE share_link ADD CONSTRAINT FK_8B6B946893CB796C FOREIGN KEY (file_id) REFERENCES stored_file (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE share_link ADD CONSTRAINT FK_8B6B9468162CB942 FOREIGN KEY (folder_id) REFERENCES folder (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE share_link ADD CONSTRAINT FK_8B6B94687E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE stored_file ADD CONSTRAINT FK_C339E77C162CB942 FOREIGN KEY (folder_id) REFERENCES folder (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE stored_file ADD CONSTRAINT FK_C339E77C7E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE folder DROP CONSTRAINT FK_ECA209CD727ACA70');
        $this->addSql('ALTER TABLE folder DROP CONSTRAINT FK_ECA209CD7E3C61F9');
        $this->addSql('ALTER TABLE share_link DROP CONSTRAINT FK_8B6B946893CB796C');
        $this->addSql('ALTER TABLE share_link DROP CONSTRAINT FK_8B6B9468162CB942');
        $this->addSql('ALTER TABLE share_link DROP CONSTRAINT FK_8B6B94687E3C61F9');
        $this->addSql('ALTER TABLE stored_file DROP CONSTRAINT FK_C339E77C162CB942');
        $this->addSql('ALTER TABLE stored_file DROP CONSTRAINT FK_C339E77C7E3C61F9');
        $this->addSql('DROP TABLE folder');
        $this->addSql('DROP TABLE share_link');
        $this->addSql('DROP TABLE stored_file');
    }
}
