<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add campaigns.seed_nm_id for first-sync cluster discovery';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE campaigns ADD seed_nm_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE campaigns DROP seed_nm_id');
    }
}
