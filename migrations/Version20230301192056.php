<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230301192056 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attachment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, paste_id INTEGER NOT NULL, filename VARCHAR(255) NOT NULL, mimetype VARCHAR(255) NOT NULL, content BLOB NOT NULL)');
        $this->addSql('CREATE INDEX IDX_795FD9BBF0AF2BDB ON attachment (paste_id)');
        $this->addSql('CREATE TABLE paste (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, url VARCHAR(255) NOT NULL, content CLOB, ttl VARCHAR(255) DEFAULT \'2 weeks\' NOT NULL --(DC2Type:dateinterval)
        , created DATETIME DEFAULT \'now\' NOT NULL, nonce VARCHAR(20) NOT NULL, public BOOLEAN NOT NULL)');
        $this->addSql('CREATE INDEX IDX_9C567898A76ED395 ON paste (user_id)');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(100) NOT NULL, roles CLOB NOT NULL --(DC2Type:json)
        , password VARCHAR(255) NOT NULL, encryption_key_nonce VARCHAR(255) NOT NULL, password_nonce VARCHAR(255) NOT NULL, encrypted_encryption_key VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F85E0677 ON user (username)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE attachment');
        $this->addSql('DROP TABLE paste');
        $this->addSql('DROP TABLE user');
    }
}
